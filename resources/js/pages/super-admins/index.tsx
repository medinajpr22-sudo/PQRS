import { Form, Head, Link } from '@inertiajs/react';
import {
    Pencil,
    Search,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    UserPlus,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import SuperAdminController from '@/actions/App/Http/Controllers/Central/SuperAdminController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/hooks/use-initials';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import superAdmins from '@/routes/super-admins';
import { edit as editSecurity } from '@/routes/security';
import type { SuperAdmin } from '@/types';

type Props = {
    admins: SuperAdmin[];
};

export default function SuperAdminsIndex({ admins }: Props) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<SuperAdmin | null>(null);
    const [deleting, setDeleting] = useState<SuperAdmin | null>(null);
    const [search, setSearch] = useState('');
    const getInitials = useInitials();

    // La lista es corta: se filtra en el navegador.
    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();

        return admins.filter(
            (a) =>
                term === '' ||
                a.name.toLowerCase().includes(term) ||
                a.email.toLowerCase().includes(term),
        );
    }, [admins, search]);

    const withoutTwoFactor = admins.filter((a) => !a.two_factor).length;

    return (
        <>
            <Head title="Super administradores" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Super administradores"
                    description="Personas con acceso total a la plataforma: todas las empresas, sus datos y los demás super administradores."
                    actions={
                        <Button
                            onClick={() => setCreating(true)}
                            className="h-11 rounded-xl px-5 font-bold shadow-md"
                        >
                            <UserPlus />
                            Nuevo super administrador
                        </Button>
                    }
                />

                {withoutTwoFactor > 0 && (
                    <div className="flex items-start gap-3 rounded-2xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm">
                        <ShieldAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                        <p>
                            {withoutTwoFactor === 1
                                ? 'Un super administrador no tiene'
                                : `${withoutTwoFactor} super administradores no tienen`}{' '}
                            activa la verificación en dos pasos. Con este nivel
                            de acceso es muy recomendable: cada uno la activa en
                            Ajustes → Seguridad.
                        </p>
                    </div>
                )}

                {admins.length > 5 && (
                    <div className="relative lg:w-96">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nombre o correo"
                            aria-label="Buscar super administradores"
                            className="h-11 rounded-xl bg-card pl-9"
                        />
                    </div>
                )}

                <Card className="gap-0 overflow-hidden rounded-2xl py-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b text-left text-[11px] tracking-[0.12em] text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-5 py-4 font-bold">
                                        Super administrador
                                    </th>
                                    <th className="px-5 py-4 font-bold">
                                        Verificación en dos pasos
                                    </th>
                                    <th className="px-5 py-4 font-bold">
                                        Agregado
                                    </th>
                                    <th className="px-5 py-4" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {visible.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-5 py-12 text-center text-muted-foreground"
                                        >
                                            Ningún super administrador coincide
                                            con la búsqueda.
                                        </td>
                                    </tr>
                                )}
                                {visible.map((a) => (
                                    <tr key={a.id}>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-(--panel) text-xs font-bold text-(--panel-fg)">
                                                    {getInitials(a.name)}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="truncate font-bold">
                                                        {a.name}
                                                        {a.is_me && (
                                                            <span className="font-medium text-muted-foreground">
                                                                {' '}
                                                                (tú)
                                                            </span>
                                                        )}
                                                    </p>
                                                    <p className="truncate text-muted-foreground">
                                                        {a.email}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            {a.two_factor ? (
                                                <span className="inline-flex items-center gap-1 rounded-full border border-emerald-600/60 px-2.5 py-0.5 text-xs font-bold whitespace-nowrap text-emerald-700 dark:text-emerald-400">
                                                    <ShieldCheck className="size-3" />
                                                    Activa
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 rounded-full border border-amber-500/60 px-2.5 py-0.5 text-xs font-bold whitespace-nowrap text-amber-700 dark:text-amber-400">
                                                    <ShieldAlert className="size-3" />
                                                    Sin activar
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 whitespace-nowrap text-muted-foreground">
                                            {formatDate(a.created_at)}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-9 rounded-lg font-bold"
                                                    onClick={() =>
                                                        setEditing(a)
                                                    }
                                                >
                                                    <Pencil />
                                                    Editar
                                                </Button>
                                                {!a.is_me && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-9 rounded-lg font-bold text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            setDeleting(a)
                                                        }
                                                        aria-label={`Quitar acceso a ${a.name}`}
                                                    >
                                                        <Trash2 />
                                                        Quitar
                                                    </Button>
                                                )}
                                            </div>
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
                    <DialogTitle>Nuevo super administrador</DialogTitle>
                    <DialogDescription>
                        Tendrá acceso total a la plataforma. Compártele la
                        contraseña inicial por un medio seguro y pídele que
                        active la verificación en dos pasos.
                    </DialogDescription>
                    <Form
                        {...SuperAdminController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        onSuccess={() => setCreating(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <AdminFields errors={errors} />
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
                                        Crear super administrador
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
                            <DialogTitle>
                                Editar super administrador
                            </DialogTitle>
                            <DialogDescription>
                                {editing.is_me
                                    ? 'Estos son tus datos de acceso.'
                                    : 'Si le cambias la contraseña (por ejemplo, si la olvidó o perdió el celular), se cierran sus sesiones y se le quitan la verificación en dos pasos y las llaves de acceso: las configura de nuevo al entrar.'}
                            </DialogDescription>
                            <Form
                                key={editing.id}
                                {...SuperAdminController.update.form(
                                    editing.id,
                                )}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setEditing(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <AdminFields
                                            errors={errors}
                                            admin={editing}
                                        />
                                        {editing.is_me ? (
                                            <p className="text-sm text-muted-foreground">
                                                Tu contraseña se cambia en{' '}
                                                <Link
                                                    href={editSecurity()}
                                                    className="font-semibold text-foreground underline underline-offset-4"
                                                >
                                                    Ajustes → Seguridad
                                                </Link>
                                                , donde se pide la actual.
                                            </p>
                                        ) : (
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
                                        )}
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

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    {deleting && (
                        <>
                            <DialogTitle>
                                ¿Quitar el acceso a {deleting.name}?
                            </DialogTitle>
                            <DialogDescription>
                                Ya no podrá entrar al panel de administración y
                                se cerrarán sus sesiones abiertas. Las empresas
                                y sus datos no cambian.
                            </DialogDescription>
                            <Form
                                key={deleting.id}
                                {...SuperAdminController.destroy.form(
                                    deleting.id,
                                )}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setDeleting(null)}
                            >
                                {({ processing }) => (
                                    <DialogFooter className="gap-2">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() => setDeleting(null)}
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            Quitar acceso
                                        </Button>
                                    </DialogFooter>
                                )}
                            </Form>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function AdminFields({
    errors,
    admin,
}: {
    errors: Partial<Record<string, string>>;
    admin?: SuperAdmin;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">Nombre completo</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    autoComplete="name"
                    defaultValue={admin?.name}
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
                    autoComplete="email"
                    defaultValue={admin?.email}
                />
                <InputError message={errors.email} />
                <p className="text-xs text-muted-foreground">
                    Con este correo inicia sesión en el panel.
                </p>
            </div>
        </>
    );
}

SuperAdminsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Super administradores', href: superAdmins.index() },
    ],
};
