<?php

namespace App\Rules;

use App\Services\CodiceFiscale;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Codice fiscale di persona fisica valido: formato, carattere di
 * controllo, data e luogo di nascita riconoscibili (App\Services\CodiceFiscale).
 */
class CodiceFiscaleValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $esito = CodiceFiscale::analizza(is_string($value) ? $value : null);

        if (! $esito['valido']) {
            $fail($esito['errore']);
        }
    }
}
