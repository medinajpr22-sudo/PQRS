import { Form, Head } from '@inertiajs/react';
import { FileText, Paperclip, Send, X } from 'lucide-react';
import { useRef, useState } from 'react';
import PqrsSubmissionController from '@/actions/App/Http/Controllers/Tenant/PqrsSubmissionController';
import InputError from '@/components/input-error';
import { DynamicField, Required } from '@/components/portal/dynamic-field';
import { PortalCard } from '@/components/portal/portal-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatFileSize } from '@/lib/format';
import type { FormSchema, Option } from '@/types';

type Props = {
    schema: FormSchema;
    documentTypes: Option[];
    attachmentRules: { max_kb: number; mimes: string[] };
    initialType: string | null;
    sedes: { value: number; label: string }[];
};

export default function CreatePqrs({
    schema,
    documentTypes,
    attachmentRules,
    initialType,
    sedes,
}: Props) {
    const [files, setFiles] = useState<File[]>([]);
    const fileInput = useRef<HTMLInputElement>(null);
    const defaultType =
        schema.types.find((t) => t.key === initialType)?.key ??
        (schema.types.length === 1 ? schema.types[0].key : undefined);

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

    const detailStep = schema.fields.length > 0 ? 4 : 3;

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
                            <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
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
                                        className="flex cursor-pointer gap-3 rounded-xl border border-zinc-200 p-4 transition-colors hover:bg-zinc-50 has-checked:border-(--brand) has-checked:bg-(--brand-soft) has-checked:ring-1 has-checked:ring-(--brand)"
                                    >
                                        <input
                                            type="radio"
                                            name="type"
                                            value={type.key}
                                            defaultChecked={
                                                type.key === defaultType
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

                        {schema.fields.length > 0 && (
                            <PortalCard
                                step={3}
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
                                            className="flex cursor-pointer flex-col items-center gap-1 rounded-xl border-2 border-dashed border-zinc-300 px-4 py-6 text-center text-sm transition-colors hover:border-(--brand) hover:bg-(--brand-soft)"
                                        >
                                            <Paperclip className="size-5 text-zinc-400" />
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
                                                        className="flex items-center gap-3 px-3 py-2 text-sm"
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
                                    className="w-full sm:w-auto"
                                >
                                    {processing ? <Spinner /> : <Send />}
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
