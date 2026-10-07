<?php

namespace App\Http\Controllers;

use App\Models\Promemoria;
use App\Models\PromemoriaInvio;
use App\Promemoria\Invii;
use App\Promemoria\Registro;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Promemoria (promemoria.md, tavole PR-1 e PR-2). La pagina è sotto
 * 'permesso:promemoria'; ogni promemoria si modifica solo con il permesso di
 * modifica della sezione della sua funzione (le quote: rinnovi_quota).
 */
class PromemoriaController extends Controller
{
    public function __construct(private Invii $invii)
    {
    }

    public function index(Request $request)
    {
        $utente = $request->user();
        $funzioni = collect(Registro::tutte())->filter(fn ($f) => $utente->puoAccedere($f->sezione()));

        return view('promemoria.index', [
            'funzioni' => $funzioni,
            // Nell'ordine dell'anno sociale (collaudo K9): prima quelli che
            // riguardano l'anno dopo (l'invito del 15 dicembre), poi per data
            'promemoria' => Promemoria::whereIn('funzione', $funzioni->keys())->orderBy('funzione')->get()
                ->sortBy(fn ($p) => sprintf('%d-%02d-%02d', $p->parametro('anno_sociale') === 'successivo' ? 0 : 1, $p->mese ?? 13, $p->giorno ?? 32))
                ->groupBy('funzione'),
            'utente' => $utente,
        ]);
    }

    public function create(Request $request)
    {
        $f = Registro::trova($request->query('funzione'));
        abort_unless($f, 404);
        $this->richiediModifica($request, $f->sezione());
        $p = new Promemoria(['funzione' => $f->chiave(), 'quando' => 'data_fissa', 'ora' => '09:00:00', 'attivo' => true,
            'giorno' => 1, 'mese' => now()->month, 'parametri' => array_map(fn ($d) => array_key_first($d['opzioni']), $f->parametri())]);

        return view('promemoria.edit', $this->dati($p, $f) + ['puoModificare' => true]);
    }

    public function store(Request $request)
    {
        $f = Registro::trova($request->input('funzione'));
        abort_unless($f, 422);
        $this->richiediModifica($request, $f->sezione());
        $p = Promemoria::create($this->valida($request, $f) + ['funzione' => $f->chiave(), 'creato_da' => $request->user()->id]);

        return redirect()->route('promemoria.edit', $p)->with('success', "Promemoria «{$p->nome}» creato.");
    }

    public function edit(Request $request, Promemoria $promemoria)
    {
        $f = $promemoria->funzioneOggetto();
        abort_unless($f && $request->user()->puoAccedere($f->sezione()), 403);

        return view('promemoria.edit', $this->dati($promemoria, $f) + ['puoModificare' => $request->user()->puoModificare($f->sezione())]);
    }

    public function update(Request $request, Promemoria $promemoria)
    {
        $f = $promemoria->funzioneOggetto();
        abort_unless($f, 404);
        $this->richiediModifica($request, $f->sezione());
        $promemoria->update($this->valida($request, $f));

        return redirect()->route('promemoria.edit', $promemoria)->with('success', 'Promemoria salvato.');
    }

    public function destroy(Request $request, Promemoria $promemoria)
    {
        $f = $promemoria->funzioneOggetto();
        abort_unless($f, 404);
        $this->richiediModifica($request, $f->sezione());
        if ($promemoria->invii()->where('esito', '!=', 'prova')->exists()) {
            return back()->with('error', 'Il promemoria ha già degli invii: non si elimina, si spegne (resta lo storico).');
        }
        $nome = $promemoria->nome;
        $promemoria->delete();

        return redirect()->route('promemoria.index')->with('success', "Promemoria «{$nome}» eliminato.");
    }

    public function prova(Request $request, Promemoria $promemoria)
    {
        $f = $promemoria->funzioneOggetto();
        abort_unless($f, 404);
        $this->richiediModifica($request, $f->sezione());
        if (! $request->user()->email) {
            return back()->with('error', 'Il tuo utente non ha un indirizzo email.');
        }

        return $this->invii->prova($promemoria, $request->user())
            ? back()->with('success', "Prova inviata a {$request->user()->email} con i dati del primo destinatario (oggetto con «[Prova]»).")
            : back()->with('error', 'Invio della prova non riuscito: controlla il registro delle email.');
    }

    // ----------------------------------------------------------------

    private function dati(Promemoria $p, \App\Promemoria\Funzione $f): array
    {
        // Destinatari al prossimo invio (il suo anno sociale), con la situazione di oggi
        $riferimento = $p->exists && $p->attivo ? (Invii::prossimo($p) ?? now()) : now();
        $riferimento = $riferimento->lt(now()) ? now() : $riferimento;
        $destinatari = $this->invii->daAvvisare($p, $riferimento);

        $esempio = $destinatari->first(fn ($d) => $d->email) ?? $destinatari->first();

        return [
            'p' => $p,
            'f' => $f,
            'destinatari' => $destinatari,
            'riferimento' => $riferimento,
            'esempio' => $esempio,
            'prossimo' => $p->exists ? Invii::prossimo($p) : null,
            'storico' => $p->exists ? PromemoriaInvio::where('promemoria_id', $p->id)
                ->selectRaw("DATE(inviato_il) AS giorno, periodo, SUM(esito = 'inviato') AS inviati, SUM(esito = 'fallito') AS falliti, SUM(esito = 'senza_email') AS senza, SUM(esito = 'prova') AS prove")
                ->groupBy('giorno', 'periodo')->orderByDesc('giorno')->limit(20)->get() : collect(),
            'senzaEmail' => $p->exists ? PromemoriaInvio::where('promemoria_id', $p->id)->where('esito', 'senza_email')
                ->with('socio:id,nome,cognome')->orderByDesc('inviato_il')->limit(30)->get() : collect(),
        ];
    }

    private function valida(Request $request, \App\Promemoria\Funzione $f): array
    {
        $parametri = [];
        $regole = [
            'nome' => ['required', 'string', 'max:150'],
            'attivo' => ['nullable', 'boolean'],
            'quando' => ['required', Rule::in($f->quandoAmmessi())],
            'giorno' => ['required_if:quando,data_fissa', 'nullable', 'integer', 'between:1,31'],
            'mese' => ['required_if:quando,data_fissa', 'nullable', 'integer', 'between:1,12'],
            'giorni' => ['required_if:quando,relativa', 'nullable', 'integer', 'between:-365,365'],
            'ora' => ['required', 'date_format:H:i'],
            'oggetto' => ['required', 'string', 'max:200'],
            'testo' => ['required', 'string', 'max:5000'],
        ];
        foreach ($f->parametri() as $chiave => $def) {
            $regole["parametri.{$chiave}"] = ['required', Rule::in(array_keys($def['opzioni']))];
        }
        $dati = $request->validate($regole, [
            'giorno.between' => 'Il giorno va da 1 a 31.',
        ], ['giorni' => 'giorni', 'oggetto' => 'oggetto', 'testo' => 'testo']);

        foreach ($f->parametri() as $chiave => $def) {
            $parametri[$chiave] = $dati['parametri'][$chiave];
        }
        if (($dati['quando'] === 'data_fissa') && $dati['giorno'] > \Illuminate\Support\Carbon::create(2024, (int) $dati['mese'], 1)->daysInMonth) {
            throw \Illuminate\Validation\ValidationException::withMessages(['giorno' => 'Questo mese non ha un giorno ' . $dati['giorno'] . '.']);
        }

        return [
            'nome' => trim($dati['nome']),
            'attivo' => $request->boolean('attivo'),
            'quando' => $dati['quando'],
            'giorno' => $dati['quando'] === 'data_fissa' ? (int) $dati['giorno'] : null,
            'mese' => $dati['quando'] === 'data_fissa' ? (int) $dati['mese'] : null,
            'giorni' => $dati['quando'] === 'relativa' ? (int) $dati['giorni'] : null,
            'ora' => $dati['ora'] . ':00',
            'parametri' => $parametri,
            'oggetto' => trim($dati['oggetto']),
            'testo' => str_replace("\r\n", "\n", trim($dati['testo'])),
        ];
    }

    private function richiediModifica(Request $request, string $sezione): void
    {
        abort_unless($request->user()->puoModificare($sezione), 403, 'Il tuo ruolo non modifica i promemoria di questa funzione.');
    }
}
