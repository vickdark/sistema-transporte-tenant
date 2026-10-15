<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Formato de nombre de usuario: 3 a 50 caracteres en minúsculas, números, punto, guion y guion
 * bajo. Nunca lleva "@", así el login "usuario o correo" sabe sin ambigüedad cuál recibió.
 */
class Username implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[a-z0-9._-]{3,50}$/', $value) !== 1) {
            $fail('El usuario debe tener entre 3 y 50 caracteres: minúsculas, números, punto, guion o guion bajo.');
        }
    }
}
