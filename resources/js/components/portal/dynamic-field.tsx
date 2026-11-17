import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { FormField } from '@/types';

/**
 * Renderiza un campo personalizado del formulario de la empresa.
 * Los valores se envían como fields[<key>].
 */
export function DynamicField({
    field,
    error,
    disabled,
}: {
    field: FormField;
    error?: string;
    disabled?: boolean;
}) {
    const id = `field-${field.key}`;
    const name = `fields[${field.key}]`;
    const placeholder = field.placeholder || undefined;

    if (field.type === 'checkbox') {
        return (
            <div className="sm:col-span-2">
                <label
                    htmlFor={id}
                    className="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 p-3 hover:bg-zinc-50"
                >
                    <Checkbox
                        id={id}
                        name={name}
                        value="1"
                        disabled={disabled}
                        className="mt-0.5"
                    />
                    <span className="text-sm">
                        {field.label}
                        {field.required && <Required />}
                        {field.help && (
                            <span className="block text-xs text-zinc-500">
                                {field.help}
                            </span>
                        )}
                    </span>
                </label>
                <InputError message={error} className="mt-1" />
            </div>
        );
    }

    return (
        <div
            className={cn(
                'grid content-start gap-2',
                (field.width === 'full' ||
                    field.type === 'textarea' ||
                    field.type === 'radio') &&
                    'sm:col-span-2',
            )}
        >
            <Label htmlFor={id}>
                {field.label}
                {field.required && <Required />}
            </Label>

            {field.type === 'textarea' ? (
                <Textarea
                    id={id}
                    name={name}
                    rows={3}
                    placeholder={placeholder}
                    required={field.required}
                    disabled={disabled}
                    aria-invalid={!!error}
                />
            ) : field.type === 'select' ? (
                <NativeSelect
                    id={id}
                    name={name}
                    required={field.required}
                    disabled={disabled}
                    defaultValue=""
                    aria-invalid={!!error}
                >
                    <option value="" disabled={field.required}>
                        Selecciona una opción
                    </option>
                    {field.options.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </NativeSelect>
            ) : field.type === 'radio' ? (
                <div className="flex flex-wrap gap-2" role="radiogroup" id={id}>
                    {field.options.map((option) => (
                        <label
                            key={option}
                            className="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm transition-colors hover:bg-zinc-50 has-checked:border-(--brand) has-checked:bg-(--brand-soft)"
                        >
                            <input
                                type="radio"
                                name={name}
                                value={option}
                                required={field.required}
                                disabled={disabled}
                                className="accent-(--brand)"
                            />
                            {option}
                        </label>
                    ))}
                </div>
            ) : (
                <Input
                    id={id}
                    name={name}
                    type={field.type}
                    placeholder={placeholder}
                    required={field.required}
                    disabled={disabled}
                    aria-invalid={!!error}
                />
            )}

            {field.help && (
                <p className="text-xs text-zinc-500">{field.help}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

export function Required() {
    return (
        <span className="ml-0.5 text-red-600" aria-hidden>
            *
        </span>
    );
}
