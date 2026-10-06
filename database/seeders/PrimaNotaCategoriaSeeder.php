<?php

namespace Database\Seeders;

use App\Models\PrimaNotaCategoria;
use Illuminate\Database\Seeder;

/**
 * Categorie di Prima Nota della Compagnia (prima-nota.md §2).
 *
 * Sono le 33 categorie di conta_categorie_master di gestionale-acv (dump del
 * 06/10/2026, claude/acv-categorie-master.sql nel progetto), tutte tenute
 * su decisione di Paolo, con lo stesso nome e lo stesso stato. L'ordine è
 * quello del vecchio gestionale, rinumerato: "Opere e progetti" e "Costi di
 * gestione" avevano entrambe 10.
 *
 * In più:
 * - codice QUOTE su "Quote associative" (lo cerca IscrizioneSocio, prima
 *   nessun seeder lo creava);
 * - "Uscite da classificare" diventa categoria di sistema (codice
 *   DA_CLASSIFICARE): la ricevono gli scontrini dal telefono (v0.36.0);
 * - "Disavanzo/Attivo anno precedente" solo per preventivo e rendiconto;
 * - due entrate con natura da verificare per il regime 398 dal 2028.
 *
 * Si può rilanciare: le categorie con codice si cercano per codice, le altre
 * per tipo e nome; le esistenti non vengono modificate (tranne codice e uso
 * delle categorie speciali, se mancanti).
 */
class PrimaNotaCategoriaSeeder extends Seeder
{
    public function run(): void
    {
        // [tipo, nome, attiva, codice, uso, natura da verificare]
        $categorie = [
            ['uscita', 'Disavanzo anno precedente', true, 'DISAVANZO_PREC', 'solo_bilancio', false],
            ['uscita', 'Imposte e tasse', true, null, 'movimenti', false],
            ['uscita', 'Affiliazione EPS', true, null, 'movimenti', false],
            ['uscita', 'Tesseramento Soci EPS', true, null, 'movimenti', false],
            ['uscita', 'Polizze Assicurative', true, null, 'movimenti', false],
            ['uscita', 'Contributi obbligatori, associativi, diversi', true, null, 'movimenti', false],
            ['uscita', 'Attività ricreative interne', true, null, 'movimenti', false],
            ['uscita', 'Gare, manifestazioni e iniziative', true, null, 'movimenti', false],
            ['uscita', 'Acquisto materiali', true, null, 'movimenti', false],
            ['uscita', 'Opere e progetti', true, null, 'movimenti', false],
            ['uscita', 'Costi di gestione', true, null, 'movimenti', false],
            ['uscita', 'Manutenzione campi e strutture', true, null, 'movimenti', false],
            ['uscita', 'Taglio erba', true, null, 'movimenti', false],
            ['uscita', 'Manutenzione e gestione bagni', true, null, 'movimenti', false],
            ['uscita', 'Acquisto attrezzature', true, null, 'movimenti', false],
            ['uscita', 'Altre spese', true, null, 'movimenti', false],
            ['uscita', 'Spese ufficio', true, null, 'movimenti', false],
            ['uscita', 'Fondo per le spese impreviste e di riserva', false, null, 'movimenti', false],
            ['uscita', 'Anticipo spese', false, null, 'movimenti', false],
            ['uscita', 'Spese per pubb. straordinarie, materiale propaganda.', false, null, 'movimenti', false],
            ['uscita', 'Uscite da classificare', true, PrimaNotaCategoria::DA_CLASSIFICARE, 'sistema', false],

            ['entrata', 'Attivo anno precedente', true, 'ATTIVO_PREC', 'solo_bilancio', false],
            ['entrata', 'Quote associative', true, PrimaNotaCategoria::QUOTE, 'movimenti', false],
            ['entrata', 'Contributi e donazioni da terzi', true, null, 'movimenti', false],
            ['entrata', 'Interessi attivi su depositi bancari', true, null, 'movimenti', false],
            ['entrata', 'Gare, manifestazioni e iniziative', true, null, 'movimenti', true],
            ['entrata', 'Donazioni', false, null, 'movimenti', false],
            ['entrata', 'Contributi da Esercenti, Commercianti, privati, banche, vari', false, null, 'movimenti', true],
            ['entrata', 'Contributo comunale', false, null, 'movimenti', false],
            ['entrata', 'Contributi da Enti Pubblici per iniziative varie', false, null, 'movimenti', false],
            ['entrata', 'Recupero anticipi', false, null, 'movimenti', false],
            ['entrata', 'Entrate temporanee', false, null, 'movimenti', false],
            ['entrata', 'Fondo spese impreviste e di riserva (anno precedente)', false, null, 'movimenti', false],
        ];

        $ordine = ['entrata' => 0, 'uscita' => 0];

        foreach ($categorie as [$tipo, $nome, $attiva, $codice, $uso, $daVerificare]) {
            $ordine[$tipo] += 10;

            $esistente = ($codice ? PrimaNotaCategoria::where('codice', $codice)->first() : null)
                ?? PrimaNotaCategoria::where('tipo', $tipo)->where('nome', $nome)->first();

            if ($esistente) {
                // Solo i dati che il codice usa, e solo se mancano.
                $esistente->fill(array_filter([
                    'codice' => $esistente->codice ?: $codice,
                    'uso' => $uso !== 'movimenti' ? $uso : null,
                ]))->save();
                continue;
            }

            PrimaNotaCategoria::create([
                'tipo' => $tipo,
                'nome' => $nome,
                'codice' => $codice,
                'uso' => $uso,
                'attiva' => $attiva,
                'ordine' => $ordine[$tipo],
                'natura_da_verificare' => $daVerificare,
            ]);
        }
    }
}
