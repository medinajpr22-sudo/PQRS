<?php

namespace App\Http\Requests\Central;

use App\Support\TenantDefaults;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantThemeRequest extends FormRequest
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
        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'layout' => ['required', Rule::in(TenantDefaults::LAYOUTS)],
            'primary_color' => $hex,
            'accent_color' => $hex,
            'panel_color' => ['sometimes', ...$hex],
            'panel_accent' => ['sometimes', ...$hex],
            'font' => ['required', Rule::in(array_keys(TenantDefaults::FONTS))],
            'hero_title' => ['required', 'string', 'max:120'],
            'hero_subtitle' => ['nullable', 'string', 'max:300'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'url', 'max:190'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            // Sin SVG: se sirve desde nuestro dominio y podría traer scripts.
            'icon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,ico', 'max:1024'],
            'remove_icon' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'layout' => 'plantilla',
            'primary_color' => 'color principal',
            'accent_color' => 'color de acento',
            'panel_color' => 'color del menú del panel',
            'panel_accent' => 'color de botones del panel',
            'font' => 'tipografía',
            'hero_title' => 'título',
            'hero_subtitle' => 'subtítulo',
            'footer_text' => 'texto del pie de página',
            'website_url' => 'sitio web',
            'icon' => 'ícono',
        ];
    }
}
