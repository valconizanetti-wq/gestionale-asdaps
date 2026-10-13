<?php

namespace App\Promemoria\Funzioni;

use App\Models\Promemoria;
use App\Models\Scadenza;
use App\Promemoria\Destinatario;
use App\Promemoria\Funzione;
use App\Promemoria\Interni;
use App\Support\Importo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Scadenze dell'associazione (v0.38.0, scadenzario.md §6, tavola PR-3):
 * N giorni prima (o dopo) di ogni scadenza dello Scadenzario non ancora
 * rinnovata, un'email agli utenti scelti (di norma Presidente e Tesoriere).
 * Una per scadenza e per utente; con «Rinnovata» la data passa all'anno dopo
 * e il promemoria riparte da lì.
 */
class ScadenzeAssociazione implements Funzione
{
    public function chiave(): string
    {
        return 'scadenze_associazione';
    }

    public function nome(): string
    {
        return 'Scadenze dell\'associazione';
    }

    public function descrizione(): string
    {
        return 'Scadenze dello Scadenzario non ancora rinnovate (affiliazione, polizza, rendiconto…). Le email vanno a chi è scelto tra Presidente, Tesoriere e Segretario, non ai soci.';
    }

    public function sezione(): string
    {
        return 'scadenzario';
    }

    public function quandoAmmessi(): array
    {
        return ['relativa'];
    }

    public function parametri(): array
    {
        return ['destinatari' => Interni::parametro('Utenti del gestionale con quel ruolo, all\'email con cui entrano.')];
    }

    public function segnaposti(): array
    {
        return [
            'saluto' => 'Nome di chi riceve',
            'scadenza' => 'Titolo della scadenza',
            'data' => 'Data della scadenza',
            'giorni' => 'Quanto manca (es. «tra 30 giorni»)',
            'categoria' => 'Categoria',
            'ultimo_pagamento' => 'Data e importo dell\'ultimo rinnovo',
            'link' => 'Indirizzo dello Scadenzario',
        ];
    }

    /** Le date sono per destinatario (Destinatario::$periodo). */
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
        $link = route('scadenzario.index');

        return Scadenza::aperte()->with(['categoriaPrimaNota', 'ultimoRinnovo'])->orderBy('data_scadenza')->get()
            ->flatMap(function (Scadenza $s) use ($utenti, $quando, $link) {
                $r = $s->ultimoRinnovo;
                $valori = [
                    'scadenza' => $s->titolo,
                    'data' => $s->data_scadenza->format('d/m/Y'),
                    'giorni' => $s->stato($quando)['testo'],
                    'categoria' => $s->categoriaTesto(),
                    'ultimo_pagamento' => $r ? $r->fatto_il->format('d/m/Y') . ($r->importo !== null ? ' · ' . Importo::it($r->importo, true) : '') : 'nessuno registrato nel gestionale',
                    'link' => $link,
                ];

                return $utenti->map(fn ($u) => new Destinatario(
                    chiave: 'utente:' . $u->id,
                    socioId: null,
                    email: $u->email ?: null,
                    nome: $u->name,
                    etichetta: $u->name . " (" . $u->etichettaRuoli() . ") · {$s->titolo}",
                    valori: ['saluto' => Interni::saluto($u)] + $valori,
                    data: $s->data_scadenza->copy(),
                    periodo: 's' . $s->id . ':' . $s->data_scadenza->toDateString(),
                    cosa: $s->titolo,
                ));
            })->values();
    }

    public function esempio(Promemoria $p, Carbon $quando, \App\Models\User $u): array
    {
        $data = Interni::dataEsempio($p, $quando);
        // Stesso testo di «{giorni}» delle scadenze vere (Scadenza::stato)
        $finta = (new Scadenza())->forceFill(['data_scadenza' => $data, 'giorni_preavviso' => 30, 'completato' => false]);

        return [
            'saluto' => Interni::saluto($u),
            'scadenza' => 'Polizza RC',
            'data' => $data->format('d/m/Y'),
            'giorni' => $finta->stato($quando)['testo'],
            'categoria' => 'Polizze Assicurative',
            'ultimo_pagamento' => $data->copy()->subYear()->format('d/m/Y') . ' · ' . Importo::it(250, true),
            'link' => route('scadenzario.index'),
        ];
    }

    public function predefiniti(): array
    {
        $testo = "Ciao {saluto},\n\n«{scadenza}» scade il {data} ({giorni}).\nUltimo rinnovo: {ultimo_pagamento}.\n\n"
            . "Quando è pagata (o fatta), segnalalo con «Rinnovata» nello Scadenzario: il pagamento va in Prima Nota e la scadenza passa all'anno dopo.\n{link}";

        return [
            [
                'nome' => 'Scadenza in arrivo',
                'quando' => 'relativa', 'giorni' => -30, 'parametri' => ['destinatari' => 'presidente_tesoriere'],
                'oggetto' => '{scadenza}: scade il {data}',
                'testo' => $testo,
            ],
            [
                'nome' => 'Ultimi giorni',
                'quando' => 'relativa', 'giorni' => -7, 'parametri' => ['destinatari' => 'presidente_tesoriere'],
                'oggetto' => 'Ultimi giorni: {scadenza} scade il {data}',
                'testo' => $testo,
            ],
        ];
    }
}
