import { Head } from '@inertiajs/react';
import { PauseCircle } from 'lucide-react';
import { PortalCard } from '@/components/portal/portal-card';

export default function Suspended() {
    return (
        <>
            <Head title="Portal no disponible" />
            <PortalCard className="mx-auto max-w-lg text-center">
                <PauseCircle className="mx-auto size-12 text-zinc-400" />
                <h2 className="mt-4 text-xl font-semibold">
                    Portal temporalmente no disponible
                </h2>
                <p className="mt-2 text-sm text-zinc-500">
                    En este momento no estamos recibiendo solicitudes por este
                    medio. Por favor comunícate con nosotros a través de
                    nuestros canales de atención.
                </p>
            </PortalCard>
        </>
    );
}

Suspended.layout = {
    title: 'Servicio no disponible',
    description: ' ',
};
