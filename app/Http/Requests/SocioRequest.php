<?php

namespace App\Http\Requests;

use App\Models\Socio;
use App\Rules\CodiceFiscaleValido;
use App\Services\CodiceFiscale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Validazione di inserimento e modifica di un socio (vedi soci.md).
 * Ripresa dal Gestionale APS, adattata alle regole ASD.
 */
class SocioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->puoModificare('soci');
    }

    /**
     * Normalizza prima di validare: CF maiuscolo senza spazi, provincia
     * maiuscola. Data, luogo di nascita e sesso si ricavano dal codice
     * fiscale (Paolo, 30/09/2026): il modulo li mostra già compilati, ma
     * vale sempre il calcolo del server.
     */
    protected function prepareForValidation(): void
    {
        $cf = $this->filled('codice_fiscale') ? strtoupper(preg_replace('/\s+/', '', $this->input('codice_fiscale'))) : null;
        $tutoreCf = $this->filled('tutore_codice_fiscale') ? strtoupper(preg_replace('/\s+/', '', $this->input('tutore_codice_fiscale'))) : null;

        $dati = [
            'codice_fiscale' => $cf,
            'tutore_codice_fiscale' => $tutoreCf,
            'provincia' => $this->filled('provincia') ? strtoupper(trim($this->input('provincia'))) : null,
            'numero_tessera' => $this->filled('numero_tessera') ? trim($this->input('numero_tessera')) : null,
        ];

        $esito = CodiceFiscale::analizza($cf);
        if ($esito['valido']) {
            $dati['data_nascita'] = $esito['data_nascita']->format('Y-m-d');
            $dati['luogo_nascita'] = CodiceFiscale::luogoLeggibile($esito);
            $dati['sesso'] = $esito['sesso'];
        }

        $this->merge($dati);
    }

    /** Minorenne alla data di oggi, dalla data di nascita (ricavata dal codice fiscale). */
    private function minorenne(): bool
    {
        $nascita = $this->input('data_nascita');

        return $nascita && Carbon::parse($nascita)->addYears(18)->isFuture();
    }

    public function rules(): array
    {
        $socioId = $this->route('socio')?->id;
        $minore = $this->minorenne();

        return [
            // Tessera interna: opzionale finché non si decide se emetterla (soci.md §3)
            'numero_tessera' => ['nullable', 'string', 'max:30',
                function ($attribute, $value, $fail) use ($socioId) {
                    // Confronto sul numero normalizzato ("1050" = "01050") e anche
                    // tra i soci eliminati, come nel Gestionale APS.
                    $numero = (int) preg_replace('/\D/', '', $value);
                    $occupato = Socio::withTrashed()
                        ->where('numero_tessera_int', $numero)
                        ->when($socioId, fn ($q) => $q->where('id', '!=', $socioId))
                        ->exists();
                    if ($occupato) {
                        $fail("Il numero di tessera {$value} è già assegnato a un altro socio.");
                    }
                },
            ],
            'tipo'                  => ['required', 'in:ordinario,giovane,minorenne'],
            'data_prima_iscrizione' => ['required', 'date', 'before_or_equal:today'],
            'scheda_registrazione_presente' => ['boolean'],

            'cognome'        => ['required', 'string', 'max:100'],
            'nome'           => ['required', 'string', 'max:100'],
            'data_nascita'   => ['nullable', 'date', 'before:today'],
            'luogo_nascita'  => ['nullable', 'string', 'max:100'],
            // Obbligatorio nel form: serve per il tesseramento CSEN.
            // L'unicità (anche tra i soci eliminati) è controllata nel
            // controller, per poter proporre il ripristino (soci.md §3).
            'codice_fiscale' => ['required', new CodiceFiscaleValido()],
            'sesso'          => ['nullable', 'in:M,F,altro'],
            'nazionalita'    => ['nullable', 'string', 'max:50'],

            // Residenza obbligatoria (Paolo, 30/09/2026)
            'indirizzo' => ['required', 'string', 'max:255'],
            'cap'       => ['required', 'regex:/^\d{5}$/'],
            'citta'     => ['required', 'string', 'max:100'],
            'provincia' => ['required', 'regex:/^[A-Z]{2}$/'],

            // Email e un solo telefono, obbligatori per i maggiorenni; per un
            // minorenne sono facoltativi e obbligatori quelli del genitore.
            'email'     => [$minore ? 'nullable' : 'required', 'email', 'max:255'],
            'telefono'  => [$minore ? 'nullable' : 'required', 'string', 'max:30'],

            // Genitore o tutore: tutti i campi obbligatori per un minorenne.
            'tutore_nome'           => [$minore ? 'required' : 'nullable', 'string', 'max:200'],
            'tutore_codice_fiscale' => [$minore ? 'required' : 'nullable', new CodiceFiscaleValido(), 'different:codice_fiscale'],
            'tutore_relazione'      => [$minore ? 'required' : 'nullable', 'string', 'max:100'],
            'tutore_telefono'       => [$minore ? 'required' : 'nullable', 'string', 'max:30'],
            'tutore_email'          => [$minore ? 'required' : 'nullable', 'email', 'max:255'],

            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'codice_fiscale.required' => 'Il codice fiscale è obbligatorio (serve per il tesseramento CSEN).',
            'cap.regex' => 'Il CAP deve avere 5 cifre.',
            'provincia.regex' => 'La provincia va indicata con la sigla di 2 lettere (es. TR).',
            'tutore_codice_fiscale.different' => 'Il codice fiscale del genitore deve essere diverso da quello del socio.',
            'email.required' => "L'email è obbligatoria per un socio maggiorenne.",
            'telefono.required' => 'Il telefono è obbligatorio per un socio maggiorenne.',
            'tutore_nome.required' => 'Per un socio minorenne il nome del genitore o tutore è obbligatorio.',
            'tutore_codice_fiscale.required' => 'Per un socio minorenne il codice fiscale del genitore o tutore è obbligatorio.',
            'tutore_relazione.required' => 'Per un socio minorenne indica la relazione (genitore, tutore legale…).',
            'tutore_telefono.required' => 'Per un socio minorenne il telefono del genitore o tutore è obbligatorio.',
            'tutore_email.required' => "Per un socio minorenne l'email del genitore o tutore è obbligatoria.",
            'data_prima_iscrizione.before_or_equal' => 'La data di iscrizione non può essere nel futuro.',
            'data_nascita.before'     => 'La data di nascita deve essere nel passato.',
        ];
    }

    public function attributes(): array
    {
        return [
            'data_prima_iscrizione' => 'data di iscrizione',
            'data_nascita' => 'data di nascita',
            'luogo_nascita' => 'luogo di nascita',
            'tutore_email' => 'email del genitore o tutore',
            'tutore_nome' => 'nome del genitore o tutore',
            'tutore_codice_fiscale' => 'codice fiscale del genitore o tutore',
            'tutore_relazione' => 'relazione',
            'tutore_telefono' => 'telefono del genitore o tutore',
            'citta' => 'comune',
            'cap' => 'CAP',
        ];
    }
}
