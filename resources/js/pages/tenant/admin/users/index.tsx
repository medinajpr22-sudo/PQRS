import { Form, Head } from '@inertiajs/react';
import { Check, MapPin, Pencil, Search, UserPlus } from 'lucide-react';
import { useMemo, useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Tenant/Admin/UserController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { FilterPills } from '@/components/pqrs/filter-pills';
import { PageHeader } from '@/components/pqrs/page-header';
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
import { useInitials } from '@/hooks/use-initials';
import { formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/tenant/admin';
import type { StaffUser } from '@/types';

type RoleOption = { value: string; label: string; description: string };

type SedeOption = { value: number; label: string };

type Props = {
    users: StaffUser[];
    roles: RoleOption[];
    sedes: SedeOption[];
};

export default function UsersIndex({ users, roles, sedes }: Props) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<StaffUser | null>(null);
    const [search, setSearch] = useState('');
    const [role, setRole] = useState('');
    const [status, setStatus] = useState<string | null>(null);
    const getInitials = useInitials();

    // La lista es corta: se filtra en el navegador.
    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();

        return users.filter(
            (u) =>
                (term === '' ||
                    u.name.toLowerCase().includes(term) ||
                    u.email.toLowerCase().includes(term)) &&
                (role === '' || u.role === role) &&
                (status === null ||
                    (status === 'active' ? u.is_active : !u.is_active)),
        );
    }, [users, search, role, status]);

    return (
        <>
            <Head title="Usuarios" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Usuarios"
                    description="Gestiona quién entra al panel de PQRS, con qué rol y qué sedes atiende cada agente."
                    actions={
                        <Button
                            onClick={() => setCreating(true)}
                            className="h-11 rounded-xl px-5 font-bold shadow-md"
                        >
                            <UserPlus />
                            Nuevo usuario
                        </Button>
                    }
                />

                <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div className="relative lg:w-96">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nombre o correo"
                            aria-label="Buscar usuarios"
                            className="h-11 rounded-xl bg-card pl-9"
                        />
                    </div>
                    <NativeSelect
                        aria-label="Rol"
                        value={role}
                        onChange={(e) => setRole(e.target.value)}
                        className="h-11 rounded-xl bg-card lg:w-60"
                    >
                        <option value="">Todos los roles</option>
                        {roles.map((r) => (
                            <option key={r.value} value={r.value}>
                                {r.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <FilterPills
                        label="Estado"
                        options={[
                            { value: null, label: 'Todos' },
                            { value: 'active', label: 'Activos' },
                            { value: 'inactive', label: 'Inactivos' },
                        ]}
                        value={status}
                        onChange={setStatus}
                    />
                </div>

                <Card className="gap-0 overflow-hidden rounded-2xl py-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b text-left text-[11px] tracking-[0.12em] text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-5 py-4 font-bold">
                                        Usuario
                                    </th>
                                    <th className="px-5 py-4 font-bold">Rol</th>
                                    <th className="px-5 py-4 font-bold">
                                        Sedes
                                    </th>
                                    <th className="px-5 py-4 font-bold">
                                        PQRS abiertas
                                    </th>
                                    <th className="px-5 py-4 font-bold">
                                        Estado
                                    </th>
                                    <th className="px-5 py-4 font-bold">
                                        Último acceso
                                    </th>
                                    <th className="px-5 py-4" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {visible.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-5 py-12 text-center text-muted-foreground"
                                        >
                                            Ningún usuario coincide con los
                                            filtros.
                                        </td>
                                    </tr>
                                )}
                                {visible.map((u) => (
                                    <tr key={u.id}>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-(--panel) text-xs font-bold text-(--panel-fg)">
                                                    {getInitials(u.name)}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="truncate font-bold">
                                                        {u.name}
                                                        {u.is_me && (
                                                            <span className="font-medium text-muted-foreground">
                                                                {' '}
                                                                (tú)
                                                            </span>
                                                        )}
                                                    </p>
                                                    <p className="truncate text-muted-foreground">
                                                        {u.email}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span
                                                className={cn(
                                                    'inline-flex rounded-full border px-2.5 py-0.5 text-xs font-bold whitespace-nowrap',
                                                    u.role === 'admin'
                                                        ? 'border-(--panel-accent) text-(--panel-accent)'
                                                        : 'border-foreground/40',
                                                )}
                                            >
                                                {u.role_label}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {u.role === 'admin' ? (
                                                <span className="text-muted-foreground">
                                                    Todas
                                                </span>
                                            ) : u.sedes.length > 0 ? (
                                                <div className="flex flex-wrap gap-1.5">
                                                    {u.sedes.map((s) => (
                                                        <span
                                                            key={s.id}
                                                            className="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                                        >
                                                            <MapPin className="size-3" />
                                                            {s.name}
                                                        </span>
                                                    ))}
                                                </div>
                                            ) : (
                                                <span className="text-xs text-amber-600">
                                                    Sin sedes: no ve ninguna
                                                    PQRS
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 tabular-nums">
                                            {u.open_assigned}
                                            {!u.is_active &&
                                                u.open_assigned > 0 && (
                                                    <span className="ml-2 text-xs text-amber-600">
                                                        Reasígnalas
                                                    </span>
                                                )}
                                        </td>
                                        <td className="px-5 py-4">
                                            {u.is_active ? (
                                                <span className="inline-flex items-center gap-1 rounded-full border border-emerald-600/60 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                                    <Check className="size-3" />
                                                    Activo
                                                </span>
                                            ) : (
                                                <span className="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-bold text-muted-foreground">
                                                    Inactivo
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 whitespace-nowrap text-muted-foreground">
                                            {formatRelative(u.last_login_at)}
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-9 rounded-lg font-bold"
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
                        Comparte la contraseña inicial con el usuario por un
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
                                <UserFields
                                    roles={roles}
                                    sedes={sedes}
                                    errors={errors}
                                />
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
                                            sedes={sedes}
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
    sedes,
    errors,
    user,
}: {
    roles: RoleOption[];
    sedes: SedeOption[];
    errors: Partial<Record<string, string>>;
    user?: StaffUser;
}) {
    const [role, setRole] = useState(user?.role ?? 'agente');
    const userSedes = new Set(user?.sedes.map((s) => s.id) ?? []);
    const sedeError = Object.entries(errors).find(([k]) =>
        k.startsWith('sedes'),
    )?.[1];

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
            {role === 'agente' && (
                <fieldset className="grid gap-2">
                    <legend className="mb-2 text-sm font-medium">
                        Sedes que atiende
                    </legend>
                    {sedes.length === 0 ? (
                        <p className="text-xs text-amber-600">
                            Primero crea las sedes de la empresa en «Sedes».
                        </p>
                    ) : (
                        <div className="grid gap-2 sm:grid-cols-2">
                            {sedes.map((s) => (
                                <label
                                    key={s.value}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        name="sedes[]"
                                        value={s.value}
                                        defaultChecked={userSedes.has(s.value)}
                                        className="size-4"
                                    />
                                    {s.label}
                                </label>
                            ))}
                        </div>
                    )}
                    <p className="text-xs text-muted-foreground">
                        Solo verá y gestionará las PQRS de estas sedes.
                    </p>
                    <InputError message={sedeError} />
                </fieldset>
            )}
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Resumen', href: admin.dashboard() },
        { title: 'Usuarios', href: admin.users.index() },
    ],
};
