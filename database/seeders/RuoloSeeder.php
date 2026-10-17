<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ruolo;

class RuoloSeeder extends Seeder
{
    public function run(): void
    {
        $ruoli = [
            ['codice' => 'presidente', 'label' => 'Presidente',  'colore_avatar' => '#8e44ad', 'badge_class' => 'bg-purple', 'ordine' => 1],
            // Vicepresidente (v0.42.0, statuto art. 15.2; analisi-collaudo-O.md §11)
            ['codice' => 'vicepresidente', 'label' => 'Vicepresidente', 'colore_avatar' => '#5b6abf', 'badge_class' => 'bg-indigo', 'ordine' => 2],
            ['codice' => 'segretario', 'label' => 'Segretario',  'colore_avatar' => '#2980b9', 'badge_class' => 'bg-blue',   'ordine' => 3],
            ['codice' => 'tesoriere',  'label' => 'Tesoriere',   'colore_avatar' => '#27ae60', 'badge_class' => 'bg-green',  'ordine' => 4],
            // Membri del CD senza carica (v0.39.0, scontrini.md §6): caricano
            // gli scontrini dal telefono e vedono la Prima Nota in lettura.
            ['codice' => 'consigliere', 'label' => 'Consigliere', 'colore_avatar' => '#16a085', 'badge_class' => 'bg-teal',  'ordine' => 5],
            ['codice' => 'revisore',   'label' => 'Revisore',    'colore_avatar' => '#d35400', 'badge_class' => 'bg-orange', 'ordine' => 6],
            // Amministratore di sistema (v0.42.0, analisi-collaudo-O.md §5 e §10):
            // legge tutto, scrive solo Utenti; si somma ai ruoli tranne
            // Presidente e Revisore.
            ['codice' => 'amministratore', 'label' => 'Amministratore', 'colore_avatar' => '#5d6d7e', 'badge_class' => 'bg-grey', 'ordine' => 7],
        ];

        foreach ($ruoli as $ruolo) {
            Ruolo::updateOrCreate(['codice' => $ruolo['codice']], $ruolo);
        }
    }
}
