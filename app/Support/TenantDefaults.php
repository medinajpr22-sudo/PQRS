<?php

namespace App\Support;

use App\Enums\PqrsType;

/**
 * Valores por defecto del diseño y del formulario de cada empresa.
 */
class TenantDefaults
{
    public const LAYOUTS = ['corporate', 'modern', 'minimal', 'salud'];

    /**
     * Tipografías del portal. Circe Rounded es la institucional (se sirve
     * desde resources/fonts, ver vite.config.ts) y es la predeterminada.
     */
    public const FONTS = [
        'circe-rounded' => ['label' => 'Circe Rounded (institucional)', 'family' => "'Circe Rounded'", 'bunny' => null],
        'inter' => ['label' => 'Inter', 'family' => "'Inter'", 'bunny' => 'inter:400,500,600,700'],
        'poppins' => ['label' => 'Poppins', 'family' => "'Poppins'", 'bunny' => 'poppins:400,500,600,700'],
        'montserrat' => ['label' => 'Montserrat', 'family' => "'Montserrat'", 'bunny' => 'montserrat:400,500,600,700'],
        'merriweather' => ['label' => 'Merriweather (serif)', 'family' => "'Merriweather'", 'bunny' => 'merriweather:400,700'],
    ];

    public const FIELD_TYPES = ['text', 'textarea', 'email', 'tel', 'number', 'date', 'select', 'radio', 'checkbox'];

    /**
     * @return array<string, mixed>
     */
    public static function theme(string $companyName = ''): array
    {
        return [
            'layout' => 'corporate',
            'primary_color' => '#1d4ed8',
            'accent_color' => '#f59e0b',
            // Panel administrativo de la empresa: menú lateral y botones.
            'panel_color' => '#5b1a24',
            'panel_accent' => '#c62833',
            'font' => 'circe-rounded',
            'logo_path' => null,
            'hero_title' => 'Peticiones, quejas, reclamos y sugerencias',
            'hero_subtitle' => $companyName !== ''
                ? "En {$companyName} queremos escucharte. Radica tu solicitud y haz seguimiento en línea."
                : 'Radica tu solicitud y haz seguimiento en línea.',
            'footer_text' => '',
            'website_url' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formSchema(): array
    {
        return [
            'types' => array_map(fn (PqrsType $type) => [
                'key' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'enabled' => $type !== PqrsType::Denuncia,
                'sla_days' => $type->defaultSlaDays(),
            ], PqrsType::cases()),
            'fields' => [],
            'require_document' => true,
            'require_phone' => false,
            'allow_attachments' => true,
            'max_attachments' => 3,
            // Reclamos en salud: el ciudadano elige el nivel de riesgo (Supersalud).
            'risk_levels' => false,
            'privacy_policy_url' => '',
            'privacy_text' => 'Autorizo el tratamiento de mis datos personales conforme a la Ley 1581 de 2012 y la política de tratamiento de datos de la empresa.',
        ];
    }

    /**
     * Combina la configuración guardada con los valores por defecto para
     * que siempre existan todas las llaves (útil al agregar opciones nuevas).
     *
     * @param  array<string, mixed>|null  $schema
     * @return array<string, mixed>
     */
    public static function mergeFormSchema(?array $schema): array
    {
        $defaults = self::formSchema();
        $schema = array_merge($defaults, $schema ?? []);

        $savedTypes = collect($schema['types'] ?? [])->keyBy('key');
        $schema['types'] = collect($defaults['types'])
            ->map(fn (array $type) => array_merge($type, $savedTypes->get($type['key'], [])))
            ->values()
            ->all();

        return $schema;
    }
}
