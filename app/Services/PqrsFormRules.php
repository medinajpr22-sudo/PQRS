<?php

namespace App\Services;

use App\Enums\PqrsType;
use App\Enums\RiskLevel;
use App\Rules\DocumentNumber;
use App\Rules\EmailDomainReceivesMail;
use App\Support\UploadLimits;
use Closure;
use Illuminate\Support\Str;
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
     * Documentos del paciente afectado: puede ser un menor (registro civil),
     * pero no una empresa (NIT).
     */
    public const PATIENT_DOCUMENT_TYPES = [
        'CC' => 'Cédula de ciudadanía',
        'TI' => 'Tarjeta de identidad',
        'RC' => 'Registro civil',
        'CE' => 'Cédula de extranjería',
        'PA' => 'Pasaporte',
        'PPT' => 'Permiso por protección temporal',
    ];

    /**
     * Régimen de afiliación al sistema de salud.
     */
    public const HEALTH_REGIMES = [
        'contributivo' => 'Contributivo',
        'subsidiado' => 'Subsidiado',
        'especial' => 'Especial o de excepción',
        'particular' => 'Particular (sin EPS)',
    ];

    /**
     * Nombre de un tipo de documento (del solicitante o del paciente).
     */
    public static function documentLabel(?string $type): ?string
    {
        return $type === null ? null : (self::DOCUMENT_TYPES[$type] ?? self::PATIENT_DOCUMENT_TYPES[$type] ?? $type);
    }

    /**
     * Limpia los datos antes de validarlos: el ciudadano suele escribir la
     * cédula con puntos o espacios (1.075.234.567), y los campos de una línea
     * no deben traer saltos de línea.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function normalize(array $input): array
    {
        $clean = [];

        foreach (['requester_document', 'affected_document'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $clean[$key] = mb_strtoupper((string) preg_replace('/[\s.,]+/u', '', $input[$key]));
            }
        }

        // Campos de una sola línea: sin saltos de línea ni espacios de más
        // (se usan en los correos y en los listados).
        foreach (['requester_name', 'affected_name', 'requester_relationship', 'eps', 'subject'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $clean[$key] = trim((string) preg_replace('/\s+/u', ' ', $input[$key]));
            }
        }

        return $clean;
    }

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
            'requester_email' => ['bail', 'required', 'string', 'max:150', ...self::emailRules()],
            'requester_phone' => [$schema['require_phone'] ? 'required' : 'nullable', 'string', 'max:30'],
            'requester_document_type' => [$schema['require_document'] ? 'required' : 'nullable', Rule::in(array_keys(self::DOCUMENT_TYPES))],
            'requester_document' => [$schema['require_document'] ? 'required' : 'nullable', 'string', 'max:30', new DocumentNumber('requester_document_type')],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'privacy' => ['accepted'],
        ];

        if ($sedeIds !== []) {
            $rules['sede_id'] = ['required', 'integer', Rule::in($sedeIds)];
        }

        // Reclamos en salud: nivel de riesgo obligatorio si la empresa lo usa.
        if (! empty($schema['risk_levels'])) {
            $rules['risk_level'] = ['nullable', 'required_if:type,'.PqrsType::Reclamo->value, Rule::enum(RiskLevel::class)];
        }

        // Empresas de salud: quién recibió el servicio, EPS, régimen y fecha.
        // Si el solicitante es el mismo afectado, sus datos no se repiten.
        if (! empty($schema['health_data'])) {
            $other = 'exclude_if:requester_is_affected,1';

            $rules += [
                'requester_is_affected' => ['required', 'boolean'],
                'requester_relationship' => [$other, 'nullable', 'string', 'max:60'],
                'affected_name' => [$other, 'required', 'string', 'max:150'],
                'affected_document_type' => [$other, 'required', Rule::in(array_keys(self::PATIENT_DOCUMENT_TYPES))],
                'affected_document' => [$other, 'required', 'string', 'max:30', new DocumentNumber('affected_document_type')],
                'affected_phone' => [$other, 'nullable', 'string', 'max:30'],
                'affected_email' => [$other, 'bail', 'nullable', 'string', 'max:150', ...self::emailRules()],
                'eps' => ['nullable', 'string', 'max:120'],
                'health_regime' => ['nullable', Rule::in(array_keys(self::HEALTH_REGIMES))],
                'attended_on' => ['nullable', 'date', 'after:2000-01-01', 'before_or_equal:today'],
            ];
        }

        if (! empty($schema['services'])) {
            $rules['service'] = ['nullable', Rule::in($schema['services'])];
        }

        foreach ($schema['fields'] as $field) {
            $rules['fields.'.$field['key']] = self::fieldRules($field);
        }

        if ($schema['allow_attachments']) {
            // Tamaño por archivo y total: los reales del servidor (UploadLimits).
            $rules = [...$rules, ...UploadLimits::rules('attachments', (int) $schema['max_attachments'])];
        }

        return $rules;
    }

    /**
     * Correo del ciudadano: errores de escritura conocidos, formato y que el
     * dominio reciba correos (ver EmailDomainReceivesMail). Los rebotes le
     * restan reputación al buzón que envía.
     *
     * @return list<mixed>
     */
    public static function emailRules(): array
    {
        return [
            self::emailTypoRule(),
            'email:rfc',
            ...(config('pqrs.mail.check_dns') ? [new EmailDomainReceivesMail] : []),
        ];
    }

    /**
     * Errores de escritura comunes en los correos más usados. Algunos, como
     * «gmial.com», existen y reciben correo (son de terceros), así que la
     * revisión del dominio no los atrapa: el ciudadano nunca recibiría
     * nada.
     */
    private const EMAIL_TYPOS = [
        'gmial.com' => 'gmail.com', 'gmal.com' => 'gmail.com', 'gamil.com' => 'gmail.com',
        'gmai.com' => 'gmail.com', 'gmaill.com' => 'gmail.com', 'gnail.com' => 'gmail.com',
        'gmail.con' => 'gmail.com', 'gmail.cm' => 'gmail.com', 'gmail.om' => 'gmail.com',
        'hotmal.com' => 'hotmail.com', 'hotmial.com' => 'hotmail.com', 'hotmai.com' => 'hotmail.com',
        'hotmil.com' => 'hotmail.com', 'hotamil.com' => 'hotmail.com', 'hotmail.con' => 'hotmail.com',
        'outlok.com' => 'outlook.com', 'outllok.com' => 'outlook.com', 'otlook.com' => 'outlook.com',
        'outlook.con' => 'outlook.com', 'yaho.com' => 'yahoo.com', 'yahoo.con' => 'yahoo.com',
    ];

    /**
     * Sugiere la dirección correcta si el dominio es un error de escritura
     * conocido.
     */
    public static function emailTypoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! str_contains($value, '@')) {
                return;
            }

            $domain = mb_strtolower(trim(Str::afterLast($value, '@')));

            if (isset(self::EMAIL_TYPOS[$domain])) {
                $fail('Revisa el :attribute: ¿quisiste decir «'.mb_strtolower(Str::beforeLast(trim($value), '@')).'@'.self::EMAIL_TYPOS[$domain].'»?');
            }
        };
    }

    /**
     * Mensajes de error de los correos.
     *
     * @return array<string, string>
     */
    public static function emailMessages(): array
    {
        $message = 'Revisa el :attribute: no parece un correo válido (debe ser como nombre@gmail.com).';

        return [
            'requester_email.email' => $message,
            'affected_email.email' => $message,
        ];
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
            'risk_level' => 'nivel de riesgo',
            'requester_name' => 'nombre completo',
            'requester_email' => 'correo electrónico',
            'requester_phone' => 'teléfono',
            'requester_document_type' => 'tipo de documento',
            'requester_document' => 'número de documento',
            'requester_is_affected' => 'quién recibió el servicio',
            'requester_relationship' => 'parentesco',
            'affected_name' => 'nombre del afectado',
            'affected_document_type' => 'tipo de documento del afectado',
            'affected_document' => 'número de documento del afectado',
            'affected_phone' => 'teléfono del afectado',
            'affected_email' => 'correo del afectado',
            'eps' => 'EPS',
            'health_regime' => 'régimen',
            'service' => 'servicio recibido',
            'attended_on' => 'fecha de atención',
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
