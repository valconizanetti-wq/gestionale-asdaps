<?php

namespace App\Http\Requests;

use App\Rules\CodiceFiscaleValido;
use App\Services\CodiceFiscale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Passo 1 della domanda online: dati del richiedente o, per un minorenne,
 * del genitore e del minore. Data, sesso e luogo di nascita non si
 * chiedono: si ricavano dal codice fiscale (IscrizioneOnline::prepara).
 */
class IscrizioneDatiRequest extends FormRequest
{
    public const DOCUMENTI = ['carta_identita' => "Carta d'identità", 'patente' => 'Patente', 'passaporto' => 'Passaporto'];

    public function authorize(): bool
    {
        return true; // modulo pubblico
    }

    protected function prepareForValidation(): void
    {
        $maiuscolo = fn ($v) => is_string($v) ? CodiceFiscale::normalizza($v) : $v;
        $pulito = fn ($v) => is_string($v) ? trim(preg_replace('/\s+/', ' ', $v)) : $v;

        $dati = [];
        foreach (['nome', 'cognome', 'genitore_nome', 'genitore_cognome', 'indirizzo', 'citta', 'documento_numero'] as $k) {
            $dati[$k] = $pulito($this->input($k));
        }
        foreach (['codice_fiscale', 'genitore_codice_fiscale'] as $k) {
            $dati[$k] = $maiuscolo($this->input($k));
        }
        $dati['provincia'] = is_string($this->input('provincia')) ? strtoupper(trim($this->input('provincia'))) : null;
        $dati['documento_numero'] = is_string($dati['documento_numero']) ? strtoupper(str_replace(' ', '', $dati['documento_numero'])) : null;
        foreach (['email', 'genitore_email'] as $k) {
            $dati[$k] = is_string($this->input($k)) ? strtolower(trim($this->input($k))) : null;
        }

        $this->merge($dati);
    }

    public function rules(): array
    {
        $minore = $this->input('chi') === 'minore';
        $obbl = fn (bool $se) => $se ? 'required' : 'nullable';

        // Nomi e città finiscono nelle email markdown ai consiglieri: solo
        // lettere, spazi, apostrofi, trattini e punti, così un nome come
        // "[Vota qui](https://…)" non diventa un link (v0.30.0).
        $nomeProprio = 'regex:/^[\pL\pM][\pL\pM\' .’-]*$/u';

        return [
            'chi' => ['required', 'in:me,minore'],

            // Richiedente (adulto) o minore
            'nome' => ['required', 'string', 'max:100', $nomeProprio],
            'cognome' => ['required', 'string', 'max:100', $nomeProprio],
            'codice_fiscale' => ['required', 'string', new CodiceFiscaleValido()],
            'email' => [$obbl(! $minore), 'email:rfc', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +().\/-]{6,30}$/'],

            // Genitore, solo per i minorenni
            'genitore_nome' => [$obbl($minore), 'string', 'max:100', $nomeProprio],
            'genitore_cognome' => [$obbl($minore), 'string', 'max:100', $nomeProprio],
            'genitore_codice_fiscale' => [$obbl($minore), 'string', new CodiceFiscaleValido(), 'different:codice_fiscale'],
            'genitore_email' => [$obbl($minore), 'email:rfc', 'max:255'],
            'genitore_telefono' => [$obbl($minore), 'string', 'max:30', 'regex:/^[0-9 +().\/-]{6,30}$/'],

            // Documento di chi firma: il richiedente o il genitore
            'documento_tipo' => ['required', 'in:' . implode(',', array_keys(self::DOCUMENTI))],
            'documento_numero' => ['required', 'string', 'min:5', 'max:30', 'regex:/^[A-Z0-9]+$/'],

            // Residenza (del minore, se minorenne)
            'indirizzo' => ['required', 'string', 'max:255'],
            'cap' => ['required', 'digits:5'],
            'citta' => ['required', 'string', 'max:100', $nomeProprio],
            'provincia' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
        ];
    }

    /** Controlli che dipendono da più campi: età, soprattutto. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $minore = $this->input('chi') === 'minore';
            $richiedente = CodiceFiscale::analizza($this->input('codice_fiscale'));
            $maggiorenne = $richiedente['data_nascita']->addYears(18)->lessThanOrEqualTo(now()->startOfDay());

            if (! $minore && ! $maggiorenne) {
                $validator->errors()->add('chi', 'Dal codice fiscale risulta minorenne: la domanda va presentata da un genitore. Scegli «Minorenne» e compila i dati del genitore.');
            }
            if ($minore && $maggiorenne) {
                $validator->errors()->add('codice_fiscale', 'Dal codice fiscale la persona risulta maggiorenne: deve presentare la domanda per sé.');
            }
            if ($minore) {
                $genitore = CodiceFiscale::analizza($this->input('genitore_codice_fiscale'));
                if ($genitore['data_nascita']->addYears(18)->greaterThan(now()->startOfDay())) {
                    $validator->errors()->add('genitore_codice_fiscale', 'Dal codice fiscale il genitore risulta minorenne.');
                }
                if ($this->input('email') && $this->input('email') === $this->input('genitore_email')) {
                    $validator->errors()->add('email', "L'email del minore è facoltativa: se è la stessa del genitore, lasciala vuota.");
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'codice_fiscale' => 'codice fiscale',
            'genitore_codice_fiscale' => 'codice fiscale del genitore',
            'genitore_nome' => 'nome del genitore',
            'genitore_cognome' => 'cognome del genitore',
            'genitore_email' => 'email del genitore',
            'genitore_telefono' => 'telefono del genitore',
            'documento_tipo' => 'tipo di documento',
            'documento_numero' => 'numero del documento',
            'citta' => 'comune',
            'cap' => 'CAP',
        ];
    }

    public function messages(): array
    {
        // Messaggi in italiano anche senza il pacchetto di traduzioni lang/it.
        return [
            'required' => 'Il campo :attribute è obbligatorio.',
            'email' => "L'indirizzo email non è valido.",
            'max' => 'Il campo :attribute è troppo lungo.',
            'min' => 'Il campo :attribute è troppo corto.',
            'digits' => 'Il :attribute deve avere :digits cifre.',
            'in' => 'Scegli un valore tra quelli proposti.',
            'string' => 'Il campo :attribute non è valido.',
            'chi.required' => 'Indica per chi è la domanda.',
            'genitore_codice_fiscale.different' => 'Il codice fiscale del genitore deve essere diverso da quello del minore.',
            'documento_numero.regex' => 'Il numero del documento può contenere solo lettere e cifre.',
            'provincia.regex' => 'Scrivi la sigla della provincia, due lettere (es. TR).',
            'telefono.regex' => 'Il numero di telefono non è valido.',
            'genitore_telefono.regex' => 'Il numero di telefono non è valido.',
            'nome.regex' => 'Il nome può contenere solo lettere, spazi, apostrofi e trattini.',
            'cognome.regex' => 'Il cognome può contenere solo lettere, spazi, apostrofi e trattini.',
            'genitore_nome.regex' => 'Il nome può contenere solo lettere, spazi, apostrofi e trattini.',
            'genitore_cognome.regex' => 'Il cognome può contenere solo lettere, spazi, apostrofi e trattini.',
            'citta.regex' => 'Il comune può contenere solo lettere, spazi, apostrofi e trattini.',
        ];
    }
}
