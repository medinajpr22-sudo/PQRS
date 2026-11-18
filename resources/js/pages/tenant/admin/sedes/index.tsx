import { Form, Head } from '@inertiajs/react';
import { MapPinPlus, Pencil } from 'lucide-react';
import { useState } from 'react';
import SedeController from '@/actions/App/Http/Controllers/Tenant/Admin/SedeController';
import InputError from '@/components/input-error';
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
import admin from '@/routes/tenant/admin';

type SedeRow = {
    id: number;
    name: string;
    is_active: boolean;
    agents_count: number;
    open_pqrs_count: number;
    total_pqrs_count: number;
};

export default function SedesIndex({ sedes }: { sedes: SedeRow[] }) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<SedeRow | null>(null);

    return (
        <>
            <Head title="Sedes" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Sedes"
                    description="Cada PQRS pertenece a una sede y cada agente solo ve las de sus sedes. Las sedes se desactivan en lugar de borrarse."
                    actions={
                        <Button onClick={() => setCreating(true)}>
                            <MapPinPlus />
                            Nueva sede
                        </Button>
                    }
                />

                <Card className="gap-0 py-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Sede
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Agentes
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        PQRS abiertas
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        PQRS totales
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {sedes.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Aún no hay sedes. Mientras no
                                            existan, el formulario público no
                                            pide sede y solo el administrador ve
                                            las PQRS.
                                        </td>
                                    </tr>
                                )}
                                {sedes.map((s) => (
                                    <tr key={s.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {s.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {s.is_active ? (
                                                'Activa'
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Inactiva
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {s.agents_count}
                                            {s.is_active &&
                                                s.agents_count === 0 && (
                                                    <span className="ml-2 text-xs text-amber-600">
                                                        Sin agentes
                                                    </span>
                                                )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {s.open_pqrs_count}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {s.total_pqrs_count}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setEditing(s)}
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
                    <DialogTitle>Nueva sede</DialogTitle>
                    <DialogDescription>
                        Después asígnala a los agentes que la atienden, desde
                        Usuarios.
                    </DialogDescription>
                    <Form
                        {...SedeController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        onSuccess={() => setCreating(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="new-sede-name">
                                        Nombre
                                    </Label>
                                    <Input
                                        id="new-sede-name"
                                        name="name"
                                        required
                                        placeholder="Ej.: Neiva"
                                    />
                                    <InputError message={errors.name} />
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
                                        Crear sede
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
                            <DialogTitle>Editar sede</DialogTitle>
                            <DialogDescription>
                                Una sede inactiva ya no aparece en el formulario
                                público; sus PQRS y agentes se conservan.
                            </DialogDescription>
                            <Form
                                key={editing.id}
                                {...SedeController.update.form(editing.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setEditing(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="sede-name">
                                                Nombre
                                            </Label>
                                            <Input
                                                id="sede-name"
                                                name="name"
                                                required
                                                defaultValue={editing.name}
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="sede-active">
                                                Estado
                                            </Label>
                                            <NativeSelect
                                                id="sede-active"
                                                name="is_active"
                                                defaultValue={
                                                    editing.is_active
                                                        ? '1'
                                                        : '0'
                                                }
                                            >
                                                <option value="1">
                                                    Activa
                                                </option>
                                                <option value="0">
                                                    Inactiva
                                                </option>
                                            </NativeSelect>
                                            <InputError
                                                message={errors.is_active}
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

SedesIndex.layout = {
    breadcrumbs: [
        { title: 'Resumen', href: admin.dashboard() },
        { title: 'Sedes', href: admin.sedes.index() },
    ],
};
