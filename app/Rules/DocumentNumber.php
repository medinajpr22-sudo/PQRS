<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Número de documento coherente con su tipo.
 *
 * Cédulas, tarjetas de identidad, registros civiles y PPT solo llevan
 * números; el NIT admite el dígito de verificación (900123456-7) y el
 * pasaporte letras y números. Los puntos y espacios se quitan antes de
 * validar (ver PqrsFormRules::normalize).
 *
 * Además frena el spam: los robots escriben cosas como «gyh5ty».
 */
final class DocumentNumber implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param  string  $typeField  Campo con el tipo de documento.
     */
    public function __construct(private readonly string $typeField) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = $this->data[$this->typeField] ?? null;
        $value = is_scalar($value) ? (string) $value : '';

        [$pattern, $message] = match ($type) {
            'PA' => ['/^[A-Z0-9]{4,20}$/', 'El :attribute solo puede tener letras y números.'],
            'NIT' => ['/^\d{5,12}(-\d)?$/', 'El :attribute solo puede tener números y el dígito de verificación (p. ej. 900123456-7).'],
            default => ['/^\d{3,15}$/', 'El :attribute solo puede tener números, sin letras.'],
        };

        if (preg_match($pattern, $value) !== 1) {
            $fail($message);
        }
    }
}
