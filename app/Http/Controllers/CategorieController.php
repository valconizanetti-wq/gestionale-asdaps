<?php

namespace App\Http\Controllers;

use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Categorie e tag della Prima Nota (prima-nota.md §2, tavola PN-9).
 * Regole riprese da gestionale-acv: il tipo non cambia dopo la creazione,
 * una categoria usata non si elimina (si disattiva), le categorie con un
 * codice di sistema non si toccano. Rotte sotto 'permesso:prima_nota'.
 */
class CategorieController extends Controller
{
    public const NATURE = [
        'istituzionale' => 'Istituzionale',
        'da_verificare' => 'Da verificare (398)',
        'commerciale' => 'Commerciale',
    ];

    public function index()
    {
        $categorie = PrimaNotaCategoria::withCount('movimenti')
            ->orderBy('ordine')->orderBy('nome')->get()->groupBy('tipo');

        $tags = Tag::withCount('movimenti')
            ->withSum(['movimenti as uscite' => fn ($q) => $q->where('tipo', 'uscita')->whereNull('giroconto_id')], 'importo')
            ->withSum(['movimenti as entrate' => fn ($q) => $q->where('tipo', 'entrata')->whereNull('giroconto_id')], 'importo')
            ->orderBy('nome')->get();

        $anniTag = PrimaNota::whereNotNull('tag_id')->selectRaw('tag_id, GROUP_CONCAT(DISTINCT YEAR(data) ORDER BY YEAR(data)) AS anni')
            ->groupBy('tag_id')->pluck('anni', 'tag_id');

        return view('prima-nota.categorie', [
            'uscite' => $categorie->get('uscita', collect()),
            'entrate' => $categorie->get('entrata', collect()),
            'tags' => $tags,
            'anniTag' => $anniTag,
        ]);
    }

    public function store(Request $request)
    {
        $this->richiediModifica($request);
        // Errori in un "bag" per finestra: la pagina riapre la finestra
        // giusta con il messaggio, invece di chiuderla (collaudo J2).
        $dati = $request->validateWithBag('nuovaCategoria', [
            'tipo' => ['required', 'in:entrata,uscita'],
            'nome' => ['required', 'string', 'max:150', $this->nomeUnico($request->input('tipo'))],
            'natura' => ['required', Rule::in(array_keys(self::NATURE))],
        ], [], ['nome' => 'nome della categoria']);

        PrimaNotaCategoria::create([
            'tipo' => $dati['tipo'],
            'nome' => trim($dati['nome']),
            'uso' => 'movimenti',
            'attiva' => true,
            'ordine' => (int) PrimaNotaCategoria::where('tipo', $dati['tipo'])->where('uso', '!=', 'sistema')->max('ordine') + 10,
            'creata_da' => $request->user()->id,
        ] + $this->natura($dati['natura']));

        return redirect()->route('prima-nota.categorie.index')->with('success', 'Categoria «' . trim($dati['nome']) . '» aggiunta.');
    }

    public function update(Request $request, PrimaNotaCategoria $categoria)
    {
        $this->richiediModifica($request);
        $this->nonDiSistema($categoria);

        $dati = $request->validateWithBag('categoria', [
            'nome' => ['required', 'string', 'max:150', $this->nomeUnico($categoria->tipo, $categoria->id)],
            'natura' => ['required', Rule::in(array_keys(self::NATURE))],
        ], [], ['nome' => 'nome della categoria']);

        $categoria->update(['nome' => trim($dati['nome'])] + $this->natura($dati['natura']));

        return redirect()->route('prima-nota.categorie.index')->with('success', 'Categoria aggiornata.');
    }

    /** Sposta su o giù di un posto tra le categorie dello stesso tipo. */
    public function sposta(Request $request, PrimaNotaCategoria $categoria)
    {
        $this->richiediModifica($request);
        $su = $request->input('verso') === 'su';

        $vicina = PrimaNotaCategoria::where('tipo', $categoria->tipo)
            ->where(fn ($q) => $su
                ? $q->where('ordine', '<', $categoria->ordine)->orWhere(fn ($w) => $w->where('ordine', $categoria->ordine)->where('id', '<', $categoria->id))
                : $q->where('ordine', '>', $categoria->ordine)->orWhere(fn ($w) => $w->where('ordine', $categoria->ordine)->where('id', '>', $categoria->id)))
            ->orderBy('ordine', $su ? 'desc' : 'asc')->orderBy('id', $su ? 'desc' : 'asc')
            ->first();

        if ($vicina) {
            $a = $categoria->ordine;
            $b = $vicina->ordine === $a ? $a + ($su ? -1 : 1) : $vicina->ordine;
            $categoria->update(['ordine' => $b]);
            $vicina->update(['ordine' => $a]);
        }

        return redirect()->to(route('prima-nota.categorie.index') . '#categoria-' . $categoria->id);
    }

    public function attiva(Request $request, PrimaNotaCategoria $categoria)
    {
        $this->richiediModifica($request);
        $this->nonDiSistema($categoria);
        $categoria->update(['attiva' => ! $categoria->attiva]);

        return redirect()->route('prima-nota.categorie.index')
            ->with('success', 'Categoria «' . $categoria->nome . '» ' . ($categoria->attiva ? 'attivata.' : 'disattivata: resta sui movimenti già registrati.'));
    }

    public function destroy(Request $request, PrimaNotaCategoria $categoria)
    {
        $this->richiediModifica($request);
        $this->nonDiSistema($categoria);

        if ($categoria->codice || $categoria->movimenti()->exists() || $categoria->vociBilancio()->exists()) {
            return back()->with('error', 'La categoria è già usata: non si elimina, si può disattivare.');
        }
        $nome = $categoria->nome;
        $categoria->delete();

        return redirect()->route('prima-nota.categorie.index')->with('success', "Categoria «{$nome}» eliminata.");
    }

    public function updateTag(Request $request, Tag $tag)
    {
        $this->richiediModifica($request);
        $dati = $request->validateWithBag('tag', [
            'nome' => ['required', 'string', 'max:100', function ($a, $v, $fail) use ($tag) {
                if (Tag::whereRaw('LOWER(nome) = ?', [mb_strtolower(trim($v))])->where('id', '!=', $tag->id)->exists()) {
                    $fail('Esiste già un tag con questo nome.');
                }
            }],
        ], [], ['nome' => 'nome del tag']);

        $tag->update(['nome' => trim(preg_replace('/\s+/u', ' ', $dati['nome']))]);

        return redirect()->route('prima-nota.categorie.index')->with('success', 'Tag rinominato.');
    }

    public function destroyTag(Request $request, Tag $tag)
    {
        $this->richiediModifica($request);
        if ($tag->movimenti()->exists()) {
            return back()->with('error', 'Il tag è usato da almeno un movimento: toglilo prima dai movimenti.');
        }
        $nome = $tag->nome;
        $tag->delete();

        return redirect()->route('prima-nota.categorie.index')->with('success', "Tag «{$nome}» eliminato.");
    }

    // ----------------------------------------------------------------

    private function natura(string $natura): array
    {
        return [
            'flag_non_istituzionale' => $natura === 'commerciale',
            'natura_da_verificare' => $natura === 'da_verificare',
        ];
    }

    private function nomeUnico(?string $tipo, ?int $escludi = null): \Closure
    {
        return function ($a, $v, $fail) use ($tipo, $escludi) {
            $esiste = PrimaNotaCategoria::where('tipo', $tipo)
                ->whereRaw('LOWER(nome) = ?', [mb_strtolower(trim((string) $v))])
                ->when($escludi, fn ($q) => $q->where('id', '!=', $escludi))->exists();
            if ($esiste) {
                $fail('Esiste già una categoria ' . ($tipo === 'entrata' ? 'di entrata' : 'di uscita') . ' con questo nome.');
            }
        };
    }

    private function nonDiSistema(PrimaNotaCategoria $categoria): void
    {
        abort_if($categoria->isSistema(), 403, 'Categoria di sistema: non si modifica.');
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('prima_nota'), 403, 'Il tuo ruolo può solo consultare la Prima Nota.');
    }
}
