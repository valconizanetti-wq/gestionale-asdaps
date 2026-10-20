<?php

namespace Database\Seeders;

use App\Models\Sezione;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SezioneSeeder extends Seeder
{
    public function run(): void
    {
        $sezioni = [
            ['codice' => 'associazione', 'label' => 'Associazione', 'ordine' => 5],
            ['codice' => 'soci', 'label' => 'Soci', 'ordine' => 10],
            ['codice' => 'domande', 'label' => 'Domande/Iscrizione', 'ordine' => 20],
            ['codice' => 'rinnovi_quota', 'label' => 'Rinnovi quota', 'ordine' => 30],
            ['codice' => 'prima_nota', 'label' => 'Prima Nota', 'ordine' => 40],
            ['codice' => 'scontrini', 'label' => 'Scontrini', 'ordine' => 42],
            ['codice' => 'bilancio', 'label' => 'Bilancio', 'ordine' => 50],
            ['codice' => 'progetti', 'label' => 'Progetti', 'ordine' => 60],
            ['codice' => 'governance_cd', 'label' => 'Governance CD', 'ordine' => 70],
            ['codice' => 'assemblee', 'label' => 'Assemblee', 'ordine' => 80],
            ['codice' => 'verbali', 'label' => 'Verbali', 'ordine' => 90],
            ['codice' => 'volontari', 'label' => 'Volontari', 'ordine' => 100],
            ['codice' => 'tesseramento', 'label' => 'Tesseramento sportivo', 'ordine' => 110],
            ['codice' => 'compliance_rasd', 'label' => 'Compliance RASD', 'ordine' => 120],
            ['codice' => 'comunicazioni', 'label' => 'Comunicazioni', 'ordine' => 130],
            ['codice' => 'promemoria', 'label' => 'Promemoria', 'ordine' => 132],
            ['codice' => 'archivio', 'label' => 'Archivio documenti', 'ordine' => 135],
            ['codice' => 'gdpr', 'label' => 'GDPR', 'ordine' => 140],
            ['codice' => 'scadenzario', 'label' => 'Scadenzario', 'ordine' => 150],
            ['codice' => 'audit_log', 'label' => 'Audit Log', 'ordine' => 160],
            ['codice' => 'impostazioni', 'label' => 'Impostazioni', 'ordine' => 170],
            ['codice' => 'utenti', 'label' => 'Utenti', 'ordine' => 175],
            ['codice' => 'erogazioni_collaboratori', 'label' => 'Erogazioni/Collaboratori', 'ordine' => 180],
        ];

        foreach ($sezioni as $s) {
            Sezione::firstOrCreate(['codice' => $s['codice']], $s);
        }

        // Sezioni amministrative: presidente + segretario, accesso pieno.
        // 'associazione' (gl_associazione: dati ente, RASD, affiliazione
        // CSEN) e 'archivio' (gl_archivio_documenti: statuto firmato,
        // documenti istituzionali) servono anche al segretario, che segue
        // la corrispondenza con CSEN.
        $amministrative = [
            'associazione', 'soci', 'domande', 'governance_cd', 'assemblee',
            'verbali', 'comunicazioni', 'archivio', 'gdpr', 'compliance_rasd',
            'volontari', 'tesseramento', 'scadenzario',
        ];
        foreach ($amministrative as $codice) {
            $this->assegna('presidente', $codice);
            $this->assegna('segretario', $codice);
        }

        // Tessere CSEN (v0.37.0, analisi-tessere-scadenze.md §2.8): anche il
        // tesoriere, che compra i pacchetti; revisore in sola lettura.
        $this->assegna('tesoriere', 'tesseramento');
        $this->assegna('revisore', 'tesseramento', soloLettura: true);

        // Scadenzario (v0.38.0, scadenzario.md): anche il tesoriere, che paga
        // affiliazione e polizza; revisore in sola lettura.
        $this->assegna('tesoriere', 'scadenzario');
        $this->assegna('revisore', 'scadenzario', soloLettura: true);

        // Volontari: tesoriere in sola lettura — vede i rimborsi da pagare
        // e registrare in Prima Nota, la gestione resta al segretario.
        $this->assegna('tesoriere', 'volontari', soloLettura: true);

        // Sezioni contabili: presidente + tesoriere accesso pieno,
        // revisore in sola lettura.
        $contabili = ['prima_nota', 'rinnovi_quota', 'bilancio', 'progetti'];
        foreach ($contabili as $codice) {
            $this->assegna('presidente', $codice);
            $this->assegna('tesoriere', $codice);
            $this->assegna('revisore', $codice, soloLettura: true);
        }

        // Scontrini (v0.39.0, scontrini.md §6): tutto il CD carica gli
        // scontrini dal telefono (sezione 'scontrini' piena = caricare);
        // Segretario e Consigliere vedono la Prima Nota in sola lettura.
        // Verificare, dividere, correggere ed eliminare restano a chi
        // modifica la Prima Nota (Presidente e Tesoriere); il Revisore legge.
        foreach (['presidente', 'tesoriere', 'segretario', 'consigliere'] as $ruolo) {
            $this->assegna($ruolo, 'scontrini');
        }
        $this->assegna('revisore', 'scontrini', soloLettura: true);
        $this->assegna('segretario', 'prima_nota', soloLettura: true);
        $this->assegna('consigliere', 'prima_nota', soloLettura: true);

        // Promemoria (v0.36.0, promemoria.md): la pagina la vedono i ruoli che
        // hanno almeno una funzione con promemoria; chi modifica il singolo
        // promemoria lo decide la sezione della sua funzione (es. le quote:
        // rinnovi_quota). Revisore in sola lettura.
        foreach (['presidente', 'tesoriere', 'segretario'] as $ruolo) {
            $this->assegna($ruolo, 'promemoria');
        }
        $this->assegna('revisore', 'promemoria', soloLettura: true);

        // Audit Log: presidente accesso pieno (inclusa la futura
        // archiviazione su gl_audit_archivi), revisore in sola lettura —
        // registro con hash chain, da consultare, non modificare.
        $this->assegna('presidente', 'audit_log');
        $this->assegna('revisore', 'audit_log', soloLettura: true);

        // Impostazioni: solo presidente.
        $this->assegna('presidente', 'impostazioni');
        // Profilo dell'ente in lettura (v0.38.0, tavola SC-2): tesoriere e
        // segretario vedono affiliazione e polizza; le modifiche restano al
        // Presidente (ImpostazioniEnteController::richiediPresidente).
        $this->assegna('tesoriere', 'impostazioni', soloLettura: true);
        $this->assegna('segretario', 'impostazioni', soloLettura: true);

        // Utenti (v0.40.0, analisi-ruoli-utenti.md §3.4, Paolo 13/10/2026):
        // modificano Presidente e Segretario (il Segretario con i limiti di
        // App\Services\Utenti: niente ruolo di Presidente, né l'utente del
        // Presidente, né i propri ruoli); tutto il CD legge. Il Revisore no:
        // non fa parte del CD.
        $this->assegna('presidente', 'utenti');
        $this->assegna('segretario', 'utenti');
        $this->assegna('tesoriere', 'utenti', soloLettura: true);
        $this->assegna('consigliere', 'utenti', soloLettura: true);

        // v0.42.0 (analisi-collaudo-O.md §11, §15): pagina del CD in lettura
        // per gli altri del CD e il Revisore; Vicepresidente come il
        // Consigliere più le sezioni del Presidente in lettura;
        // Amministratore in lettura ovunque, Utenti in scrittura.
        foreach (['tesoriere', 'consigliere', 'revisore'] as $ruolo) {
            $this->assegna($ruolo, 'governance_cd', soloLettura: true);
        }
        $this->assegna('vicepresidente', 'scontrini');
        foreach (['prima_nota', 'utenti', 'soci', 'domande', 'rinnovi_quota', 'tesseramento', 'scadenzario',
            'promemoria', 'impostazioni', 'audit_log', 'governance_cd'] as $codice) {
            $this->assegna('vicepresidente', $codice, soloLettura: true);
        }
        foreach ($sezioni as $s) {
            $this->assegna('amministratore', $s['codice'], soloLettura: $s['codice'] !== 'utenti');
        }

        // v0.44.0 (analisi-verbali-assemblee.md, V12): i verbali li scrivono
        // Presidente e Segretario; il resto del CD e il Revisore leggono.
        foreach (['vicepresidente', 'tesoriere', 'consigliere', 'revisore'] as $ruolo) {
            $this->assegna($ruolo, 'verbali', soloLettura: true);
        }

        // Erogazioni/Collaboratori: nessun ruolo assegnato — sezione
        // dormiente, nessun dipendente/collaboratore oggi (vedi
        // MANUALE-ASD.md, checklist moduli).
    }

    private function assegna(string $ruoloCodice, string $sezioneCodice, bool $soloLettura = false): void
    {
        $sezioneId = DB::table('gl_sezioni')->where('codice', $sezioneCodice)->value('id');

        DB::table('gl_ruoli_sezioni')->updateOrInsert(
            ['ruolo_codice' => $ruoloCodice, 'sezione_id' => $sezioneId],
            ['sola_lettura' => $soloLettura]
        );
    }
}
