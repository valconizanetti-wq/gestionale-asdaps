<?php

namespace App\Http\Controllers;

use App\Models\VotoCd;
use App\Services\VotazioneCd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pagina di voto del consigliere, dal link personale dell'email (statuto
 * v1.0 art. 16.4; tavole "Voto 1–3"). Senza login: il link è la chiave.
 * GET mostra, POST agisce (i filtri antispam aprono da soli i link).
 * Dopo il voto si torna al GET, che mostra il voto registrato: ricaricare
 * la pagina non vota due volte.
 */
class VotoCdController extends Controller
{
    /** Lunghezza minima della motivazione del voto contrario. */
    public const MOTIVO_MIN = 20;

    public function __construct(private VotazioneCd $votazione)
    {
    }

    public function mostra(string $token): View
    {
        $voto = VotoCd::perToken($token);
        if (! $voto) {
            return view('voto-cd.chiuso', ['voto' => null]);
        }

        $domanda = $voto->domanda;
        if ($voto->haVotato()) {
            return view('voto-cd.registrato', ['voto' => $voto, 'domanda' => $domanda]);
        }
        if (! $domanda->isInAttesa() || $domanda->terminiCdScaduti()) {
            return view('voto-cd.chiuso', ['voto' => $voto, 'domanda' => $domanda]);
        }

        return view('voto-cd.scelta', ['voto' => $voto, 'domanda' => $domanda, 'token' => $token, 'minimo' => self::MOTIVO_MIN]);
    }

    public function vota(Request $request, string $token): RedirectResponse
    {
        $voto = VotoCd::perToken($token);
        if (! $voto) {
            return redirect()->route('voto-cd.mostra', $token);
        }

        $dati = $request->validate([
            'voto' => ['required', 'in:favorevole,contrario'],
            'motivo' => ['nullable', 'required_if:voto,contrario', 'string', 'min:' . self::MOTIVO_MIN, 'max:1000'],
        ], [
            'voto.required' => 'Scegli favorevole o contrario.',
            'motivo.required_if' => 'Per il voto contrario serve la motivazione.',
            'motivo.min' => 'La motivazione deve avere almeno :min caratteri.',
            'motivo.max' => 'La motivazione non deve superare :max caratteri.',
        ], ['motivo' => 'motivazione']);

        $this->votazione->vota($voto, $dati['voto'], isset($dati['motivo']) ? trim($dati['motivo']) : null, $request);

        // Registrato o no (votazione chiusa nel frattempo), il GET mostra lo stato attuale.
        return redirect()->route('voto-cd.mostra', $token);
    }
}
