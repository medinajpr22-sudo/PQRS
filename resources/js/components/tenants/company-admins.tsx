import { Form } from '@inertiajs/react';
import { KeyRound, Pencil, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import TenantAdminController from '@/actions/App/Http/Controllers/Central/TenantAdminController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { CompanyAdmin } from '@/types';

/**
 * Administradores de la empresa: el super administrador puede corregir su
 * nombre, su correo (con el que inician sesión en el panel de la empresa) y
 * ponerles una contraseña nueva si la olvidaron.
 */
export function CompanyAdmins({
    tenantId,
    admins,
}: {
    tenantId: string;
    admins: CompanyAdmin[];
}) {
    const [editing, setEditing] = useState<CompanyAdmin | null>(null);
    const [password, setPassword] = useState('');

    const close = () => {
        setEditing(null);
        setPassword('');
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ShieldCheck className="size-4" />
                    Administradores de la empresa
                </CardTitle>
                <CardDescription>
                    Inician sesión en el panel de la empresa con este correo.
                </CardDescription>
            </CardHeader>
            <CardContent className="divide-y">
                {admins.length === 0 && (
                    <p className="py-3 text-sm text-muted-foreground">
                        La empresa no tiene administradores.
                    </p>
                )}
                {admins.map((a) => (
                    <div
                        key={a.id}
                        className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                    >
                        <div className="min-w-0">
                            <p className="truncate font-medium">
                                {a.name}
                                {!a.is_active && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        (inactivo)
                                    </span>
                                )}
                            </p>
                            <p className="truncate text-sm text-muted-foreground">
                                {a.email}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setEditing(a)}
                        >
                            <Pencil />
                            Editar
                        </Button>
                    </div>
                ))}
            </CardContent>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && close()}
            >
                <DialogContent>
                    {editing && (
                        <>
                            <DialogTitle>Editar administrador</DialogTitle>
                            <DialogDescription>
                                Si cambias el correo, desde ahora debe iniciar
                                sesión con el nuevo. Si le pones una contraseña
                                nueva, se cierran sus sesiones abiertas.
                            </DialogDescription>
                            <Form
                                key={editing.id}
                                {...TenantAdminController.update.form({
                                    tenant: tenantId,
                                    admin: editing.id,
                                })}
                                options={{ preserveScroll: true }}
                                onSuccess={close}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="company-admin-name">
                                                Nombre
                                            </Label>
                                            <Input
                                                id="company-admin-name"
                                                name="name"
                                                required
                                                defaultValue={editing.name}
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="company-admin-email">
                                                Correo electrónico
                                            </Label>
                                            <Input
                                                id="company-admin-email"
                                                name="email"
                                                type="email"
                                                required
                                                defaultValue={editing.email}
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <div className="flex items-center justify-between gap-2">
                                                <Label htmlFor="company-admin-password">
                                                    Nueva contraseña (opcional)
                                                </Label>
                                                <Button
                                                    type="button"
                                                    variant="link"
                                                    size="sm"
                                                    className="h-auto p-0"
                                                    onClick={() =>
                                                        setPassword(
                                                            strongPassword(),
                                                        )
                                                    }
                                                >
                                                    <KeyRound />
                                                    Generar una
                                                </Button>
                                            </div>
                                            <PasswordInput
                                                id="company-admin-password"
                                                name="password"
                                                autoComplete="new-password"
                                                value={password}
                                                onChange={(e) =>
                                                    setPassword(e.target.value)
                                                }
                                                placeholder="Déjala vacía para no cambiarla"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Por ejemplo, si la olvidó.
                                                Compártesela por un medio seguro
                                                y pídele que la cambie al
                                                entrar.
                                            </p>
                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>
                                        <DialogFooter className="gap-2">
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                onClick={close}
                                            >
                                                Cancelar
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                Guardar cambios
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </Card>
    );
}

/**
 * Contraseña aleatoria de 16 caracteres con mayúsculas, minúsculas, números
 * y símbolos (cumple las reglas de producción). Sin letras que se confundan
 * (0/O, 1/l/I), para dictarla o copiarla sin errores.
 */
function strongPassword(): string {
    const sets = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnpqrstuvwxyz',
        '23456789',
        '!#$%*+-=?@',
    ];
    const all = sets.join('');
    const random = (max: number) =>
        crypto.getRandomValues(new Uint32Array(1))[0] % max;
    const chars = [
        ...sets.map((set) => set[random(set.length)]),
        ...Array.from({ length: 12 }, () => all[random(all.length)]),
    ];

    // Mezcla para que los obligatorios no queden siempre al inicio.
    for (let i = chars.length - 1; i > 0; i--) {
        const j = random(i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }

    return chars.join('');
}
