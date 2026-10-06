<?php

namespace App\Http\Requests;

use App\Models\Domanda;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Passo 2: dichiarazioni. Una sola dichiarazione obbligatoria copre
 * domanda di ammissione, statuto e clausola arbitrale, tesseramento CSEN,
 * polizza base e (per i minori) autorizzazione alle attività
 * (domande-iscrizione.md §3). La presa visione dell'informativa è a parte;
 * foto e video sono facoltativi. Per un minore che ha compiuto 14 anni
 * serve anche la sua dichiarazione (assenso al tesseramento, art. 16, c. 2,
 * D.Lgs. 36/2021; v0.31.0).
 */
class IscrizioneInvioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Dati del passo 1 in sessione (IscrizioneController::SESSIONE), o null
     * se la sessione è scaduta: il controller poi rimanda al passo 1.
     */
    private function datiInSessione(): ?array
    {
        return $this->session()->get('iscrizione.dati.dati');
    }

    /**
     * Serve l'assenso del minore? Età calcolata adesso, al momento
     * dell'invio: se il minore compie 14 anni tra il passo 1 e l'invio, la
     * dichiarazione diventa obbligatoria (la pagina la mostra già, perché
     * la calcola allo stesso modo quando si apre).
     */
    public function assensoRichiesto(): bool
    {
        $d = $this->datiInSessione();

        return $d !== null && ($d['minore'] ?? false) && Domanda::assensoRichiesto($d['data_nascita'] ?? null);
    }

    public function rules(): array
    {
        return [
            'assenso_minore' => $this->assensoRichiesto() ? ['accepted'] : ['nullable'],
            'dichiarazione' => ['accepted'],
            'informativa' => ['accepted'],
            'foto_video' => ['nullable', 'boolean'],
            // Statuto e informativa aperti (finestra o pagina): si registra
            // se è successo, non è obbligatorio. Basta la dichiarazione
            // (decisione di Paolo, 29/09/2026).
            'statuto_aperto' => ['nullable', 'boolean'],
            'informativa_aperta' => ['nullable', 'boolean'],
            // Campo trappola per i bot: invisibile, deve restare vuoto.
            'sito_web' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'dichiarazione.accepted' => 'Per inviare la domanda devi spuntare la dichiarazione.',
            'informativa.accepted' => "Per inviare la domanda devi dichiarare di aver letto l'informativa.",
            'sito_web.prohibited' => 'Invio non valido.',
            'assenso_minore.accepted' => 'Serve la dichiarazione di ' . ($this->datiInSessione()['nome'] ?? 'chi diventa socio')
                . ': dai ' . Domanda::ETA_ASSENSO_MINORE . ' anni il tesseramento richiede il suo assenso.',
        ];
    }
}
