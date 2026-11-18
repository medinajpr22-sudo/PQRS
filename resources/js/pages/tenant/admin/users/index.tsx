import { Form, Head } from '@inertiajs/react';
import { Pencil, UserPlus } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Tenant/Admin/UserController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { PageHeader } from '@/components/pqrs/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import admin from '@/routes/tenant/admin';
import type { StaffUser } from '@/types';

type RoleOption = { value: string; label: string; description: string };

type Props = {
    users: StaffUser[];
    roles: RoleOption[];
};

export default function UsersIndex({ users, roles }: Props) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<StaffUser | null>(null);

    return (
        <>
            <Head title="Usuarios" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Usuarios"
                    description="Funcionarios que atienden las PQRS de la empresa. Los usuarios se desactivan en lugar de borrarse, para conservar el historial."
                    actions={
                        <Button onClick={() => setCreating(true)}>
                            <UserPlus />
                            Nuevo usuario
                        </Button>
                    }
                />

                <Card className="gap-0 py-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Nombre
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Rol
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        PQRS abiertas asignadas
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.map((u) => (
                                    <tr key={u.id}>
                                        <td className="px-4 py-3">
                                            <p className="font-medium">
                                                {u.name}
                                                {u.is_me && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        (tú)
                                                    </span>
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {u.email}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    u.role === 'admin'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {u.role_label}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            {u.is_active ? (
                                                'Activo'
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Inactivo
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {u.open_assigned}
                                            {!u.is_active &&
                                                u.open_assigned > 0 && (
                                                    <span className="ml-2 text-xs text-amber-600">
                                                        Reasígnalas
                                                    </span>
                                                )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setEditing(u)}
                                            >
                                                <Pencil />
                                                Editar
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent>
                    <DialogTitle>Nuevo usuario</DialogTitle>
                    <DialogDescription>
                        Comparte la contraseña inicial con el funcionario por un
                        medio seguro.
                    </DialogDescription>
                    <Form
                        {...UserController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        onSuccess={() => setCreating(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <UserFields roles={roles} errors={errors} />
                                <div className="grid gap-2">
                                    <Label htmlFor="new-password">
                                        Contraseña inicial
                                    </Label>
                                    <PasswordInput
                                        id="new-password"
                                        name="password"
                                        required
                                        autoComplete="new-password"
                                    />
                                    <InputError message={errors.password} />
                                </div>
                                <DialogFooter className="gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setCreating(false)}
                                    >
                                        Cancelar
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Crear usuario
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    {editing && (
                        <>
                            <DialogTitle>Editar usuario</DialogTitle>
                            <DialogDescription>
                                Al desactivarlo se cierran sus sesiones
                                abiertas.
                            </DialogDescription>
                            <Form
                                key={editing.id}
                                {...UserController.update.form(editing.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setEditing(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <UserFields
                                            roles={roles}
                                            errors={errors}
                                            user={editing}
                                        />
                                        <div className="grid gap-2">
                                            <Label htmlFor="is_active">
                                                Estado
                                            </Label>
                                            <NativeSelect
                                                id="is_active"
                                                name="is_active"
                                                defaultValue={
                                                    editing.is_active
                                                        ? '1'
                                                        : '0'
                                                }
                                                disabled={editing.is_me}
                                            >
                                                <option value="1">
                                                    Activo
                                                </option>
                                                <option value="0">
                                                    Inactivo
                                                </option>
                                            </NativeSelect>
                                            {editing.is_me && (
                                                <input
                                                    type="hidden"
                                                    name="is_active"
                                                    value="1"
                                                />
                                            )}
                                            <InputError
                                                message={errors.is_active}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-password">
                                                Nueva contraseña (opcional)
                                            </Label>
                                            <PasswordInput
                                                id="edit-password"
                                                name="password"
                                                autoComplete="new-password"
                                                placeholder="Déjala vacía para no cambiarla"
                                            />
                                            <InputError
                                                message={errors.password}
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
        </>
    );
}

function UserFields({
    roles,
    errors,
    user,
}: {
    roles: RoleOption[];
    errors: Partial<Record<string, string>>;
    user?: StaffUser;
}) {
    const [role, setRole] = useState(user?.role ?? 'funcionario');

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">Nombre completo</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    defaultValue={user?.name}
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="email">Correo electrónico</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    required
                    defaultValue={user?.email}
                />
                <InputError message={errors.email} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="role">Rol</Label>
                <NativeSelect
                    id="role"
                    name="role"
                    value={role}
                    onChange={(e) => setRole(e.target.value as typeof role)}
                    disabled={user?.is_me}
                >
                    {roles.map((r) => (
                        <option key={r.value} value={r.value}>
                            {r.label}
                        </option>
                    ))}
                </NativeSelect>
                {user?.is_me && (
                    <input type="hidden" name="role" value={role} />
                )}
                <p className="text-xs text-muted-foreground">
                    {roles.find((r) => r.value === role)?.description}
                </p>
                <InputError message={errors.role} />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Resumen', href: admin.dashboard() },
        { title: 'Usuarios', href: admin.users.index() },
    ],
};
