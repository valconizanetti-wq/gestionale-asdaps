<?php

namespace App\Http\Controllers;

use App\Models\Domanda;
use App\Models\PrivacyLink;
use App\Services\Documenti;
use App\Services\LinkPrivacy;
use App\Services\ProfiloEnte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pagina del link personale per l'informativa privacy e foto e video
 * (privacy-link.md §3; tavole "Link privacy 1–2"). Senza login: il link è
 * la chiave. GET mostra, POST registra e torna al GET, che mostra il
 * riepilogo: ricaricare non registra due volte.
 */
class PrivacyLinkController extends Controller
{
    public function __construct(private LinkPrivacy $servizio)
    {
    }

    public function mostra(string $token): View
    {
        $link = PrivacyLink::perToken($token);
        if ($link?->isUsato()) {
            return view('privacy.registrata', ['link' => $link, 'socio' => $link->socio]);
        }
        if (! $link || ! $link->isValido()) {
            return view('privacy.non-valido');
        }

        $socio = $link->socio;
        $versione = ProfiloEnte::versione('informativa');
        $eta = $socio->data_nascita?->age;

        return view('privacy.pagina', [
            'link' => $link,
            'socio' => $socio,
            'token' => $token,
            'versione' => $versione,
            'html' => Documenti::html('informativa', $versione),
            'perTutore' => $link->perTutore(),
            // Minore dai 14 anni: il testo su foto e video dice che il genitore
            // ne ha parlato con lui o lei (come l'assenso della domanda).
            'assensoMinore' => $link->perTutore() && $eta !== null && $eta >= 14,
            'eta' => $eta,
            'precedente' => $socio->privacy_accettata_il && $socio->privacy_versione !== $versione ? $socio : null,
            'normaImmagine' => Domanda::NORMA_IMMAGINE,
        ]);
    }

    public function conferma(Request $request, string $token): RedirectResponse
    {
        $link = PrivacyLink::perToken($token);
        if (! $link || ! $link->isValido()) {
            return redirect()->route('privacy-link.mostra', $token);
        }

        $request->validate([
            'dichiarazione' => ['accepted'],
            'foto_video' => ['nullable', 'boolean'],
        ], [
            'dichiarazione.accepted' => 'Per confermare serve la dichiarazione di lettura.',
        ]);

        $this->servizio->registra($link, $request->boolean('foto_video'), $request);

        // Registrato o no (link sostituito nel frattempo), il GET mostra lo stato attuale.
        return redirect()->route('privacy-link.mostra', $token);
    }
}
