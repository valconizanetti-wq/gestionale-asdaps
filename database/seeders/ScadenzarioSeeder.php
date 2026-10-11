<?php

namespace Database\Seeders;

use App\Models\Associazione;
use App\Models\PrimaNotaCategoria;
use App\Models\Scadenza;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Scadenze dell'associazione pronte (v0.38.0, scadenzario.md; tavola SC-1):
 * - Affiliazione CSEN, ogni 31/12 (Paolo, 07/10/2026), uscita «Affiliazione EPS»;
 * - Polizza RC, a luglio (Unipol tramite CSEN; giorno da controllare,
 *   proposto il 31), uscita «Polizze Assicurative»;
 * - Approvazione del rendiconto, entro il 30/04 (statuto art. 22.2).
 * Tutte ricorrenti, preavviso 30 giorni, con la prima data da oggi in poi.
 * Si può rilanciare: una scadenza già presente (per chiave) non cambia; una
 * con lo stesso titolo e senza chiave riceve la chiave.
 */
class ScadenzarioSeeder extends Seeder
{
    public function run(): void
    {
        $categoria = fn (string $codice) => PrimaNotaCategoria::where('codice', $codice)->value('id');

        $scadenze = [
            [Scadenza::AFFILIAZIONE, 'Affiliazione CSEN', 'Rinnovo annuale dell\'affiliazione all\'ente di promozione sportiva. Senza affiliazione valida non si emettono tessere e l\'iscrizione al RASD decade.',
                'rete_affiliazione', 31, 12, $categoria(PrimaNotaCategoria::AFFILIAZIONE)],
            [Scadenza::POLIZZA, 'Polizza RC', 'Unipol, tramite CSEN: responsabilità civile verso terzi dell\'associazione.',
                'assicurazione', 31, 7, $categoria(PrimaNotaCategoria::POLIZZE)],
            [Scadenza::RENDICONTO, 'Approvazione del rendiconto', 'L\'Assemblea approva il rendiconto dell\'anno precedente entro quattro mesi dalla chiusura dell\'esercizio (statuto art. 22.2).',
                'assemblea', 30, 4, null],
        ];

        foreach ($scadenze as [$chiave, $titolo, $descrizione, $cat, $giorno, $mese, $pn]) {
            if ($giaPresente = Scadenza::where('chiave', $chiave)->first()) {
                // Seeder lanciato prima di PrimaNotaCategoriaSeeder: la categoria
                // dell'uscita arriva al secondo lancio
                if ($pn && ! $giaPresente->prima_nota_categoria_id) {
                    $giaPresente->forceFill(['prima_nota_categoria_id' => $pn])->save();
                }
                continue;
            }
            // Riga già inserita a mano con lo stesso titolo (o rimasta da un
            // rollback): riceve la chiave invece di essere duplicata
            if ($esistente = Scadenza::whereNull('chiave')->where('titolo', $titolo)->first()) {
                $esistente->forceFill(['chiave' => $chiave, 'prima_nota_categoria_id' => $esistente->prima_nota_categoria_id ?? $pn])->save();
                continue;
            }
            $data = Carbon::create(today()->year, $mese, $giorno)->startOfDay();
            if ($data->lt(today())) {
                $data->addYearNoOverflow();
            }
            Scadenza::create([
                'chiave' => $chiave,
                'titolo' => $titolo,
                'descrizione' => $descrizione,
                'tipo' => 'ricorrente',
                'categoria' => $cat,
                'prima_nota_categoria_id' => $pn,
                'data_scadenza' => $data->toDateString(),
                'giorno_ricorrenza' => $giorno,
                'mese_ricorrenza' => $mese,
                'giorni_preavviso' => 30,
            ]);
        }

        // Copia della scadenza dell'affiliazione e dati noti della polizza
        // nella scheda dell'ente (solo se mancano).
        $a = Associazione::istanza();
        $a->fill(array_filter([
            'rete_affiliazione_scadenza' => $a->rete_affiliazione_scadenza ? null : Scadenza::perChiave(Scadenza::AFFILIAZIONE)?->data_scadenza,
            'polizza_compagnia' => $a->polizza_compagnia ? null : 'Unipol, tramite CSEN',
            'polizza_copertura' => $a->polizza_copertura ? null : 'Responsabilità civile verso terzi dell\'associazione',
        ]))->save();
    }
}
