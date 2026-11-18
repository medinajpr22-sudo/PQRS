import { Form } from '@inertiajs/react';
import { Pencil, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import TenantAdminController from '@/actions/App/Http/Controllers/Central/TenantAdminController';
import InputError from '@/components/input-error';
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
 * nombre y su correo (con el que inician sesión en el panel de la empresa).
 */
export function CompanyAdmins({
    tenantId,
    admins,
}: {
    tenantId: string;
    admins: CompanyAdmin[];
}) {
    const [editing, setEditing] = useState<CompanyAdmin | null>(null);

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
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    {editing && (
                        <>
                            <DialogTitle>Editar administrador</DialogTitle>
                            <DialogDescription>
                                Si cambias el correo, desde ahora debe iniciar
                                sesión con el nuevo.
                            </DialogDescription>
                            <Form
                                key={editing.id}
                                {...TenantAdminController.update.form({
                                    tenant: tenantId,
                                    admin: editing.id,
                                })}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setEditing(null)}
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
                                        <DialogFooter className="gap-2">
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                onClick={() => setEditing(null)}
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
