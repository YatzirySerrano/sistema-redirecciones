<?php

namespace App\Rules;

use App\Services\Redirecciones\ValidadorUrlDestino;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regla de validación para URLs de destino de enlaces permanentes.
 */
class UrlDestinoSegura implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('La URL de destino debe ser texto.');

            return;
        }

        $error = app(ValidadorUrlDestino::class)->error($value);

        if ($error !== null) {
            $fail($error);
        }
    }
}
