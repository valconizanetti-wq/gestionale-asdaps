<?php

namespace App\Promemoria\Funzioni;

use App\Models\Promemoria;
use App\Models\Socio;
use App\Models\Tesseramento;
use App\Promemoria\Destinatario;
use App\Promemoria\Funzione;
use App\Promemoria\Interni;
use App\Services\Tessere;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tessere in scadenza (v0.38.0, tessere.md, tavola PR-3): N giorni prima
 * della scadenza delle tessere CSEN non ancora rinnovate dei soci attivi,
 * un'email agli utenti scelti (di norma il Tesoriere) con l'elenco e la
 * situazione della quota di ciascun socio.
 *
 * Le tessere che scadono lo stesso giorno (es. caricate tutte insieme)
 * arrivano in una sola email: un destinatario per utente e per giorno di
 * scadenza. Una tessera emessa dopo l'invio con la stessa scadenza non fa
 * ripartire il promemoria (compare nella pagina Tessere, «Da rinnovare»).
 */
class TessereInScadenza implements Funzione
{
    public function chiave(): string
    {
        return 'tessere_in_scadenza';
    }

    public function nome(): string
    {
        return 'Tessere in scadenza';
    }

    public function descrizione(): string
    {
        return 'Tessere CSEN dei soci attivi che scadono e non sono ancora rinnovate, con la situazione della quota. Le email vanno a chi è scelto tra Presidente, Tesoriere e Segretario, non ai soci.';
    }

    public function sezione(): string
    {
        return 'tesseramento';
    }

    public function quandoAmmessi(): array
    {
        return ['relativa'];
    }

    public function parametri(): array
    {
        return ['destinatari' => Interni::parametro('Utenti del gestionale con quel ruolo, all\'email con cui entrano. Le tessere che scadono lo stesso giorno arrivano in una sola email.')];
    }

    public function segnaposti(): array
    {
        return [
            'saluto' => 'Nome di chi riceve',
            'data' => 'Giorno di scadenza delle tessere',
            'numero' => 'Quante sono (es. «3 tessere»)',
            'elenco' => 'Socio, numero di tessera e quota, una riga per tessera',
            'link' => 'Indirizzo della pagina Tessere',
        ];
    }

    public function periodo(Promemoria $p, Carbon $quando): string
    {
        return (string) $quando->year;
    }

    public function destinatari(Promemoria $p, Carbon $quando): Collection
    {
        $utenti = Interni::utenti($p->parametro('destinatari'));
        if ($utenti->isEmpty()) {
            return collect();
        }
        $link = route('tesseramento.index');

        // Ultima tessera di ogni socio attivo: se è già rinnovata, l'ultima è
        // la nuova, che scade l'anno dopo.
        $soci = Socio::where('stato', 'attivo')->where('pseudonimizzato', false)->get()->keyBy('id');
        $ultime = Tesseramento::whereIn('socio_id', $soci->keys())->whereNotNull('scadenza')->whereNotNull('data_rilascio')
            ->orderBy('scadenza')->orderBy('id')->get()
            ->groupBy('socio_id')->map(fn ($t) => $t->last());

        return $ultime
            ->groupBy(fn (Tesseramento $t) => $t->scadenza->toDateString())
            ->sortKeys()
            ->flatMap(function (Collection $tessere, string $giorno) use ($utenti, $soci, $quando, $link) {
                $tessere = $tessere->sortBy(fn ($t) => $soci[$t->socio_id]->cognome . ' ' . $soci[$t->socio_id]->nome)->values();
                $data = Carbon::parse($giorno);
                $elenco = $tessere->map(function (Tesseramento $t) use ($soci, $data, $quando) {
                    $s = $soci[$t->socio_id];
                    $quota = Tessere::quota($s, $data->copy()->addDay(), $quando)['titolo'];

                    return "– {$s->cognome} {$s->nome}, tessera {$t->numero_tessera_csen} · {$quota}";
                })->implode("\n");
                $n = $tessere->count();
                $primo = $soci[$tessere->first()->socio_id];
                $cosa = $n === 1 ? "{$primo->cognome} {$primo->nome}" : "{$n} tessere";

                return $utenti->map(fn ($u) => new Destinatario(
                    chiave: 'utente:' . $u->id,
                    socioId: null,
                    email: $u->email ?: null,
                    nome: $u->name,
                    etichetta: $u->name . ' (' . $u->etichettaRuoli() . ") · {$cosa} al " . $data->format('d/m/Y'),
                    valori: [
                        'saluto' => Interni::saluto($u),
                        'data' => $data->format('d/m/Y'),
                        'numero' => $n === 1 ? '1 tessera' : "{$n} tessere",
                        'elenco' => $elenco,
                        'link' => $link,
                    ],
                    data: $data,
                    periodo: 't:' . $giorno,
                    cosa: $cosa,
                ));
            })->values();
    }

    public function esempio(Promemoria $p, Carbon $quando, \App\Models\User $u): array
    {
        $data = Interni::dataEsempio($p, $quando);
        $anno = $data->copy()->addDay()->year;   // come Tessere::quota: anno del rinnovo

        return [
            'saluto' => Interni::saluto($u),
            'data' => $data->format('d/m/Y'),
            'numero' => '2 tessere',
            'elenco' => "– Rossi Mario, tessera {$data->format('y')}B0000001 · Quota {$anno} versata\n"
                . "– Verdi Anna, tessera {$data->format('y')}B0000002 · Quota {$anno} non ancora versata",
            'link' => route('tesseramento.index'),
        ];
    }

    public function predefiniti(): array
    {
        return [
            [
                'nome' => 'Tessere da rinnovare',
                'quando' => 'relativa', 'giorni' => -15, 'parametri' => ['destinatari' => 'tesoriere'],
                'oggetto' => 'Tessere CSEN in scadenza il {data}: {numero}',
                'testo' => "Ciao {saluto},\n\nil {data} scadono {numero}:\n\n{elenco}\n\n"
                    . "Senza tessera valida il socio non ha l'assicurazione base. Le rinnovi dalla pagina Tessere («Da rinnovare»):\n{link}",
            ],
        ];
    }
}
