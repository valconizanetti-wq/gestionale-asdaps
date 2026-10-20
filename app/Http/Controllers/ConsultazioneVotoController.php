<?php

namespace App\Http\Controllers;

use App\Models\ConsultazioneVoto;
use App\Models\OrganoDocumento;
use App\Services\Consultazioni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Pagina del voto di una consultazione telematica dal link personale
 * (v0.44.0, tavola CT-2, statuto art. 16.4). Senza login: il link è la
 * chiave. GET mostra, POST vota; dopo il voto si torna al GET, che mostra il
 * voto registrato (ricaricare non vota due volte).
 */
class ConsultazioneVotoController extends Controller
{
    public function __construct(private Consultazioni $consultazioni)
    {
    }

    public function mostra(string $token): View
    {
        $voto = ConsultazioneVoto::perToken($token);
        $c = $voto?->consultazione;
        $stato = match (true) {
            ! $voto => 'non_valido',
            $voto->haVotato() => 'votato',
            Consultazioni::aperta($c) => 'aperta',
            default => 'chiusa',
        };

        return view('consultazione-voto.pagina', [
            'stato' => $stato,
            'voto' => $voto,
            'c' => $c,
            'token' => $token,
            'documenti' => $c ? $c->documenti() : collect(),
        ]);
    }

    public function documento(string $token, int $documento)
    {
        $voto = ConsultazioneVoto::perToken($token);
        abort_unless($voto, 404);
        $d = OrganoDocumento::where('allegabile_type', 'consultazione')->where('allegabile_id', $voto->consultazione_id)->findOrFail($documento);
        abort_unless(Storage::disk('local')->exists($d->path), 404);

        return Storage::disk('local')->response($d->path, $d->nome, ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'], 'inline');
    }

    public function vota(Request $request, string $token): RedirectResponse
    {
        $voto = ConsultazioneVoto::perToken($token);
        if (! $voto) {
            return redirect()->route('consultazione.mostra', $token);
        }
        $dati = $request->validate([
            'voto' => ['required', 'in:' . implode(',', array_keys(ConsultazioneVoto::VOTI))],
            'nota' => ['nullable', 'string', 'max:1000'],
        ], [
            'voto.required' => 'Scegli il tuo voto.',
            'voto.in' => 'Scegli il tuo voto.',
            'nota.max' => 'La nota non deve superare :max caratteri.',
        ]);
        $this->consultazioni->vota($voto, $dati['voto'], $dati['nota'] ?? null, $request);

        return redirect()->route('consultazione.mostra', $token);
    }
}
