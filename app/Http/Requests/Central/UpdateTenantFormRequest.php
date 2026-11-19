<?php

namespace App\Http\Requests\Central;

use App\Enums\PqrsType;
use App\Support\TenantDefaults;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTenantFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'types' => ['required', 'array'],
            'types.*.key' => ['required', 'distinct', Rule::in(PqrsType::values())],
            'types.*.label' => ['required', 'string', 'max:60'],
            'types.*.description' => ['nullable', 'string', 'max:400'],
            'types.*.enabled' => ['required', 'boolean'],
            'types.*.sla_days' => ['required', 'integer', 'min:1', 'max:90'],

            'fields' => ['present', 'array', 'max:20'],
            'fields.*.key' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.type' => ['required', Rule::in(TenantDefaults::FIELD_TYPES)],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:120'],
            'fields.*.help' => ['nullable', 'string', 'max:200'],
            'fields.*.width' => ['required', Rule::in(['full', 'half'])],
            'fields.*.options' => ['nullable', 'array', 'max:30'],
            'fields.*.options.*' => ['required', 'string', 'max:120'],

            'require_document' => ['required', 'boolean'],
            'require_phone' => ['required', 'boolean'],
            'allow_attachments' => ['required', 'boolean'],
            'max_attachments' => ['required', 'integer', 'min:1', 'max:10'],
            'risk_levels' => ['sometimes', 'boolean'],
            'health_data' => ['sometimes', 'boolean'],
            'services' => ['sometimes', 'array', 'max:40'],
            'services.*' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
            'privacy_policy_url' => ['nullable', 'url', 'max:190'],
            'privacy_text' => ['required', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $types = collect($this->input('types', []));

                if ($types->where('enabled', true)->isEmpty()) {
                    $validator->errors()->add('types', 'Debes habilitar al menos un tipo de solicitud.');
                }

                foreach ($this->input('fields', []) as $i => $field) {
                    if (in_array($field['type'] ?? null, ['select', 'radio'], true) && empty($field['options'])) {
                        $validator->errors()->add("fields.{$i}.options", 'Agrega al menos una opción para este campo.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'types.*.label' => 'nombre del tipo',
            'types.*.sla_days' => 'días de respuesta',
            'fields.*.key' => 'identificador del campo',
            'fields.*.label' => 'etiqueta del campo',
            'fields.*.options.*' => 'opción',
            'max_attachments' => 'máximo de adjuntos',
            'services.*' => 'servicio',
            'privacy_policy_url' => 'URL de la política de datos',
            'privacy_text' => 'texto de autorización',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.*.key.distinct' => 'Hay dos campos con el mismo identificador.',
            'fields.*.key.regex' => 'El identificador solo admite minúsculas, números y guion bajo.',
            'services.*.distinct' => 'Hay un servicio repetido.',
        ];
    }

    /**
     * Esquema normalizado para guardar.
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $data = $this->validated();

        $data['fields'] = collect($data['fields'])->map(fn (array $field) => [
            'key' => $field['key'],
            'label' => $field['label'],
            'type' => $field['type'],
            'required' => (bool) $field['required'],
            'placeholder' => $field['placeholder'] ?? '',
            'help' => $field['help'] ?? '',
            'width' => $field['width'],
            'options' => in_array($field['type'], ['select', 'radio'], true)
                ? array_values(array_unique($field['options'] ?? []))
                : [],
        ])->values()->all();

        $data['services'] = array_values(array_map('trim', $data['services'] ?? []));

        return TenantDefaults::mergeFormSchema($data);
    }
}
