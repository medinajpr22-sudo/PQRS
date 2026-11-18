import { Form, Head } from '@inertiajs/react';
import { Search } from 'lucide-react';
import PqrsTrackingController from '@/actions/App/Http/Controllers/Tenant/PqrsTrackingController';
import InputError from '@/components/input-error';
import { PortalCard } from '@/components/portal/portal-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function Track({ radicado }: { radicado: string | null }) {
    return (
        <>
            <Head title="Consultar estado" />

            <PortalCard
                className="mx-auto max-w-xl"
                title="Consulta tu solicitud"
                description="Ingresa el número de radicado y el correo con el que radicaste."
            >
                <Form
                    {...PqrsTrackingController.store.form()}
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="radicado">
                                    Número de radicado
                                </Label>
                                <Input
                                    id="radicado"
                                    name="radicado"
                                    required
                                    autoFocus={!radicado}
                                    defaultValue={radicado ?? undefined}
                                    placeholder="Ej: ABC-2026-00001"
                                    className="font-mono uppercase"
                                    aria-invalid={!!errors.radicado}
                                />
                                <InputError message={errors.radicado} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    Correo electrónico
                                </Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autoFocus={!!radicado}
                                    autoComplete="email"
                                    aria-invalid={!!errors.email}
                                />
                                <InputError message={errors.email} />
                            </div>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                            >
                                {processing ? <Spinner /> : <Search />}
                                Consultar
                            </Button>
                        </>
                    )}
                </Form>
            </PortalCard>
        </>
    );
}

Track.layout = {
    active: 'track',
    title: 'Consultar el estado de tu PQRS',
    description:
        'Revisa en qué va tu solicitud y descarga la respuesta cuando esté disponible.',
};
