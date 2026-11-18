import { useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Calendar,
    CheckSquare,
    CircleDot,
    Hash,
    List,
    Mail,
    Phone,
    Plus,
    Text,
    TextCursorInput,
    Trash2,
} from 'lucide-react';
import TenantFormController from '@/actions/App/Http/Controllers/Central/TenantFormController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { FormField, FormFieldType, FormSchema } from '@/types';

const FIELD_TYPES: {
    value: FormFieldType;
    label: string;
    icon: typeof Text;
}[] = [
    { value: 'text', label: 'Texto corto', icon: TextCursorInput },
    { value: 'textarea', label: 'Texto largo', icon: Text },
    { value: 'select', label: 'Lista desplegable', icon: List },
    { value: 'radio', label: 'Opción única', icon: CircleDot },
    { value: 'checkbox', label: 'Casilla de verificación', icon: CheckSquare },
    { value: 'date', label: 'Fecha', icon: Calendar },
    { value: 'number', label: 'Número', icon: Hash },
    { value: 'email', label: 'Correo', icon: Mail },
    { value: 'tel', label: 'Teléfono', icon: Phone },
];

function keyFromLabel(label: string, existing: string[]): string {
    const base =
        label
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .replace(/^(\d)/, 'campo_$1')
            .slice(0, 36) || 'campo';
    let key = base;
    let i = 2;

    while (existing.includes(key)) {
        key = `${base}_${i++}`;
    }

    return key;
}

export function FormBuilder({
    tenantId,
    schema,
}: {
    tenantId: string;
    schema: FormSchema;
}) {
    const form = useForm<FormSchema>(schema);
    const { data, setData, errors } = form;
    const err = errors as Record<string, string | undefined>;

    const updateField = (index: number, patch: Partial<FormField>) =>
        setData(
            'fields',
            data.fields.map((f, i) => (i === index ? { ...f, ...patch } : f)),
        );

    const addField = (type: FormFieldType) => {
        const label = 'Nuevo campo';
        setData('fields', [
            ...data.fields,
            {
                key: keyFromLabel(
                    label,
                    data.fields.map((f) => f.key),
                ),
                label,
                type,
                required: false,
                placeholder: '',
                help: '',
                width:
                    type === 'textarea' ||
                    type === 'radio' ||
                    type === 'checkbox'
                        ? 'full'
                        : 'half',
                options:
                    type === 'select' || type === 'radio'
                        ? ['Opción 1', 'Opción 2']
                        : [],
            },
        ]);
    };

    const move = (index: number, dir: -1 | 1) => {
        const next = [...data.fields];
        const target = index + dir;
        [next[index], next[target]] = [next[target], next[index]];
        setData('fields', next);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(TenantFormController.update.url(tenantId), {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Tipos de solicitud</CardTitle>
                    <CardDescription>
                        Activa los tipos que recibe la empresa, cambia su nombre
                        y define el plazo de respuesta en días hábiles.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    {data.types.map((type, i) => (
                        <div
                            key={type.key}
                            className={cn(
                                'grid items-start gap-3 rounded-lg border p-3 md:grid-cols-[auto_1fr_2fr_7rem]',
                                !type.enabled && 'bg-muted/40 opacity-70',
                            )}
                        >
                            <div className="flex items-center gap-2 pt-2">
                                <Checkbox
                                    id={`type-${type.key}`}
                                    checked={type.enabled}
                                    onCheckedChange={(v) =>
                                        setData(
                                            'types',
                                            data.types.map((t, j) =>
                                                j === i
                                                    ? {
                                                          ...t,
                                                          enabled: v === true,
                                                      }
                                                    : t,
                                            ),
                                        )
                                    }
                                />
                                <Label
                                    htmlFor={`type-${type.key}`}
                                    className="sr-only"
                                >
                                    Habilitar {type.label}
                                </Label>
                            </div>
                            <div className="grid gap-1">
                                <Input
                                    value={type.label}
                                    onChange={(e) =>
                                        setData(
                                            'types',
                                            data.types.map((t, j) =>
                                                j === i
                                                    ? {
                                                          ...t,
                                                          label: e.target.value,
                                                      }
                                                    : t,
                                            ),
                                        )
                                    }
                                    aria-label="Nombre"
                                />
                                <InputError message={err[`types.${i}.label`]} />
                            </div>
                            <Input
                                value={type.description}
                                onChange={(e) =>
                                    setData(
                                        'types',
                                        data.types.map((t, j) =>
                                            j === i
                                                ? {
                                                      ...t,
                                                      description:
                                                          e.target.value,
                                                  }
                                                : t,
                                        ),
                                    )
                                }
                                aria-label="Descripción"
                                placeholder="Descripción para el ciudadano"
                            />
                            <div className="grid gap-1">
                                <div className="flex items-center gap-2">
                                    <Input
                                        type="number"
                                        min={1}
                                        max={90}
                                        value={type.sla_days}
                                        onChange={(e) =>
                                            setData(
                                                'types',
                                                data.types.map((t, j) =>
                                                    j === i
                                                        ? {
                                                              ...t,
                                                              sla_days: Number(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                          }
                                                        : t,
                                                ),
                                            )
                                        }
                                        aria-label="Días hábiles"
                                        className="w-16"
                                    />
                                    <span className="text-xs text-muted-foreground">
                                        días
                                    </span>
                                </div>
                                <InputError
                                    message={err[`types.${i}.sla_days`]}
                                />
                            </div>
                        </div>
                    ))}
                    <InputError message={err.types} />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Datos del solicitante</CardTitle>
                    <CardDescription>
                        Nombre, correo, asunto y descripción siempre se
                        solicitan. Configura los demás datos.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <Toggle
                        id="require_document"
                        label="Solicitar tipo y número de documento"
                        checked={data.require_document}
                        onChange={(v) => setData('require_document', v)}
                    />
                    <Toggle
                        id="require_phone"
                        label="Teléfono obligatorio"
                        checked={data.require_phone}
                        onChange={(v) => setData('require_phone', v)}
                    />
                    <Toggle
                        id="allow_attachments"
                        label="Permitir adjuntar archivos"
                        checked={data.allow_attachments}
                        onChange={(v) => setData('allow_attachments', v)}
                    />
                    <Toggle
                        id="risk_levels"
                        label="Reclamos por nivel de riesgo (simple, priorizado, vital · Supersalud)"
                        checked={data.risk_levels}
                        onChange={(v) => setData('risk_levels', v)}
                    />
                    <div
                        className={cn(
                            'flex items-center gap-3',
                            !data.allow_attachments && 'opacity-50',
                        )}
                    >
                        <Label
                            htmlFor="max_attachments"
                            className="font-normal"
                        >
                            Máximo de archivos
                        </Label>
                        <Input
                            id="max_attachments"
                            type="number"
                            min={1}
                            max={10}
                            disabled={!data.allow_attachments}
                            value={data.max_attachments}
                            onChange={(e) =>
                                setData(
                                    'max_attachments',
                                    Number(e.target.value),
                                )
                            }
                            className="w-20"
                        />
                        <InputError message={err.max_attachments} />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Campos personalizados</CardTitle>
                    <CardDescription>
                        Información propia de esta empresa (número de póliza,
                        sede, contrato, producto…).
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {data.fields.length === 0 && (
                        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                            Este formulario aún no tiene campos personalizados.
                        </p>
                    )}

                    {data.fields.map((field, i) => {
                        const TypeIcon =
                            FIELD_TYPES.find((t) => t.value === field.type)
                                ?.icon ?? Text;
                        const hasOptions =
                            field.type === 'select' || field.type === 'radio';

                        return (
                            <div key={i} className="rounded-lg border bg-card">
                                <div className="flex items-center gap-2 border-b bg-muted/40 px-3 py-2">
                                    <TypeIcon className="size-4 text-muted-foreground" />
                                    <span className="flex-1 truncate text-sm font-medium">
                                        {field.label || 'Sin etiqueta'}
                                        {field.required && (
                                            <span className="ml-1 text-red-600">
                                                *
                                            </span>
                                        )}
                                    </span>
                                    <code className="hidden text-xs text-muted-foreground sm:inline">
                                        {field.key}
                                    </code>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        disabled={i === 0}
                                        onClick={() => move(i, -1)}
                                        aria-label="Subir"
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        disabled={i === data.fields.length - 1}
                                        onClick={() => move(i, 1)}
                                        aria-label="Bajar"
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7 text-red-600 hover:text-red-700"
                                        onClick={() =>
                                            setData(
                                                'fields',
                                                data.fields.filter(
                                                    (_, j) => j !== i,
                                                ),
                                            )
                                        }
                                        aria-label="Eliminar campo"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                                <div className="grid gap-4 p-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Etiqueta</Label>
                                        <Input
                                            value={field.label}
                                            onChange={(e) => {
                                                const label = e.target.value;
                                                const autoKey =
                                                    field.key.startsWith(
                                                        'nuevo_campo',
                                                    );
                                                updateField(i, {
                                                    label,
                                                    ...(autoKey
                                                        ? {
                                                              key: keyFromLabel(
                                                                  label,
                                                                  data.fields
                                                                      .filter(
                                                                          (
                                                                              _,
                                                                              j,
                                                                          ) =>
                                                                              j !==
                                                                              i,
                                                                      )
                                                                      .map(
                                                                          (f) =>
                                                                              f.key,
                                                                      ),
                                                              ),
                                                          }
                                                        : {}),
                                                });
                                            }}
                                        />
                                        <InputError
                                            message={
                                                err[`fields.${i}.label`] ??
                                                err[`fields.${i}.key`]
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Tipo de campo</Label>
                                        <NativeSelect
                                            value={field.type}
                                            onChange={(e) => {
                                                const type = e.target
                                                    .value as FormFieldType;
                                                updateField(i, {
                                                    type,
                                                    options:
                                                        (type === 'select' ||
                                                            type === 'radio') &&
                                                        field.options.length ===
                                                            0
                                                            ? ['Opción 1']
                                                            : field.options,
                                                });
                                            }}
                                        >
                                            {FIELD_TYPES.map((t) => (
                                                <option
                                                    key={t.value}
                                                    value={t.value}
                                                >
                                                    {t.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </div>
                                    {field.type !== 'checkbox' &&
                                        !hasOptions && (
                                            <div className="grid gap-2">
                                                <Label>
                                                    Texto de ayuda dentro del
                                                    campo
                                                </Label>
                                                <Input
                                                    value={field.placeholder}
                                                    onChange={(e) =>
                                                        updateField(i, {
                                                            placeholder:
                                                                e.target.value,
                                                        })
                                                    }
                                                    placeholder="Ej: AU-2026-001"
                                                />
                                            </div>
                                        )}
                                    <div className="grid gap-2">
                                        <Label>Descripción bajo el campo</Label>
                                        <Input
                                            value={field.help}
                                            onChange={(e) =>
                                                updateField(i, {
                                                    help: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    {hasOptions && (
                                        <div className="grid gap-2 md:col-span-2">
                                            <Label>
                                                Opciones (una por línea)
                                            </Label>
                                            <Textarea
                                                rows={Math.max(
                                                    3,
                                                    field.options.length,
                                                )}
                                                value={field.options.join('\n')}
                                                onChange={(e) =>
                                                    updateField(i, {
                                                        options:
                                                            e.target.value.split(
                                                                '\n',
                                                            ),
                                                    })
                                                }
                                                onBlur={() =>
                                                    updateField(i, {
                                                        options: field.options
                                                            .map((o) =>
                                                                o.trim(),
                                                            )
                                                            .filter(Boolean),
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    err[
                                                        `fields.${i}.options`
                                                    ] ??
                                                    Object.entries(err).find(
                                                        ([k]) =>
                                                            k.startsWith(
                                                                `fields.${i}.options.`,
                                                            ),
                                                    )?.[1]
                                                }
                                            />
                                        </div>
                                    )}
                                    <div className="flex flex-wrap items-center gap-6 md:col-span-2">
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={field.required}
                                                onCheckedChange={(v) =>
                                                    updateField(i, {
                                                        required: v === true,
                                                    })
                                                }
                                            />
                                            Obligatorio
                                        </label>
                                        {field.type !== 'textarea' &&
                                            field.type !== 'radio' &&
                                            field.type !== 'checkbox' && (
                                                <label className="flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={
                                                            field.width ===
                                                            'full'
                                                        }
                                                        onCheckedChange={(v) =>
                                                            updateField(i, {
                                                                width:
                                                                    v === true
                                                                        ? 'full'
                                                                        : 'half',
                                                            })
                                                        }
                                                    />
                                                    Ancho completo
                                                </label>
                                            )}
                                    </div>
                                </div>
                            </div>
                        );
                    })}

                    <div>
                        <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                            Agregar campo
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {FIELD_TYPES.map((t) => (
                                <Button
                                    key={t.value}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => addField(t.value)}
                                    disabled={data.fields.length >= 20}
                                >
                                    <Plus />
                                    {t.label}
                                </Button>
                            ))}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Tratamiento de datos</CardTitle>
                    <CardDescription>
                        Texto de autorización que el ciudadano debe aceptar (Ley
                        1581 de 2012).
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="privacy_text">
                            Texto de autorización
                        </Label>
                        <Textarea
                            id="privacy_text"
                            rows={3}
                            value={data.privacy_text}
                            onChange={(e) =>
                                setData('privacy_text', e.target.value)
                            }
                        />
                        <InputError message={err.privacy_text} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="privacy_policy_url">
                            URL de la política de tratamiento de datos
                        </Label>
                        <Input
                            id="privacy_policy_url"
                            type="url"
                            placeholder="https://"
                            value={data.privacy_policy_url ?? ''}
                            onChange={(e) =>
                                setData('privacy_policy_url', e.target.value)
                            }
                        />
                        <InputError message={err.privacy_policy_url} />
                    </div>
                </CardContent>
            </Card>

            <div className="sticky bottom-4 z-10 flex items-center justify-end gap-3 rounded-xl border bg-background/95 p-3 shadow-lg backdrop-blur">
                {Object.keys(errors).length > 0 && (
                    <span className="mr-auto text-sm text-red-600">
                        Revisa los campos marcados.
                    </span>
                )}
                {form.isDirty && (
                    <span className="text-sm text-muted-foreground">
                        Cambios sin guardar
                    </span>
                )}
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Guardar formulario
                </Button>
            </div>
        </form>
    );
}

function Toggle({
    id,
    label,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <label
            htmlFor={id}
            className="flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm hover:bg-muted/40"
        >
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(v) => onChange(v === true)}
            />
            {label}
        </label>
    );
}
