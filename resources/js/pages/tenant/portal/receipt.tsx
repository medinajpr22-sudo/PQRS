import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Copy, Printer, Search } from 'lucide-react';
import { PortalCard } from '@/components/portal/portal-card';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatDateTime, formatLongDate } from '@/lib/format';

type Props = {
    receipt: {
        radicado: string;
        type: string;
        sede: string | null;
        risk: string | null;
        subject: string;
        email: string;
        created_at: string;
        due_at: string;
        sla_days: number | null;
    };
};

export default function Receipt({ receipt }: Props) {
    const [copied, copy] = useClipboard();

    return (
        <>
            <Head title={`Radicado ${receipt.radicado}`} />

            <PortalCard className="mx-auto max-w-2xl text-center">
                <span className="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <CheckCircle2 className="size-9" />
                </span>
                <h2 className="mt-4 text-2xl font-bold tracking-tight">
                    ¡Tu solicitud fue radicada!
                </h2>
                <p className="mt-1 text-sm text-zinc-500">
                    Guarda este número para consultar el estado de tu solicitud.
                    También lo enviamos a{' '}
                    <strong className="text-zinc-700">{receipt.email}</strong>.
                </p>

                <div className="mx-auto mt-6 w-fit rounded-2xl border-2 border-dashed border-(--brand) bg-(--brand-soft) px-8 py-5">
                    <p className="text-xs font-medium tracking-wider text-zinc-500 uppercase">
                        Número de radicado
                    </p>
                    <p className="mt-1 font-mono text-3xl font-bold tracking-wider text-(--brand)">
                        {receipt.radicado}
                    </p>
                    <button
                        type="button"
                        onClick={() => void copy(receipt.radicado)}
                        className="mt-2 inline-flex items-center gap-1 text-xs font-medium text-zinc-600 hover:text-zinc-900 print:hidden"
                    >
                        <Copy className="size-3.5" />
                        {copied ? '¡Copiado!' : 'Copiar'}
                    </button>
                </div>

                <dl className="mt-8 grid gap-4 text-left text-sm sm:grid-cols-2">
                    <Item label="Tipo de solicitud" value={receipt.type} />
                    {receipt.sede && <Item label="Sede" value={receipt.sede} />}
                    {receipt.risk && (
                        <Item label="Nivel de riesgo" value={receipt.risk} />
                    )}
                    <Item
                        label="Fecha de radicación"
                        value={formatDateTime(receipt.created_at)}
                    />
                    <Item
                        label="Asunto"
                        value={receipt.subject}
                        className="sm:col-span-2"
                    />
                    <Item
                        label="Fecha máxima de respuesta"
                        value={`${formatLongDate(receipt.due_at)}${receipt.sla_days ? ` (${receipt.sla_days} días hábiles)` : ''}`}
                        className="sm:col-span-2"
                    />
                </dl>

                <div className="mt-8 flex flex-col justify-center gap-2 sm:flex-row print:hidden">
                    <Button asChild>
                        <Link href="/consultar">
                            <Search />
                            Consultar estado
                        </Link>
                    </Button>
                    <Button variant="outline" onClick={() => window.print()}>
                        <Printer />
                        Imprimir comprobante
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href="/">Volver al inicio</Link>
                    </Button>
                </div>
            </PortalCard>
        </>
    );
}

function Item({
    label,
    value,
    className,
}: {
    label: string;
    value: string;
    className?: string;
}) {
    return (
        <div className={`rounded-lg bg-zinc-50 p-3 ${className ?? ''}`}>
            <dt className="text-xs text-zinc-500">{label}</dt>
            <dd className="mt-0.5 font-medium">{value}</dd>
        </div>
    );
}

Receipt.layout = {
    active: 'create',
    title: 'Solicitud radicada',
    description: 'Hemos recibido tu PQRS correctamente.',
};
