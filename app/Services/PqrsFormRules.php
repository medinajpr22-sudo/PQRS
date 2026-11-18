<?php

namespace App\Services;

use Illuminate\Validation\Rule;

/**
 * Construye las reglas de validación del formulario público a partir
 * del formulario configurado para cada empresa.
 */
class PqrsFormRules
{
    public const DOCUMENT_TYPES = [
        'CC' => 'Cédula de ciudadanía',
        'CE' => 'Cédula de extranjería',
        'NIT' => 'NIT',
        'PA' => 'Pasaporte',
        'TI' => 'Tarjeta de identidad',
        'PPT' => 'Permiso por protección temporal',
    ];

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<int>  $sedeIds  Sedes activas de la empresa. Si tiene alguna,
     *                              el ciudadano debe elegir una.
     * @return array<string, mixed>
     */
    public static function rules(array $schema, array $sedeIds = []): array
    {
        $enabledTypes = collect($schema['types'])->where('enabled', true)->pluck('key')->all();

        $rules = [
            'type' => ['required', Rule::in($enabledTypes)],
            'requester_name' => ['required', 'string', 'max:150'],
            'requester_email' => ['required', 'email', 'max:150'],
            'requester_phone' => [$schema['require_phone'] ? 'required' : 'nullable', 'string', 'max:30'],
            'requester_document_type' => [$schema['require_document'] ? 'required' : 'nullable', Rule::in(array_keys(self::DOCUMENT_TYPES))],
            'requester_document' => [$schema['require_document'] ? 'required' : 'nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'privacy' => ['accepted'],
        ];

        if ($sedeIds !== []) {
            $rules['sede_id'] = ['required', 'integer', Rule::in($sedeIds)];
        }

        foreach ($schema['fields'] as $field) {
            $rules['fields.'.$field['key']] = self::fieldRules($field);
        }

        if ($schema['allow_attachments']) {
            $rules['attachments'] = ['nullable', 'array', 'max:'.(int) $schema['max_attachments']];
            $rules['attachments.*'] = [
                'file',
                'max:'.config('pqrs.attachments.max_kb'),
                'mimes:'.implode(',', config('pqrs.attachments.mimes')),
            ];
        }

        return $rules;
    }

    /**
     * Nombres legibles para los mensajes de error.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, string>
     */
    public static function attributes(array $schema): array
    {
        $attributes = [
            'type' => 'tipo de solicitud',
            'sede_id' => 'sede',
            'requester_name' => 'nombre completo',
            'requester_email' => 'correo electrónico',
            'requester_phone' => 'teléfono',
            'requester_document_type' => 'tipo de documento',
            'requester_document' => 'número de documento',
            'subject' => 'asunto',
            'description' => 'descripción',
            'privacy' => 'autorización de tratamiento de datos',
            'attachments' => 'adjuntos',
            'attachments.*' => 'archivo adjunto',
        ];

        foreach ($schema['fields'] as $field) {
            $attributes['fields.'.$field['key']] = mb_strtolower($field['label']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<mixed>
     */
    protected static function fieldRules(array $field): array
    {
        $required = (bool) ($field['required'] ?? false);
        $options = $field['options'] ?? [];

        return match ($field['type']) {
            'checkbox' => $required ? ['accepted'] : ['nullable', 'boolean'],
            'email' => [$required ? 'required' : 'nullable', 'email', 'max:150'],
            'number' => [$required ? 'required' : 'nullable', 'numeric'],
            'date' => [$required ? 'required' : 'nullable', 'date'],
            'select', 'radio' => [$required ? 'required' : 'nullable', Rule::in($options)],
            'textarea' => [$required ? 'required' : 'nullable', 'string', 'max:3000'],
            default => [$required ? 'required' : 'nullable', 'string', 'max:255'],
        };
    }
}
