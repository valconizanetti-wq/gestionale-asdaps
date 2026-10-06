<?php

namespace Database\Seeders;

use App\Models\Impostazione;
use Illuminate\Database\Seeder;

class ImpostazioneSeeder extends Seeder
{
    public function run(): void
    {
        Impostazione::set(
            'scontrini_split_abilitato',
            true,
            'boolean',
            'Se attivo, uno scontrino può avere più movimenti collegati in Prima Nota (split su categorie diverse), con quadratura verificata. Se disattivo, ogni scontrino genera sempre un solo movimento pari al totale.'
        );

        Impostazione::set(
            'regime_398_plafond_soglia',
            400000,
            'integer',
            'Plafond annuo (L. 398/1991) di soli proventi commerciali (entrate da categorie con flag_non_istituzionale = true). Oltre questa soglia il regime agevolato decade.'
        );

        Impostazione::set(
            'regime_398_tracciabilita_soglia',
            1000,
            'integer',
            'Soglia (in euro) oltre la quale pagamenti/versamenti devono transitare su conto corrente intestato all\'associazione (conto = banca), non in contanti. Usata per segnalare movimenti in cassa sopra soglia.'
        );

        Impostazione::set(
            'audit_log_conservazione_anni',
            10,
            'integer',
            'Anni minimi di conservazione di gl_audit_log prima che gl_audit_archivi possa archiviare/eliminare righe (art. 2220 c.c.: conservazione scritture contabili 10 anni). Da usare come soglia minima nella logica di archiviazione, non ancora implementata.'
        );

        // Domande di iscrizione online (domande-iscrizione.md). Solo se
        // mancano: sono valori che si cambiano dal gestionale (quote decise
        // dal CD, nuove versioni dei testi) e un nuovo db:seed non deve
        // riportarli al valore iniziale.
        $seManca = function (string $chiave, mixed $valore, string $tipo, string $descrizione) {
            if (Impostazione::where('chiave', $chiave)->doesntExist()) {
                Impostazione::set($chiave, $valore, $tipo, $descrizione);
            }
        };

        $seManca('privacy_versione_corrente', '1.0', 'string',
            "Versione dell'informativa privacy in vigore: file resources/content/informativa/<versione>.md. Registrata con ogni consenso.");
        $seManca('statuto_versione_corrente', '1.0', 'string',
            'Versione dello statuto in vigore: file resources/content/statuto/<versione>.md. Registrata con ogni domanda di iscrizione.');
        $seManca('quota_ordinario', 70, 'integer',
            'Quota annuale del socio ordinario, in euro, tesseramento CSEN e assicurazione base compresi (statuto art. 5.4, decisa dal CD).');
        $seManca('quota_giovane', 30, 'integer',
            'Quota annuale del socio giovane (minorenne), in euro, tesseramento CSEN e assicurazione base compresi (statuto art. 5.4, decisa dal CD).');
        $seManca('contabilita_anno_avvio', 2027, 'integer',
            "Primo anno della contabilità nel gestionale (Prima Nota e quote): le quote incassate prima restano nel vecchio gestionale, così l'anno prima non diventa per errore il primo anno contabile (rinnovi-quota.md §4).");
        $seManca('iscrizioni_aperte', false, 'boolean',
            "Se spenta, il modulo pubblico /iscrizione mostra «Iscrizioni online non ancora aperte»; chi è collegato all'area riservata può provarlo e le domande nascono di prova (profilo-ente.md).");
        $seManca('domande_modalita_test', false, 'boolean',
            "Se attiva, le nuove domande di iscrizione sono di prova: non entrano nell'audit log e si possono eliminare. Da spegnere prima dell'apertura reale.");
    }
}
