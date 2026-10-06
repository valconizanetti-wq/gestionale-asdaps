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

        // Impostazioni: solo presidente. Comprende anche la gestione
        // Utenti/Ruoli (gl_users, gl_ruoli, gl_ruoli_sezioni), senza una
        // sezione dedicata.
        $this->assegna('presidente', 'impostazioni');

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
