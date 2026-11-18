import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BellRing,
    FilePlus2,
    Hash,
    Search,
    ShieldCheck,
} from 'lucide-react';
import { SaludHome } from '@/components/portal/salud-home';
import type { PqrsTypeConfig, RiskLevelOption } from '@/types';

type Props = {
    types: PqrsTypeConfig[];
    privacyPolicyUrl: string;
    riskLevels: RiskLevelOption[];
};

export default function PortalHome({
    types,
    privacyPolicyUrl,
    riskLevels,
}: Props) {
    const { tenant } = usePage().props;

    // La plantilla «Salud» tiene su propio inicio (barra de consulta y tipos
    // con su definición).
    if (tenant?.theme.layout === 'salud') {
        return (
            <>
                <Head title="Inicio" />
                <SaludHome types={types} riskLevels={riskLevels} />
            </>
        );
    }

    return (
        <>
            <Head title="Inicio" />

            <div className="space-y-10">
                <div className="grid gap-4 md:grid-cols-2">
                    <ActionCard
                        href="/radicar"
                        icon={FilePlus2}
                        title="Radicar una solicitud"
                        description="Presenta una petición, queja, reclamo o sugerencia. Recibirás un número de radicado al instante."
                        cta="Radicar PQRS"
                        primary
                    />
                    <ActionCard
                        href="/consultar"
                        icon={Search}
                        title="Consultar el estado"
                        description="Revisa el avance y la respuesta de tu solicitud con tu número de radicado y correo."
                        cta="Consultar"
                    />
                </div>

                <section>
                    <h2 className="text-xl font-semibold tracking-tight">
                        ¿Qué puedes radicar?
                    </h2>
                    <p className="mt-1 text-sm text-zinc-500">
                        Elige el tipo de solicitud que mejor describa tu caso.
                    </p>
                    <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {types.map((type) => (
                            <Link
                                key={type.key}
                                href={`/radicar?tipo=${type.key}`}
                                className="group flex flex-col rounded-xl border border-zinc-200 bg-white p-5 transition-all hover:-translate-y-0.5 hover:border-(--brand) hover:shadow-md"
                            >
                                <div className="flex items-center justify-between">
                                    <h3 className="font-semibold">
                                        {type.label}
                                    </h3>
                                    <ArrowRight className="size-4 text-zinc-400 transition-transform group-hover:translate-x-0.5 group-hover:text-(--brand)" />
                                </div>
                                <p className="mt-1.5 flex-1 text-sm text-zinc-500">
                                    {type.description}
                                </p>
                                <p className="mt-4 text-xs font-medium text-zinc-600">
                                    Respuesta en máximo{' '}
                                    <span className="text-(--brand)">
                                        {type.sla_days} días hábiles
                                    </span>
                                </p>
                            </Link>
                        ))}
                    </div>
                </section>

                <section className="rounded-2xl bg-(--brand-soft) p-6 sm:p-8">
                    <h2 className="text-lg font-semibold tracking-tight">
                        ¿Cómo funciona?
                    </h2>
                    <ol className="mt-5 grid gap-6 sm:grid-cols-3">
                        <Step
                            icon={FilePlus2}
                            title="1. Radica"
                            text="Diligencia el formulario con los datos de tu solicitud y adjunta soportes si los tienes."
                        />
                        <Step
                            icon={Hash}
                            title="2. Guarda tu radicado"
                            text="Te mostramos y enviamos por correo un número único para hacer seguimiento."
                        />
                        <Step
                            icon={BellRing}
                            title="3. Recibe respuesta"
                            text="Te notificaremos por correo electrónico cuando tu solicitud sea respondida."
                        />
                    </ol>
                </section>

                <p className="flex items-start gap-2 text-xs text-zinc-500">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                    <span>
                        Tus datos personales son tratados de acuerdo con la Ley
                        1581 de 2012.
                        {privacyPolicyUrl && (
                            <>
                                {' '}
                                Consulta nuestra{' '}
                                <a
                                    href={privacyPolicyUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline"
                                >
                                    política de tratamiento de datos
                                </a>
                                .
                            </>
                        )}
                    </span>
                </p>
            </div>
        </>
    );
}

function ActionCard({
    href,
    icon: Icon,
    title,
    description,
    cta,
    primary = false,
}: {
    href: string;
    icon: typeof Search;
    title: string;
    description: string;
    cta: string;
    primary?: boolean;
}) {
    return (
        <Link
            href={href}
            className="group flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-lg sm:p-7"
        >
            <span
                className={
                    primary
                        ? 'flex size-12 items-center justify-center rounded-xl bg-(--brand) text-(--brand-fg)'
                        : 'flex size-12 items-center justify-center rounded-xl bg-(--brand-soft) text-(--brand)'
                }
            >
                <Icon className="size-6" />
            </span>
            <div className="flex-1">
                <h2 className="text-xl font-semibold tracking-tight">
                    {title}
                </h2>
                <p className="mt-1.5 text-sm text-zinc-500">{description}</p>
            </div>
            <span
                className={
                    primary
                        ? 'inline-flex w-fit items-center gap-2 rounded-lg bg-(--brand) px-4 py-2 text-sm font-semibold text-(--brand-fg)'
                        : 'inline-flex w-fit items-center gap-2 rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold'
                }
            >
                {cta}
                <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
            </span>
        </Link>
    );
}

function Step({
    icon: Icon,
    title,
    text,
}: {
    icon: typeof Search;
    title: string;
    text: string;
}) {
    return (
        <li className="flex gap-3">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-(--brand) shadow-sm">
                <Icon className="size-5" />
            </span>
            <div>
                <p className="font-semibold">{title}</p>
                <p className="mt-1 text-sm text-zinc-600">{text}</p>
            </div>
        </li>
    );
}

PortalHome.layout = { active: 'home' };
