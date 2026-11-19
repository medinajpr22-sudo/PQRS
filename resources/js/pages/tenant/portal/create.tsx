import { Form, Head } from '@inertiajs/react';
import { FileText, Info, Paperclip, Send, X } from 'lucide-react';
import { useRef, useState } from 'react';
import PqrsSubmissionController from '@/actions/App/Http/Controllers/Tenant/PqrsSubmissionController';
import InputError from '@/components/input-error';
import { DynamicField, Required } from '@/components/portal/dynamic-field';
import { NotARobot } from '@/components/portal/not-a-robot';
import { PortalCard } from '@/components/portal/portal-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatFileSize } from '@/lib/format';
import type { FormSchema, Option, RiskLevelOption } from '@/types';

type Props = {
    schema: FormSchema;
    documentTypes: Option[];
    patientDocumentTypes: Option[];
    healthRegimes: Option[];
    attachmentRules: { max_kb: number; mimes: string[] };
    initialType: string | null;
    sedes: { value: number; label: string }[];
    riskLevels: RiskLevelOption[];
    initialRisk: string | null;
    captcha: boolean;
};

export default function CreatePqrs({
    schema,
    documentTypes,
    patientDocumentTypes,
    healthRegimes,
    attachmentRules,
    initialType,
    sedes,
    riskLevels,
    initialRisk,
    captcha,
}: Props) {
    const [files, setFiles] = useState<File[]>([]);
    const fileInput = useRef<HTMLInputElement>(null);
    const defaultType =
        schema.types.find((t) => t.key === initialType)?.key ??
        (schema.types.length === 1 ? schema.types[0].key : undefined);
    const [selectedType, setSelectedType] = useState(defaultType);
    const asksRisk = riskLevels.length > 0 && selectedType === 'reclamo';
    // '1': el solicitante es el afectado; '0': lo radica por otra persona.
    const [isAffected, setIsAffected] = useState<string | null>(null);
    const today = new Date().toLocaleDateString('en-CA');

    const syncFiles = (next: File[]) => {
        setFiles(next);

        if (fileInput.current) {
            const transfer = new DataTransfer();
            next.forEach((f) => transfer.items.add(f));
            fileInput.current.files = transfer.files;
        }
    };

    const fieldError = (errors: Record<string, string>, key: string) =>
        errors[`fields.${key}`];

    const attachmentError = (errors: Record<string, string>) =>
        errors.attachments ??
        Object.entries(errors).find(([k]) => k.startsWith('attachments.'))?.[1];

    let step = 2;
    const patientStep = schema.health_data ? ++step : undefined;
    const fieldsStep = schema.fields.length > 0 ? ++step : undefined;
    const detailStep = ++step;

    return (
        <>
            <Head title="Radicar PQRS" />

            <Form
                {...PqrsSubmissionController.store.form()}
                className="space-y-5"
                options={{ preserveScroll: false }}
                onError={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
            >
                {({ processing, errors }) => (
                    <>
                        {Object.keys(errors).length > 0 && (
                            <div
                                key={Object.keys(errors).join()}
                                className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 motion-safe:animate-in motion-safe:duration-300 motion-safe:fade-in motion-safe:slide-in-from-top-2"
                            >
                                <p className="font-semibold">
                                    Revisa la información del formulario
                                </p>
                                <p>
                                    Hay {Object.keys(errors).length} campo(s)
                                    con errores marcados en rojo.
                                </p>
                            </div>
                        )}

                        <PortalCard
                            step={1}
                            title="Tipo de solicitud"
                            description="Selecciona la opción que mejor describe tu caso."
                        >
                            <div
                                role="radiogroup"
                                className="grid gap-3 sm:grid-cols-2"
                            >
                                {schema.types.map((type) => (
                                    <label
                                        key={type.key}
                                        className="flex cursor-pointer gap-3 rounded-xl border border-zinc-200 p-4 transition-all duration-200 hover:bg-zinc-50 active:scale-[0.99] has-checked:border-(--brand) has-checked:bg-(--brand-soft) has-checked:ring-1 has-checked:ring-(--brand)"
                                    >
                                        <input
                                            type="radio"
                                            name="type"
                                            value={type.key}
                                            defaultChecked={
                                                type.key === defaultType
                                            }
                                            onChange={() =>
                                                setSelectedType(type.key)
                                            }
                                            required
                                            className="mt-1 accent-(--brand)"
                                        />
                                        <span>
                                            <span className="block font-medium">
                                                {type.label}
                                            </span>
                                            <span className="block text-xs text-zinc-500">
                                                {type.description}
                                            </span>
                                            <span className="mt-1 block text-xs font-medium text-zinc-600">
                                                Respuesta: hasta {type.sla_days}{' '}
                                                días hábiles
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <InputError
                                message={errors.type}
                                className="mt-2"
                            />
                            {asksRisk && (
                                <fieldset className="portal-expand mt-6">
                                    <legend className="font-bold">
                                        Nivel de riesgo del reclamo
                                    </legend>
                                    <p className="text-sm text-zinc-500">
                                        Si su caso es de riesgo vital, elija ese
                                        nivel para que sea atendido con
                                        prioridad.
                                    </p>
                                    <div className="mt-3 grid gap-3 md:grid-cols-3">
                                        {riskLevels.map((level) => (
                                            <label
                                                key={level.value}
                                                className="flex cursor-pointer gap-3 rounded-xl border border-zinc-200 p-4 transition-all duration-200 hover:bg-zinc-50 active:scale-[0.99] has-checked:border-(--brand) has-checked:bg-(--brand-soft) has-checked:ring-1 has-checked:ring-(--brand)"
                                            >
                                                <input
                                                    type="radio"
                                                    name="risk_level"
                                                    value={level.value}
                                                    defaultChecked={
                                                        level.value ===
                                                        initialRisk
                                                    }
                                                    required
                                                    className="mt-1 accent-(--brand)"
                                                />
                                                <span>
                                                    <span className="block text-sm font-bold">
                                                        {level.label}
                                                    </span>
                                                    <span className="block text-sm font-medium">
                                                        {level.title}
                                                    </span>
                                                    <span className="mt-1 block text-xs text-zinc-500">
                                                        {level.description}
                                                    </span>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                    <InputError
                                        message={errors.risk_level}
                                        className="mt-2"
                                    />
                                </fieldset>
                            )}
                            {sedes.length > 0 && (
                                <div className="mt-5 grid gap-2 sm:max-w-sm">
                                    <Label htmlFor="sede_id">
                                        Sede a la que diriges tu solicitud
                                    </Label>
                                    <NativeSelect
                                        id="sede_id"
                                        name="sede_id"
                                        required
                                        defaultValue={
                                            sedes.length === 1
                                                ? String(sedes[0].value)
                                                : ''
                                        }
                                        aria-invalid={!!errors.sede_id}
                                    >
                                        <option value="" disabled>
                                            Selecciona una sede
                                        </option>
                                        {sedes.map((s) => (
                                            <option
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <InputError message={errors.sede_id} />
                                </div>
                            )}
                        </PortalCard>

                        <PortalCard
                            step={2}
                            title="Datos del solicitante"
                            description="Te enviaremos el radicado y la respuesta a tu correo."
                        >
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="requester_name">
                                        Nombre completo o razón social
                                        <Required />
                                    </Label>
                                    <Input
                                        id="requester_name"
                                        name="requester_name"
                                        autoComplete="name"
                                        required
                                        aria-invalid={!!errors.requester_name}
                                    />
                                    <InputError
                                        message={errors.requester_name}
                                    />
                                </div>

                                {schema.require_document && (
                                    <>
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="requester_document_type">
                                                Tipo de documento
                                                <Required />
                                            </Label>
                                            <NativeSelect
                                                id="requester_document_type"
                                                name="requester_document_type"
                                                defaultValue="CC"
                                                required
                                            >
                                                {documentTypes.map((d) => (
                                                    <option
                                                        key={d.value}
                                                        value={d.value}
                                                    >
                                                        {d.label}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                            <InputError
                                                message={
                                                    errors.requester_document_type
                                                }
                                            />
                                        </div>
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="requester_document">
                                                Número de documento
                                                <Required />
                                            </Label>
                                            <Input
                                                id="requester_document"
                                                name="requester_document"
                                                inputMode="numeric"
                                                required
                                                aria-invalid={
                                                    !!errors.requester_document
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors.requester_document
                                                }
                                            />
                                        </div>
                                    </>
                                )}

                                <div className="grid content-start gap-2">
                                    <Label htmlFor="requester_email">
                                        Correo electrónico
                                        <Required />
                                    </Label>
                                    <Input
                                        id="requester_email"
                                        name="requester_email"
                                        type="email"
                                        autoComplete="email"
                                        required
                                        aria-invalid={!!errors.requester_email}
                                    />
                                    <InputError
                                        message={errors.requester_email}
                                    />
                                </div>
                                <div className="grid content-start gap-2">
                                    <Label htmlFor="requester_phone">
                                        Teléfono / celular
                                        {schema.require_phone && <Required />}
                                    </Label>
                                    <Input
                                        id="requester_phone"
                                        name="requester_phone"
                                        type="tel"
                                        autoComplete="tel"
                                        required={schema.require_phone}
                                        aria-invalid={!!errors.requester_phone}
                                    />
                                    <InputError
                                        message={errors.requester_phone}
                                    />
                                </div>
                            </div>
                        </PortalCard>

                        {schema.health_data && (
                            <PortalCard
                                step={patientStep}
                                title="Paciente o afectado"
                                description="Si presentas la solicitud por un familiar u otra persona que recibió el servicio, registra aquí sus datos."
                            >
                                <fieldset>
                                    <legend className="text-sm font-medium">
                                        ¿Quién recibió el servicio?
                                        <Required />
                                    </legend>
                                    <div
                                        role="radiogroup"
                                        className="mt-3 grid gap-3 sm:grid-cols-2"
                                    >
                                        {[
                                            {
                                                value: '1',
                                                title: 'Yo',
                                                text: 'Soy el paciente o la persona afectada.',
                                            },
                                            {
                                                value: '0',
                                                title: 'Otra persona',
                                                text: 'Un familiar, un menor de edad o alguien a mi cargo.',
                                            },
                                        ].map((option) => (
                                            <label
                                                key={option.value}
                                                className="flex cursor-pointer gap-3 rounded-xl border border-zinc-200 p-4 transition-all duration-200 hover:bg-zinc-50 active:scale-[0.99] has-checked:border-(--brand) has-checked:bg-(--brand-soft) has-checked:ring-1 has-checked:ring-(--brand)"
                                            >
                                                <input
                                                    type="radio"
                                                    name="requester_is_affected"
                                                    value={option.value}
                                                    onChange={() =>
                                                        setIsAffected(
                                                            option.value,
                                                        )
                                                    }
                                                    required
                                                    className="mt-1 accent-(--brand)"
                                                />
                                                <span>
                                                    <span className="block font-medium">
                                                        {option.title}
                                                    </span>
                                                    <span className="block text-xs text-zinc-500">
                                                        {option.text}
                                                    </span>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                    <InputError
                                        message={errors.requester_is_affected}
                                        className="mt-2"
                                    />
                                </fieldset>

                                {isAffected === '0' && (
                                    <div className="portal-expand mt-5 grid gap-5 sm:grid-cols-2">
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label htmlFor="affected_name">
                                                Nombre completo del paciente
                                                <Required />
                                            </Label>
                                            <Input
                                                id="affected_name"
                                                name="affected_name"
                                                required
                                                placeholder="Ej: Carlos Pérez"
                                                aria-invalid={
                                                    !!errors.affected_name
                                                }
                                            />
                                            <InputError
                                                message={errors.affected_name}
                                            />
                                        </div>
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="affected_document_type">
                                                Tipo de documento
                                                <Required />
                                            </Label>
                                            <NativeSelect
                                                id="affected_document_type"
                                                name="affected_document_type"
                                                defaultValue="CC"
                                                required
                                            >
                                                {patientDocumentTypes.map(
                                                    (d) => (
                                                        <option
                                                            key={d.value}
                                                            value={d.value}
                                                        >
                                                            {d.label}
                                                        </option>
                                                    ),
                                                )}
                                            </NativeSelect>
                                            <InputError
                                                message={
                                                    errors.affected_document_type
                                                }
                                            />
                                        </div>
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="affected_document">
                                                Número de documento
                                                <Required />
                                            </Label>
                                            <Input
                                                id="affected_document"
                                                name="affected_document"
                                                inputMode="numeric"
                                                required
                                                aria-invalid={
                                                    !!errors.affected_document
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors.affected_document
                                                }
                                            />
                                        </div>
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="affected_phone">
                                                Teléfono del paciente
                                            </Label>
                                            <Input
                                                id="affected_phone"
                                                name="affected_phone"
                                                type="tel"
                                                aria-invalid={
                                                    !!errors.affected_phone
                                                }
                                            />
                                            <InputError
                                                message={errors.affected_phone}
                                            />
                                        </div>
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="affected_email">
                                                Correo del paciente
                                            </Label>
                                            <Input
                                                id="affected_email"
                                                name="affected_email"
                                                type="email"
                                                aria-invalid={
                                                    !!errors.affected_email
                                                }
                                            />
                                            <InputError
                                                message={errors.affected_email}
                                            />
                                        </div>
                                        <div className="grid content-start gap-2 sm:col-span-2">
                                            <Label htmlFor="requester_relationship">
                                                Tu parentesco con el paciente
                                            </Label>
                                            <Input
                                                id="requester_relationship"
                                                name="requester_relationship"
                                                maxLength={60}
                                                placeholder="Ej: Hijo(a), madre, tutor"
                                                className="sm:max-w-sm"
                                                aria-invalid={
                                                    !!errors.requester_relationship
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors.requester_relationship
                                                }
                                            />
                                        </div>
                                    </div>
                                )}

                                <div className="mt-5 grid gap-5 border-t pt-5 sm:grid-cols-2">
                                    <div className="grid content-start gap-2">
                                        <Label htmlFor="eps">
                                            EPS del paciente
                                        </Label>
                                        <Input
                                            id="eps"
                                            name="eps"
                                            maxLength={120}
                                            placeholder="Ej: Nueva EPS"
                                            aria-invalid={!!errors.eps}
                                        />
                                        <InputError message={errors.eps} />
                                    </div>
                                    <div className="grid content-start gap-2">
                                        <Label htmlFor="health_regime">
                                            Régimen
                                        </Label>
                                        <NativeSelect
                                            id="health_regime"
                                            name="health_regime"
                                            defaultValue=""
                                            aria-invalid={
                                                !!errors.health_regime
                                            }
                                        >
                                            <option value="">
                                                Selecciona el régimen
                                            </option>
                                            {healthRegimes.map((r) => (
                                                <option
                                                    key={r.value}
                                                    value={r.value}
                                                >
                                                    {r.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                        <InputError
                                            message={errors.health_regime}
                                        />
                                    </div>
                                </div>
                            </PortalCard>
                        )}

                        {schema.fields.length > 0 && (
                            <PortalCard
                                step={fieldsStep}
                                title="Información adicional"
                                description="Estos datos nos ayudan a gestionar tu caso más rápido."
                            >
                                <div className="grid gap-5 sm:grid-cols-2">
                                    {schema.fields.map((field) => (
                                        <DynamicField
                                            key={field.key}
                                            field={field}
                                            error={fieldError(
                                                errors,
                                                field.key,
                                            )}
                                        />
                                    ))}
                                </div>
                            </PortalCard>
                        )}

                        <PortalCard
                            step={detailStep}
                            title="Detalle de la solicitud"
                            description="Describe de forma clara y completa los hechos."
                        >
                            <div className="grid gap-5">
                                {(schema.services.length > 0 ||
                                    schema.health_data) && (
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        {schema.services.length > 0 && (
                                            <div className="grid content-start gap-2">
                                                <Label htmlFor="service">
                                                    Servicio recibido
                                                </Label>
                                                <NativeSelect
                                                    id="service"
                                                    name="service"
                                                    defaultValue=""
                                                    aria-invalid={
                                                        !!errors.service
                                                    }
                                                >
                                                    <option value="">
                                                        Selecciona un servicio
                                                    </option>
                                                    {schema.services.map(
                                                        (service) => (
                                                            <option
                                                                key={service}
                                                                value={service}
                                                            >
                                                                {service}
                                                            </option>
                                                        ),
                                                    )}
                                                </NativeSelect>
                                                <InputError
                                                    message={errors.service}
                                                />
                                            </div>
                                        )}
                                        {schema.health_data && (
                                            <div className="grid content-start gap-2">
                                                <Label htmlFor="attended_on">
                                                    Fecha de atención
                                                </Label>
                                                <Input
                                                    id="attended_on"
                                                    name="attended_on"
                                                    type="date"
                                                    max={today}
                                                    aria-invalid={
                                                        !!errors.attended_on
                                                    }
                                                />
                                                <InputError
                                                    message={errors.attended_on}
                                                />
                                            </div>
                                        )}
                                    </div>
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="subject">
                                        Asunto
                                        <Required />
                                    </Label>
                                    <Input
                                        id="subject"
                                        name="subject"
                                        maxLength={200}
                                        required
                                        placeholder="Resume tu solicitud en una frase"
                                        aria-invalid={!!errors.subject}
                                    />
                                    <InputError message={errors.subject} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Descripción
                                        <Required />
                                    </Label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        rows={6}
                                        minLength={20}
                                        maxLength={5000}
                                        required
                                        placeholder="¿Qué ocurrió? ¿Cuándo y dónde? ¿Qué esperas que hagamos?"
                                        aria-invalid={!!errors.description}
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                {schema.allow_attachments && (
                                    <div className="grid gap-2">
                                        <Label>
                                            Adjuntos{' '}
                                            <span className="font-normal text-zinc-500">
                                                (opcional, máximo{' '}
                                                {schema.max_attachments})
                                            </span>
                                        </Label>
                                        <input
                                            ref={fileInput}
                                            type="file"
                                            name="attachments[]"
                                            multiple
                                            className="sr-only"
                                            id="attachments"
                                            accept={attachmentRules.mimes
                                                .map((m) => `.${m}`)
                                                .join(',')}
                                            onChange={(e) =>
                                                syncFiles(
                                                    [
                                                        ...files,
                                                        ...Array.from(
                                                            e.target.files ??
                                                                [],
                                                        ),
                                                    ].slice(
                                                        0,
                                                        schema.max_attachments,
                                                    ),
                                                )
                                            }
                                        />
                                        <label
                                            htmlFor="attachments"
                                            className="group flex cursor-pointer flex-col items-center gap-1 rounded-xl border-2 border-dashed border-zinc-300 px-4 py-6 text-center text-sm transition-colors hover:border-(--brand) hover:bg-(--brand-soft)"
                                        >
                                            <Paperclip className="size-5 text-zinc-400 transition-transform duration-300 group-hover:-rotate-12 group-hover:text-(--brand)" />
                                            <span className="font-medium">
                                                Haz clic para adjuntar archivos
                                            </span>
                                            <span className="text-xs text-zinc-500">
                                                {attachmentRules.mimes
                                                    .join(', ')
                                                    .toUpperCase()}{' '}
                                                · hasta{' '}
                                                {Math.round(
                                                    attachmentRules.max_kb /
                                                        1024,
                                                )}{' '}
                                                MB cada uno
                                            </span>
                                        </label>
                                        {files.length > 0 && (
                                            <ul className="divide-y rounded-lg border">
                                                {files.map((file, i) => (
                                                    <li
                                                        key={`${file.name}-${i}`}
                                                        className="portal-expand flex items-center gap-3 px-3 py-2 text-sm"
                                                    >
                                                        <FileText className="size-4 text-zinc-400" />
                                                        <span className="flex-1 truncate">
                                                            {file.name}
                                                        </span>
                                                        <span className="text-xs text-zinc-500">
                                                            {formatFileSize(
                                                                file.size,
                                                            )}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                syncFiles(
                                                                    files.filter(
                                                                        (
                                                                            _,
                                                                            j,
                                                                        ) =>
                                                                            j !==
                                                                            i,
                                                                    ),
                                                                )
                                                            }
                                                            className="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700"
                                                            aria-label={`Quitar ${file.name}`}
                                                        >
                                                            <X className="size-4" />
                                                        </button>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        <InputError
                                            message={attachmentError(errors)}
                                        />
                                    </div>
                                )}
                            </div>
                        </PortalCard>

                        <PortalCard>
                            <label
                                htmlFor="privacy"
                                className="flex cursor-pointer items-start gap-3 text-sm"
                            >
                                <Checkbox
                                    id="privacy"
                                    name="privacy"
                                    value="1"
                                    className="mt-0.5"
                                />
                                <span className="text-zinc-700">
                                    {schema.privacy_text}
                                    {schema.privacy_policy_url && (
                                        <>
                                            {' '}
                                            <a
                                                href={schema.privacy_policy_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="font-medium text-(--brand) underline"
                                            >
                                                Ver política
                                            </a>
                                        </>
                                    )}
                                    <Required />
                                </span>
                            </label>
                            <InputError
                                message={errors.privacy}
                                className="mt-2"
                            />

                            {schema.health_data && (
                                <p className="mt-5 flex gap-2 rounded-lg bg-zinc-50 p-3 text-xs text-zinc-600">
                                    <Info className="mt-px size-4 shrink-0 text-zinc-400" />
                                    <span>
                                        Si no obtienes respuesta de esta
                                        entidad, puedes presentar tu PQRD ante
                                        la Superintendencia Nacional de Salud
                                        por cualquiera de sus canales:{' '}
                                        <a
                                            href="https://www.supersalud.gov.co"
                                            target="_blank"
                                            rel="noreferrer"
                                            className="font-medium text-(--brand) underline"
                                        >
                                            www.supersalud.gov.co
                                        </a>
                                        .
                                    </span>
                                </p>
                            )}

                            {/* Campo trampa: invisible para las personas; los robots lo llenan. */}
                            <div
                                aria-hidden="true"
                                className="pointer-events-none absolute -left-[10000px] size-px overflow-hidden"
                            >
                                <label htmlFor="website">
                                    No llenes este campo
                                </label>
                                <input
                                    id="website"
                                    name="website"
                                    type="text"
                                    tabIndex={-1}
                                    autoComplete="off"
                                />
                            </div>

                            {captcha && (
                                <div className="mt-5">
                                    <NotARobot error={errors.altcha} />
                                </div>
                            )}

                            <div className="mt-6 flex flex-col-reverse items-center justify-between gap-3 border-t pt-5 sm:flex-row">
                                <p className="text-xs text-zinc-500">
                                    Los campos marcados con{' '}
                                    <span className="text-red-600">*</span> son
                                    obligatorios.
                                </p>
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={processing}
                                    className="group w-full sm:w-auto"
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <Send className="transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                    )}
                                    Radicar solicitud
                                </Button>
                            </div>
                        </PortalCard>
                    </>
                )}
            </Form>
        </>
    );
}

CreatePqrs.layout = {
    active: 'create',
    title: 'Radicar una PQRS',
    description:
        'Completa el formulario. Al finalizar recibirás tu número de radicado.',
};
