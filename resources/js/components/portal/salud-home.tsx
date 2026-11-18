import { Link, router } from '@inertiajs/react';
import {
    FileWarning,
    Hand,
    Heart,
    Lightbulb,
    Megaphone,
    ShieldAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { cn } from '@/lib/utils';
import type { PqrsTypeConfig, RiskLevelOption } from '@/types';

const ICONS: Record<string, LucideIcon> = {
    peticion: Hand,
    queja: FileWarning,
    reclamo: Megaphone,
    sugerencia: Lightbulb,
    felicitacion: Heart,
    denuncia: ShieldAlert,
};

/**
 * Inicio del portal con la plantilla «Salud»: barra para consultar por
 * radicado, tipos de solicitud con su definición y, en los reclamos, los
 * niveles de riesgo de la Supersalud.
 */
export function SaludHome({
    types,
    riskLevels,
}: {
    types: PqrsTypeConfig[];
    riskLevels: RiskLevelOption[];
}) {
    return (
        <div className="space-y-14">
            <TrackingBar />

            <section>
                <h2 className="text-3xl font-bold md:text-4xl">
                    Seleccione el tipo de solicitud
                </h2>
                <p className="mt-2 max-w-2xl text-zinc-600">
                    Lea las definiciones y elija la que mejor describe su caso.
                    {riskLevels.length > 0 &&
                        ' Si tiene dudas entre un reclamo y otro, mida el riesgo.'}
                </p>

                <div className="mt-10 divide-y divide-zinc-200 border-y border-zinc-200">
                    {types.map((type) => (
                        <TypeRow
                            key={type.key}
                            type={type}
                            riskLevels={
                                type.key === 'reclamo' ? riskLevels : []
                            }
                        />
                    ))}
                </div>
            </section>
        </div>
    );
}

/**
 * «¿Ya radicó una solicitud?»: lleva a la consulta con el radicado escrito.
 * El correo se pide allí, para que nadie vea solicitudes ajenas.
 */
function TrackingBar() {
    const [radicado, setRadicado] = useState('');

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            '/consultar',
            radicado.trim() ? { radicado: radicado.trim() } : {},
        );
    };

    return (
        <form
            onSubmit={submit}
            className="relative -mt-20 flex flex-col gap-4 rounded-xl bg-(--brand) p-5 text-(--brand-fg) shadow-lg md:flex-row md:items-center md:gap-6 md:px-6"
        >
            <p className="text-lg leading-snug font-bold md:max-w-sm">
                ¿Ya radicó una solicitud? Consulte su respuesta con el número de
                radicado.
            </p>
            <div className="flex flex-1 flex-col gap-3 sm:flex-row">
                <input
                    name="radicado"
                    value={radicado}
                    onChange={(e) => setRadicado(e.target.value)}
                    placeholder="Número de radicado"
                    aria-label="Número de radicado"
                    className="h-12 w-full rounded-lg border-2 border-black bg-white px-4 font-mono text-zinc-900 uppercase placeholder:font-sans placeholder:text-zinc-500 placeholder:normal-case focus:ring-2 focus:ring-black focus:outline-none sm:flex-1"
                />
                <button
                    type="submit"
                    className="h-12 rounded-full bg-black px-6 text-sm font-bold text-white transition-colors hover:bg-zinc-800"
                >
                    Hacer seguimiento
                </button>
            </div>
        </form>
    );
}

function TypeRow({
    type,
    riskLevels,
}: {
    type: PqrsTypeConfig;
    riskLevels: RiskLevelOption[];
}) {
    const Icon = ICONS[type.key] ?? Hand;
    const article = type.key === 'reclamo' ? 'un' : 'una';

    return (
        <article className="flex gap-5 py-8 sm:gap-8">
            <span className="flex size-14 shrink-0 items-center justify-center rounded-full bg-black text-(--brand)">
                <Icon className="size-6" />
            </span>
            <div className="min-w-0 flex-1">
                <h3 className="text-2xl font-bold">{type.label}</h3>
                {type.description && (
                    <p className="mt-2 max-w-3xl leading-relaxed text-zinc-600">
                        {type.description}
                    </p>
                )}

                {riskLevels.length > 0 && <RiskLevels levels={riskLevels} />}

                <Link
                    href={`/radicar?tipo=${type.key}`}
                    className={cn(
                        'mt-5 inline-flex rounded-full px-6 py-3 text-sm font-bold transition-opacity hover:opacity-85',
                        riskLevels.length > 0
                            ? 'bg-(--brand) text-(--brand-fg)'
                            : 'bg-black text-white',
                    )}
                >
                    Enviar {article} {type.label.toLowerCase()}
                </Link>
            </div>
        </article>
    );
}

/**
 * Los tres niveles de riesgo de un reclamo. Cada tarjeta abre el formulario
 * con ese nivel ya elegido.
 */
function RiskLevels({ levels }: { levels: RiskLevelOption[] }) {
    return (
        <>
            <p className="mt-2 text-zinc-600">
                Se clasifica en tres niveles según el riesgo para la persona:
            </p>
            <div className="mt-5 grid gap-4 md:grid-cols-3">
                {levels.map((level) => {
                    const vital = level.value === 'vital';

                    return (
                        <Link
                            key={level.value}
                            href={`/radicar?tipo=reclamo&riesgo=${level.value}`}
                            className={cn(
                                'flex flex-col rounded-lg border-t-4 p-5 transition-shadow hover:shadow-md',
                                vital
                                    ? 'border-(--brand) bg-black text-white'
                                    : 'border-(--brand) bg-[#f6f3f0] text-zinc-900',
                                level.value === 'priorizado' &&
                                    'border-(--brand-dark)',
                            )}
                        >
                            <span className="w-fit rounded-full bg-(--brand) px-2.5 py-0.5 text-xs font-bold text-(--brand-fg)">
                                {level.label}
                            </span>
                            <h4 className="mt-3 text-lg font-bold">
                                {level.title}
                            </h4>
                            <p
                                className={cn(
                                    'mt-2 text-sm leading-relaxed',
                                    vital ? 'text-white/85' : 'text-zinc-600',
                                )}
                            >
                                {level.description}
                            </p>
                        </Link>
                    );
                })}
            </div>
            <p className="mt-5 rounded-md border-l-4 border-(--brand) bg-[#f6f3f0] px-5 py-3 text-sm">
                Si su caso es de riesgo vital, seleccione ese nivel para que sea
                atendido con prioridad.
            </p>
        </>
    );
}
