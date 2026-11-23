import { Form, router } from '@inertiajs/react';
import { PauseCircle, PlayCircle, Trash2 } from 'lucide-react';
import { useState } from 'react';
import TenantController from '@/actions/App/Http/Controllers/Central/TenantController';
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
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import tenants from '@/routes/tenants';
import type { TenantSummary } from '@/types';

export function DangerZone({ tenant }: { tenant: TenantSummary }) {
    const active = tenant.status === 'active';
    const [saving, setSaving] = useState(false);

    return (
        <Card className="border-red-200 dark:border-red-900/50">
            <CardHeader>
                <CardTitle>Zona de riesgo</CardTitle>
                <CardDescription>
                    Acciones que afectan la disponibilidad del portal.
                </CardDescription>
            </CardHeader>
            <CardContent className="divide-y">
                <div className="flex flex-col justify-between gap-3 pb-4 sm:flex-row sm:items-center">
                    <div>
                        <p className="font-medium">
                            {active ? 'Suspender empresa' : 'Activar empresa'}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {active
                                ? 'El portal mostrará un aviso y no recibirá nuevas solicitudes. Los datos se conservan.'
                                : 'El portal volverá a estar disponible para los ciudadanos.'}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        disabled={saving}
                        onClick={() =>
                            router.patch(
                                tenants.status.update.url(tenant.id),
                                { status: active ? 'suspended' : 'active' },
                                {
                                    preserveScroll: true,
                                    onStart: () => setSaving(true),
                                    onFinish: () => setSaving(false),
                                },
                            )
                        }
                    >
                        {active ? <PauseCircle /> : <PlayCircle />}
                        {active ? 'Suspender' : 'Activar'}
                    </Button>
                </div>
                <div className="flex flex-col justify-between gap-3 pt-4 sm:flex-row sm:items-center">
                    <div>
                        <p className="font-medium text-red-600 dark:text-red-400">
                            Eliminar empresa
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Borra su base de datos, sus PQRS, usuarios y
                            archivos. No se puede deshacer.
                        </p>
                    </div>
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="destructive">
                                <Trash2 />
                                Eliminar
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                ¿Eliminar «{tenant.name}»?
                            </DialogTitle>
                            <DialogDescription>
                                Se eliminará de forma permanente la base de
                                datos de la empresa con todas sus solicitudes,
                                usuarios y adjuntos. Escribe{' '}
                                <strong className="font-mono">
                                    {tenant.id}
                                </strong>{' '}
                                para confirmar.
                            </DialogDescription>
                            <Form
                                {...TenantController.destroy.form(tenant.id)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Input
                                            name="confirmation"
                                            autoComplete="off"
                                            placeholder={tenant.id}
                                            className="font-mono"
                                        />
                                        <InputError
                                            message={errors.confirmation}
                                        />
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    variant="secondary"
                                                    type="button"
                                                >
                                                    Cancelar
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                variant="destructive"
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Eliminar definitivamente
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </CardContent>
        </Card>
    );
}
