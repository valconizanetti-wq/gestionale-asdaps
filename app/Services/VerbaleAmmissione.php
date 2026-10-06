<?php

namespace App\Services;

use App\Models\Domanda;
use App\Models\Verbale;

/**
 * Bozza di verbale della consultazione telematica del CD su una domanda
 * (statuto v1.0 artt. 6.4 e 16.6): esito, voto di ciascun consigliere con
 * data, ora e identificativo del collegamento (art. 16.4 lett. e),
 * motivazioni dei voti contrari, eventuale decisione del Presidente e
 * motivazione del diniego.
 *
 * Si crea alla chiusura della votazione e si aggiorna finché è in bozza
 * (diniego inviato, decisione del Presidente). Un verbale approvato non si
 * tocca più. Il testo è HTML generato da una vista con i dati escapati.
 */
class VerbaleAmmissione
{
    public function aggiorna(Domanda $domanda): ?Verbale
    {
        $domanda->loadMissing(['voti.membroCd', 'verbaleCd']);
        $verbale = $domanda->verbaleCd;

        if ($verbale && $verbale->stato !== 'bozza') {
            return $verbale;
        }

        $dati = [
            'tipo' => 'cd_ammissione',
            'domanda_id' => $domanda->id,
            'titolo' => 'Consultazione telematica del Consiglio Direttivo — domanda di adesione di ' . $domanda->nomeCognome(),
            'periodo_da' => $domanda->created_at?->toDateString(),
            'periodo_a' => $domanda->votazione_chiusa_il?->toDateString(),
            'data_seduta' => $domanda->votazione_chiusa_il?->toDateString(),
            'presenze_cd' => $domanda->voti->map(fn ($v) => [
                'nome' => $v->consigliere_nome,
                'presidente' => (bool) $v->presidente,
                'voto' => $v->voto,
            ])->values()->all(),
            'corpo_html' => view('verbali.ammissione-telematica', ['domanda' => $domanda])->render(),
            'stato' => 'bozza',
        ];

        if ($verbale) {
            $verbale->forceFill($dati)->save();
        } else {
            $verbale = new Verbale();
            $verbale->forceFill($dati)->save();
            $domanda->forceFill(['verbale_cd_id' => $verbale->id])->save();
        }

        return $verbale;
    }
}
