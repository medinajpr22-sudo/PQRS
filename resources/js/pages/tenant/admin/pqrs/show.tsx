import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    FileText,
    Lock,
    Mail,
    MessageSquareReply,
    Paperclip,
    Phone,
    RefreshCw,
    Send,
    StickyNote,
    UserCheck,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import PqrsActivityController from '@/actions/App/Http/Controllers/Tenant/Admin/PqrsActivityController';
import InputError from '@/components/input-error';
import { DueDate } from '@/components/pqrs/due-date';
import { OverdueBadge, StatusBadge } from '@/components/pqrs/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime, formatFileSize } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/tenant/admin';
import type {
    AttachmentItem,
    PqrsActivityItem,
    PqrsListItem,
    StaffOption,
    StatusTransition,
} from '@/types';

type Props = {
    pqrs: PqrsListItem & {
        channel: string;
        requester_document_type: string | null;
        requester_document_type_label: string | null;
        requester_document: string | null;
        requester_phone: string | null;
        description: string;
        extra_fields: {
            key: string;
            label: string;
            type: string;
            value: unknown;
        }[];
        responded_at: string | null;
        closed_at: string | null;
        sla_days: number | null;
        attachments: AttachmentItem[];
        activities: PqrsActivityItem[];
    };
    transitions: StatusTransition[];
    staff: StaffOption[];
    canAssignOthers: boolean;
};

const ACTIVITY: Record<
    PqrsActivityItem['type'],
    { label: string; icon: typeof FileText; className: string }
> = {
    created: {
        label: 'Radicación',
        icon: FileText,
        className: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    },
    status_changed: {
        label: 'Cambio de estado',
        icon: RefreshCw,
        className: 'bg-muted text-muted-foreground',
    },
    note: {
        label: 'Nota interna',
        icon: StickyNote,
        className:
            'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    },
    response: {
        label: 'Respuesta al ciudadano',
        icon: MessageSquareReply,
        className:
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    },
    assigned: {
        label: 'Asignación',
        icon: UserCheck,
        className:
            'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
    },
};

export default function PqrsShow({
    pqrs,
    transitions,
    staff,
    canAssignOthers,
}: Props) {
    const { auth, errors } = usePage().props;
    // Si el responsable actual fue desactivado, igual debe aparecer en la lista.
    const staffOptions: StaffOption[] =
        pqrs.assignee && !staff.some((s) => s.value === pqrs.assignee?.id)
            ? [
                  ...staff,
                  {
                      value: pqrs.assignee.id,
                      label: `${pqrs.assignee.name} (inactivo)`,
                  },
              ]
            : staff;
    const [selectedMode, setMode] = useState<'response' | 'note'>('response');
    const [pending, setPending] = useState<StatusTransition | null>(null);
    const [reason, setReason] = useState('');
    const [saving, setSaving] = useState(false);
    const isOpen = pqrs.status === 'radicada' || pqrs.status === 'en_tramite';
    const isClosed = pqrs.status === 'cerrada';
    // Una PQRS cerrada solo admite notas internas: hay que reabrirla para
    // responder. Se deriva del estado actual para que el formulario cambie
    // apenas se cierra la solicitud, sin recargar la página.
    const mode = isClosed ? 'note' : selectedMode;

    const changeStatus = (status: string, withReason?: string) => {
        setSaving(true);
        router.patch(
            admin.pqrs.status.url(pqrs.radicado),
            { status, reason: withReason ?? null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPending(null);
                    setReason('');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const onSelectStatus = (value: string) => {
        const transition = transitions.find((t) => t.value === value);

        if (!transition) {
            return;
        }

        if (transition.requires_reason) {
            setPending(transition);
        } else {
            changeStatus(transition.value);
        }
    };

    const assign = (userId: number | null) =>
        router.patch(
            admin.pqrs.assign.url(pqrs.radicado),
            { assigned_to: userId },
            { preserveScroll: true },
        );

    const attachmentUrl = (id: number) =>
        admin.pqrs.attachments.show({ pqrs: pqrs.radicado, attachment: id })
            .url;

    return (
        <>
            <Head title={pqrs.radicado} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <Link
                            href={admin.pqrs.index()}
                            className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" />
                            Solicitudes
                        </Link>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-semibold tracking-tight">
                                {pqrs.radicado}
                            </h1>
                            <StatusBadge
                                status={pqrs.status}
                                label={pqrs.status_label}
                            />
                            {pqrs.is_overdue && <OverdueBadge />}
                        </div>
                        <p className="text-muted-foreground">
                            {pqrs.type_label} · {pqrs.subject}
                        </p>
                    </div>

                    <div className="flex flex-col items-start gap-1 lg:items-end">
                        <NativeSelect
                            aria-label="Cambiar estado"
                            value=""
                            disabled={saving || transitions.length === 0}
                            onChange={(e) => onSelectStatus(e.target.value)}
                            className="w-56"
                        >
                            <option value="" disabled>
                                Cambiar estado…
                            </option>
                            {transitions.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {isClosed && t.value === 'en_tramite'
                                        ? 'Reabrir (En trámite)'
                                        : t.label}
                                    {t.requires_reason ? ' · pide motivo' : ''}
                                </option>
                            ))}
                        </NativeSelect>
                        {!pending && <InputError message={errors.status} />}
                        {pqrs.status !== 'respondida' && !isClosed && (
                            <p className="text-xs text-muted-foreground">
                                Pasa a «Respondida» al enviar una respuesta.
                            </p>
                        )}
                    </div>
                </div>

                <Dialog
                    open={pending !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setPending(null);
                            setReason('');
                        }
                    }}
                >
                    <DialogContent>
                        <DialogTitle>
                            {isOpen
                                ? 'Cerrar sin respuesta'
                                : 'Reabrir la solicitud'}
                        </DialogTitle>
                        <DialogDescription>
                            El motivo queda en el historial y el ciudadano lo
                            verá en la consulta de su radicado.
                        </DialogDescription>
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();

                                if (pending) {
                                    changeStatus(pending.value, reason);
                                }
                            }}
                        >
                            <Textarea
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                rows={4}
                                required
                                minLength={5}
                                placeholder={
                                    isOpen
                                        ? 'Ej.: Solicitud duplicada del radicado …'
                                        : 'Ej.: El ciudadano indica que la respuesta no resolvió su caso.'
                                }
                                aria-invalid={!!errors.reason}
                            />
                            <InputError
                                message={errors.reason ?? errors.status}
                            />
                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => setPending(null)}
                                >
                                    Cancelar
                                </Button>
                                <Button type="submit" disabled={saving}>
                                    {saving && <Spinner />}
                                    Confirmar
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Descripción de la solicitud
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm leading-relaxed whitespace-pre-line">
                                    {pqrs.description}
                                </p>
                                {pqrs.attachments.length > 0 && (
                                    <div>
                                        <p className="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground uppercase">
                                            <Paperclip className="size-3.5" />
                                            Adjuntos del solicitante
                                        </p>
                                        <AttachmentList
                                            files={pqrs.attachments}
                                            urlFor={attachmentUrl}
                                        />
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <CardTitle>Gestionar</CardTitle>
                                    <div className="inline-flex rounded-lg bg-muted p-1 text-sm">
                                        {(['response', 'note'] as const).map(
                                            (m) => (
                                                <button
                                                    key={m}
                                                    type="button"
                                                    onClick={() => setMode(m)}
                                                    disabled={
                                                        m === 'response' &&
                                                        isClosed
                                                    }
                                                    title={
                                                        m === 'response' &&
                                                        isClosed
                                                            ? 'Reabre la solicitud para responder'
                                                            : undefined
                                                    }
                                                    className={cn(
                                                        'flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                                                        mode === m
                                                            ? 'bg-background shadow-sm'
                                                            : 'text-muted-foreground hover:text-foreground',
                                                    )}
                                                >
                                                    {m === 'response' ? (
                                                        <MessageSquareReply className="size-4" />
                                                    ) : (
                                                        <Lock className="size-4" />
                                                    )}
                                                    {m === 'response'
                                                        ? 'Responder al ciudadano'
                                                        : 'Nota interna'}
                                                </button>
                                            ),
                                        )}
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...PqrsActivityController.store.form(
                                        pqrs.radicado,
                                    )}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="space-y-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="type"
                                                value={mode}
                                            />
                                            <Textarea
                                                name="body"
                                                rows={6}
                                                required
                                                placeholder={
                                                    mode === 'response'
                                                        ? `Cordial saludo, ${pqrs.requester_name}…`
                                                        : 'Escribe una nota visible solo para el equipo…'
                                                }
                                                aria-invalid={!!errors.body}
                                            />
                                            <InputError message={errors.body} />
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <input
                                                    type="file"
                                                    name="attachments[]"
                                                    multiple
                                                    className="text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm file:font-medium"
                                                />
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    {processing ? (
                                                        <Spinner />
                                                    ) : mode === 'response' ? (
                                                        <Send />
                                                    ) : (
                                                        <StickyNote />
                                                    )}
                                                    {mode === 'response'
                                                        ? 'Enviar respuesta'
                                                        : 'Guardar nota'}
                                                </Button>
                                            </div>
                                            <InputError
                                                message={
                                                    Object.entries(errors).find(
                                                        ([k]) =>
                                                            k.startsWith(
                                                                'attachments',
                                                            ),
                                                    )?.[1]
                                                }
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                {mode === 'response'
                                                    ? `La respuesta se envía a ${pqrs.requester_email}, queda visible en la consulta pública y la solicitud pasa a «Respondida».`
                                                    : 'Las notas internas no son visibles para el ciudadano.'}
                                            </p>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Historial</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ol className="relative space-y-6 before:absolute before:top-2 before:bottom-2 before:left-4 before:w-px before:bg-border">
                                    {pqrs.activities.map((a) => {
                                        const meta = ACTIVITY[a.type];

                                        return (
                                            <li
                                                key={a.id}
                                                className="relative flex gap-4"
                                            >
                                                <span
                                                    className={cn(
                                                        'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full ring-4 ring-card',
                                                        meta.className,
                                                    )}
                                                >
                                                    <meta.icon className="size-4" />
                                                </span>
                                                <div className="min-w-0 flex-1 pt-1">
                                                    <p className="text-sm">
                                                        <span className="font-medium">
                                                            {meta.label}
                                                        </span>
                                                        {a.user && (
                                                            <span className="text-muted-foreground">
                                                                {' '}
                                                                · {a.user.name}
                                                            </span>
                                                        )}
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            {formatDateTime(
                                                                a.created_at,
                                                            )}
                                                        </span>
                                                    </p>
                                                    {a.body && (
                                                        <p
                                                            className={cn(
                                                                'mt-1 text-sm whitespace-pre-line',
                                                                a.type ===
                                                                    'response' ||
                                                                    a.type ===
                                                                        'note'
                                                                    ? 'rounded-lg border bg-muted/40 p-3'
                                                                    : 'text-muted-foreground',
                                                            )}
                                                        >
                                                            {a.body}
                                                        </p>
                                                    )}
                                                    {a.attachments.length >
                                                        0 && (
                                                        <div className="mt-2">
                                                            <AttachmentList
                                                                files={
                                                                    a.attachments
                                                                }
                                                                urlFor={
                                                                    attachmentUrl
                                                                }
                                                            />
                                                        </div>
                                                    )}
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ol>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Responsable</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                {canAssignOthers ? (
                                    <NativeSelect
                                        aria-label="Asignar responsable"
                                        value={pqrs.assignee?.id ?? ''}
                                        onChange={(e) =>
                                            assign(
                                                e.target.value
                                                    ? Number(e.target.value)
                                                    : null,
                                            )
                                        }
                                    >
                                        <option value="">
                                            Sin responsable
                                        </option>
                                        {staffOptions.map((s) => (
                                            <option
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                                {s.value === auth.user.id
                                                    ? ' (yo)'
                                                    : ''}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                ) : (
                                    <>
                                        <p className="flex items-center gap-2 font-medium">
                                            <UserCheck className="size-4 text-muted-foreground" />
                                            {pqrs.assignee?.name ??
                                                'Sin responsable'}
                                        </p>
                                        {!pqrs.assignee && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    assign(auth.user.id)
                                                }
                                            >
                                                Tomar esta solicitud
                                            </Button>
                                        )}
                                        {pqrs.assignee?.id === auth.user.id && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => assign(null)}
                                            >
                                                Soltar solicitud
                                            </Button>
                                        )}
                                    </>
                                )}
                                <InputError message={errors.assigned_to} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Solicitante</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <p className="flex items-center gap-2 font-medium">
                                    <UserRound className="size-4 text-muted-foreground" />
                                    {pqrs.requester_name}
                                </p>
                                {pqrs.requester_document && (
                                    <p className="text-muted-foreground">
                                        {pqrs.requester_document_type_label ??
                                            pqrs.requester_document_type}{' '}
                                        <span className="text-foreground">
                                            {pqrs.requester_document}
                                        </span>
                                    </p>
                                )}
                                <a
                                    href={`mailto:${pqrs.requester_email}`}
                                    className="flex items-center gap-2 hover:underline"
                                >
                                    <Mail className="size-4 text-muted-foreground" />
                                    {pqrs.requester_email}
                                </a>
                                {pqrs.requester_phone && (
                                    <a
                                        href={`tel:${pqrs.requester_phone}`}
                                        className="flex items-center gap-2 hover:underline"
                                    >
                                        <Phone className="size-4 text-muted-foreground" />
                                        {pqrs.requester_phone}
                                    </a>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Plazos</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-3 text-sm">
                                    <Row
                                        label="Radicada"
                                        value={formatDateTime(pqrs.created_at)}
                                    />
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Vence
                                        </dt>
                                        <dd className="text-right">
                                            <DueDate
                                                dueAt={pqrs.due_at}
                                                open={isOpen}
                                            />
                                        </dd>
                                    </div>
                                    {pqrs.sla_days && (
                                        <Row
                                            label="Plazo"
                                            value={`${pqrs.sla_days} días hábiles`}
                                        />
                                    )}
                                    <Row
                                        label="Respondida"
                                        value={
                                            pqrs.responded_at
                                                ? formatDate(pqrs.responded_at)
                                                : 'Pendiente'
                                        }
                                    />
                                    {pqrs.closed_at && (
                                        <Row
                                            label="Cerrada"
                                            value={formatDate(pqrs.closed_at)}
                                        />
                                    )}
                                    <Row
                                        label="Canal"
                                        value={
                                            pqrs.channel === 'web'
                                                ? 'Portal web'
                                                : pqrs.channel
                                        }
                                    />
                                </dl>
                            </CardContent>
                        </Card>

                        {pqrs.extra_fields.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Información adicional</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <dl className="grid gap-3 text-sm">
                                        {pqrs.extra_fields.map((f) => (
                                            <div key={f.key}>
                                                <dt className="text-xs text-muted-foreground">
                                                    {f.label}
                                                </dt>
                                                <dd className="font-medium">
                                                    {displayValue(
                                                        f.type,
                                                        f.value,
                                                    )}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function displayValue(type: string, value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (type === 'checkbox') {
        return value ? 'Sí' : 'No';
    }

    if (type === 'date' && typeof value === 'string') {
        return formatDate(`${value}T12:00:00`);
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value as string | number | boolean);
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
    );
}

function AttachmentList({
    files,
    urlFor,
}: {
    files: AttachmentItem[];
    urlFor: (id: number) => string;
}) {
    return (
        <ul className="flex flex-wrap gap-2">
            {files.map((f) => (
                <li key={f.id}>
                    <a
                        href={urlFor(f.id)}
                        className="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs transition-colors hover:bg-muted"
                    >
                        <Download className="size-3.5" />
                        <span className="max-w-48 truncate">{f.name}</span>
                        <span className="text-muted-foreground">
                            {formatFileSize(f.size)}
                        </span>
                    </a>
                </li>
            ))}
        </ul>
    );
}

PqrsShow.layout = {
    breadcrumbs: [
        { title: 'Resumen', href: admin.dashboard() },
        { title: 'Solicitudes', href: admin.pqrs.index() },
    ],
};
