import { Head, usePage } from '@inertiajs/react';
import {
    Bug,
    Camera,
    Check,
    Copy,
    ExternalLink,
    FileText,
    Hash,
    HelpCircle,
    Lightbulb,
    LifeBuoy,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/pqrs/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import admin from '@/routes/tenant/admin';

type Props = {
    helpDeskUrl: string;
    company: string;
    portalUrl: string | null;
};

const ROLE_LABEL: Record<string, string> = {
    admin: 'Administrador de empresa',
    agente: 'Agente',
};

const WHEN = [
    { icon: Bug, text: 'Algo no funciona o aparece un error.' },
    {
        icon: HelpCircle,
        text: 'Tienes dudas sobre cómo hacer algo en la aplicación.',
    },
    {
        icon: Lightbulb,
        text: 'Tienes una idea para mejorar la aplicación.',
    },
];

const INCLUDE = [
    {
        icon: UserRound,
        text: 'Tu empresa y tu usuario (abajo, listos para copiar).',
    },
    { icon: Hash, text: 'El número de radicado, si es sobre una PQRS.' },
    { icon: FileText, text: 'Qué estabas haciendo y qué pasó.' },
    { icon: Camera, text: 'Una captura de pantalla, si aparece un error.' },
];

export default function HelpDesk({ helpDeskUrl, company, portalUrl }: Props) {
    const { auth } = usePage().props;
    const [copied, setCopied] = useState(false);
    const host = helpDeskUrl.replace(/^https?:\/\//, '').replace(/\/$/, '');
    const isAgent = auth.user.role === 'agente';

    const details = [
        `Empresa: ${company}`,
        `Usuario: ${auth.user.name} (${auth.user.email})`,
        `Rol: ${ROLE_LABEL[auth.user.role ?? ''] ?? auth.user.role ?? '—'}`,
        portalUrl ? `Portal: ${portalUrl}` : null,
    ].filter((line): line is string => line !== null);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(details.join('\n'));
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2500);
        } catch {
            // Sin permiso para el portapapeles: el texto sigue a la vista
            // para copiarlo a mano.
        }
    };

    return (
        <>
            <Head title="Mesa de ayuda" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Mesa de ayuda"
                    description="Soporte de la plataforma de PQRS."
                />

                <Card className="overflow-hidden">
                    <CardContent className="flex flex-col items-start gap-6 p-6 md:flex-row md:items-center md:p-8">
                        <span className="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <LifeBuoy className="size-8" />
                        </span>
                        <div className="flex-1 space-y-2">
                            <h2 className="text-xl font-bold tracking-tight md:text-2xl">
                                ¿Necesitas ayuda con la aplicación o quieres
                                proponer una mejora?
                            </h2>
                            <p className="text-muted-foreground">
                                Solicita tu ticket en la mesa de ayuda de TIC.
                                El equipo de soporte lo revisa y te responde por
                                ese mismo medio.
                            </p>
                        </div>
                        <div className="flex w-full flex-col items-stretch gap-1.5 md:w-auto md:items-end">
                            <Button size="lg" asChild>
                                <a
                                    href={helpDeskUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Solicitar ticket
                                    <ExternalLink />
                                </a>
                            </Button>
                            <p className="text-center text-xs text-muted-foreground md:text-right">
                                Se abre en una pestaña nueva: {host}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>¿Cuándo abrir un ticket?</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <ul className="space-y-3">
                                {WHEN.map(({ icon: Icon, text }) => (
                                    <li
                                        key={text}
                                        className="flex items-start gap-3 text-sm"
                                    >
                                        <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        {text}
                                    </li>
                                ))}
                            </ul>
                            {isAgent && (
                                <p className="rounded-lg border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                                    Para crear usuarios o cambiar tus sedes,
                                    habla con el administrador de tu empresa: se
                                    hace desde el panel, sin ticket.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Para que te ayuden más rápido</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <ul className="space-y-3">
                                {INCLUDE.map(({ icon: Icon, text }) => (
                                    <li
                                        key={text}
                                        className="flex items-start gap-3 text-sm"
                                    >
                                        <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        {text}
                                    </li>
                                ))}
                            </ul>
                            <div className="rounded-lg border bg-muted/40 p-3">
                                <pre className="font-sans text-sm whitespace-pre-wrap">
                                    {details.join('\n')}
                                </pre>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3"
                                    onClick={copy}
                                >
                                    {copied ? <Check /> : <Copy />}
                                    {copied ? '¡Copiado!' : 'Copiar datos'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

HelpDesk.layout = {
    breadcrumbs: [
        { title: 'Resumen', href: admin.dashboard() },
        { title: 'Mesa de ayuda', href: admin.help() },
    ],
};
