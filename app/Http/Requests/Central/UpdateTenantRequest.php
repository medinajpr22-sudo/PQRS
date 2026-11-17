<?php

namespace App\Http\Requests\Central;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Stancl\Tenancy\Database\Models\Domain;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $domains = collect((array) $this->input('domains'))
            ->map(fn ($d) => mb_strtolower(trim((string) $d)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'domains' => $domains,
            'radicado_prefix' => mb_strtoupper(trim((string) $this->input('radicado_prefix'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return [
            'name' => ['required', 'string', 'max:150'],
            'nit' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'plan' => ['required', Rule::in(Tenant::PLANS)],
            'radicado_prefix' => ['required', 'string', 'max:6', 'regex:/^[A-Z0-9]+$/'],
            'domains' => ['required', 'array', 'min:1', 'max:5'],
            'domains.*' => [
                'string', 'max:190',
                'regex:/^(?=.{1,190}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z0-9-]+$/',
                Rule::notIn(config('tenancy.central_domains')),
                function (string $attribute, mixed $value, \Closure $fail) use ($tenant) {
                    $taken = Domain::where('domain', $value)->where('tenant_id', '!=', $tenant->id)->exists();

                    if ($taken) {
                        $fail("El dominio {$value} ya pertenece a otra empresa.");
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'razón social',
            'radicado_prefix' => 'prefijo de radicado',
            'domains' => 'dominios',
            'domains.*' => 'dominio',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domains.*.regex' => 'El dominio no es válido (ej: pqrs.miempresa.com).',
            'radicado_prefix.regex' => 'El prefijo solo puede tener letras y números.',
        ];
    }
}
