<?php

namespace App\Http\Requests\Central;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Stancl\Tenancy\Database\Models\Domain;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subdomain' => mb_strtolower(trim((string) $this->input('subdomain'))),
            'radicado_prefix' => mb_strtoupper(trim((string) $this->input('radicado_prefix'))),
            'database' => mb_strtolower(trim((string) $this->input('database'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'nit' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'plan' => ['required', Rule::in(Tenant::PLANS)],
            'subdomain' => [
                'required', 'string', 'min:3', 'max:40',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::notIn(['www', 'admin', 'api', 'app', 'mail', 'central']),
                Rule::unique('tenants', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (Domain::where('domain', Tenant::subdomainToDomain($value))->exists()) {
                        $fail('Este subdominio ya está en uso.');
                    }
                },
            ],
            'radicado_prefix' => ['nullable', 'string', 'max:6', 'regex:/^[A-Z0-9]+$/'],
            // Nombre de la base de datos propia de la empresa (no se puede cambiar después).
            'database' => [
                'required', 'string', 'min:3', 'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::notIn(Tenant::RESERVED_DATABASE_NAMES),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (Tenant::databaseNameInUse((string) $value)) {
                        $fail('Ya existe una base de datos con ese nombre.');
                    }
                },
            ],
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'email', 'max:150'],
            'admin_password' => ['required', 'string', Password::defaults(), 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'razón social',
            'nit' => 'NIT',
            'subdomain' => 'subdominio',
            'radicado_prefix' => 'prefijo de radicado',
            'database' => 'nombre de la base de datos',
            'admin_name' => 'nombre del administrador',
            'admin_email' => 'correo del administrador',
            'admin_password' => 'contraseña',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subdomain.regex' => 'Usa solo minúsculas, números y guiones (ej: mi-empresa).',
            'subdomain.unique' => 'Ya existe una empresa con este identificador.',
            'radicado_prefix.regex' => 'El prefijo solo puede tener letras y números.',
        ];
    }
}
