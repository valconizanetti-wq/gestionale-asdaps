<?php

namespace App\Services;

use App\Models\Associazione;
use App\Models\Impostazione;
use App\Models\Scadenza;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Azzeramento dei dati di prova prima dell'apertura (v0.41.0,
 * analisi-azzeramento.md). Lo usa il comando `gestionale:azzera`.
 *
 * Ogni tabella del database è in uno dei gruppi qui sotto: TIENI
 * (configurazione e dati veri dell'ente), PARZIALI (si tengono con delle
 * correzioni) o tutte le altre, che si SVUOTANO. Una tabella che compare
 * in TIENI e PARZIALI per errore, o una tabella nuova non ancora
 * decisa, ferma il comando: AzzeramentoTest controlla la classificazione.
 */
class Azzeramento
{
    /** Configurazione e dati veri dell'ente: non si toccano. */
    public const TIENI = [
        'migrations',
        'gl_ruoli', 'gl_sezioni', 'gl_ruoli_sezioni',
        'gl_users_ruoli',                                   // D1: restano tutti gli utenti
        'gl_associazione', 'gl_associazione_periodi',
        'gl_prima_nota_categorie',
        'gl_tessere_tipi',
        'gl_qualifiche', 'gl_divisioni_arco', 'gl_specialita_gara',
        'gl_promemoria',                                    // D3: i testi restano
    ];

    /**
     * Colonne di tabelle tenute che puntano a tabelle svuotate: esegui() le
     * rimette a null (v0.42.0: gli utenti restano, i soci di prova no).
     */
    public const SCOLLEGATE = [
        'gl_users.socio_id' => 'gl_soci',
    ];

    /** Si tengono con delle correzioni (vedi esegui()). */
    public const PARZIALI = [
        'gl_impostazioni',   // chiavi rimesse (§3.4)
        'gl_scadenzario',    // solo le tre pronte, con la data rimessa (D4)
        'gl_users',          // D1: restano tutti, scollegati dai soci di prova (v0.42.0)
    ];

    /**
     * Dati di prova: si svuotano tutte, contatori compresi. Tutti i moduli,
     * anche quelli non ancora costruiti (vuote): domande, soci, quote,
     * Prima Nota, scontrini, tessere, Audit Log, registro email, sessioni…
     */
    public const SVUOTA = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'gl_anni_contabili',
        'gl_archivio_documenti',
        'gl_assemblee',
        'gl_assemblee_rsvp',
        'gl_attestazioni_tecniche',
        'gl_audit_archivi',
        'gl_audit_log',
        'gl_audit_log_integrity',
        'gl_backup_log',
        'gl_bilancio',
        'gl_bilancio_voci',
        'gl_cassa_chiusure',
        'gl_certificati_medici',
        'gl_collaboratori',
        'gl_comunicazioni',
        'gl_comunicazioni_allegati',
        'gl_comunicazioni_destinatari',
        'gl_destinatari',
        'gl_domande',
        'gl_email_log',
        'gl_erogazioni_liberali',
        'gl_f24_batch',
        'gl_gdpr_documenti',
        'gl_gdpr_incaricati',
        'gl_gdpr_log_accessi',
        'gl_gdpr_registro_firmato',
        'gl_gdpr_responsabili',
        'gl_gdpr_responsabili_docs',
        'gl_gdpr_richieste',
        'gl_gdpr_trattamenti',
        'gl_gdpr_violazioni',
        'gl_governance_cd',
        'gl_lettere',
        'gl_lettere_invii',
        'gl_password_reset_tokens',
        'gl_prima_nota',
        'gl_prima_nota_documenti',
        'gl_prima_nota_scontrini',
        'gl_prima_nota_scontrini_file',
        'gl_prima_nota_scontrini_righe',
        'gl_privacy_link',
        'gl_progetti',
        'gl_progetti_preventivo',
        'gl_promemoria_invii',
        'gl_rasd_verifiche',
        'gl_rimborsi_rasd_invii',
        'gl_rimborsi_volontari',
        'gl_rimborsi_volontari_voci',
        'gl_rinnovi',
        'gl_runts_documenti',
        'gl_scadenzario_rinnovi',
        'gl_soci',
        'gl_soci_documenti',
        'gl_soggetti',
        'gl_tag',
        'gl_tecnici',
        'gl_tesseramenti',
        'gl_tesseramenti_divisioni',
        'gl_tesseramenti_qualifiche',
        'gl_tesseramenti_specialita',
        'gl_tessere_blocchi',
        'gl_tessere_pacchetti',
        'gl_verbali',
        'gl_verbali_allegati',
        'gl_volontari',
        'gl_volontari_autodichiarazioni',
        'gl_volontari_log',
        'gl_volontari_sessioni',
        'gl_voti_cd',
        'job_batches',
        'jobs',
        'sessions',
    ];

    /**
     * Tabelle che, svuotate, si mostrano con un nome leggibile nel
     * riepilogo (le altre col nome della tabella, o tra le «altre» se vuote).
     */
    public const ETICHETTE = [
        'gl_soci' => 'Soci',
        'gl_soci_documenti' => 'Documenti dei soci',
        'gl_privacy_link' => 'Link privacy',
        'gl_domande' => 'Domande di iscrizione',
        'gl_voti_cd' => 'Voti del CD',
        'gl_verbali' => 'Verbali',
        'gl_governance_cd' => 'Consiglio Direttivo',
        'gl_rinnovi' => 'Quote e ricevute',
        'gl_promemoria_invii' => 'Promemoria inviati',
        'gl_prima_nota' => 'Movimenti di Prima Nota',
        'gl_prima_nota_documenti' => 'Giustificativi',
        'gl_anni_contabili' => 'Anni contabili',
        'gl_tag' => 'Tag',
        'gl_soggetti' => 'Fornitori',                       // D5
        'gl_prima_nota_scontrini' => 'Scontrini',
        'gl_prima_nota_scontrini_righe' => 'Righe degli scontrini',
        'gl_prima_nota_scontrini_file' => 'Foto degli scontrini',
        'gl_tesseramenti' => 'Tessere CSEN',
        'gl_tessere_pacchetti' => 'Pacchetti di tessere',
        'gl_scadenzario_rinnovi' => 'Rinnovi dello Scadenzario',
        'gl_audit_log' => 'Audit Log',
        'gl_audit_log_integrity' => 'Certificati dell\'Audit Log',
        'gl_attestazioni_tecniche' => 'Attestazioni dell\'Audit Log',
        'gl_email_log' => 'Registro delle email',
        'sessions' => 'Sessioni aperte',
    ];

    /** Cartelle di storage/app/private con i file di prova. */
    public const CARTELLE = ['domande', 'soci', 'ricevute', 'prima-nota', 'scontrini', 'audit', 'tmp'];

    /** Cartella delle copie di sicurezza (non si cancella). */
    public const BACKUP = 'backup';

    /** Chiavi delle impostazioni che si tolgono (la prima verifica sarà quella vera). */
    private const IMPOSTAZIONI_DA_TOGLIERE = ['audit_ultima_verifica'];

    /** Impostazioni rimesse: iscrizioni chiuse, modalità test spenta. */
    private const IMPOSTAZIONI_RIMESSE = ['iscrizioni_aperte' => '0', 'domande_modalita_test' => '0'];

    /** @return list<string> tutte le tabelle del database */
    public function tabelle(): array
    {
        // Solo il database del gestionale: getTables() elenca anche gli altri
        // database visibili all'utente MySQL (es. quello dei test)
        $db = DB::connection()->getDatabaseName();

        return array_values(array_unique(array_map(fn ($t) => $t['name'],
            array_filter(Schema::getTables(), fn ($t) => ($t['schema'] ?? $db) === $db))));
    }

    /** @return list<string> tabelle che si svuotano (quelle di SVUOTA che esistono) */
    public function daSvuotare(): array
    {
        return array_values(array_intersect(self::SVUOTA, $this->tabelle()));
    }

    /**
     * Tabelle del database in nessun gruppo: una migration nuova ne ha
     * aggiunta una e non si è ancora deciso se tenerla o svuotarla.
     *
     * @return list<string>
     */
    public function nonClassificate(): array
    {
        return array_values(array_diff($this->tabelle(), self::TIENI, self::PARZIALI, self::SVUOTA));
    }

    /**
     * Perché non si può azzerare (dati veri), o null.
     */
    public function motivoRifiuto(): ?string
    {
        if ($nuove = $this->nonClassificate()) {
            return 'ci sono tabelle che il comando non conosce (' . implode(', ', $nuove) . '): va deciso se tenerle o svuotarle (App\\Services\\Azzeramento).';
        }
        if (Schema::hasTable('gl_anni_contabili') && DB::table('gl_anni_contabili')->where('stato', 'chiuso')->exists()) {
            $anno = DB::table('gl_anni_contabili')->where('stato', 'chiuso')->min('anno');

            return "l'anno contabile {$anno} è chiuso, quindi ci sono dati veri.";
        }
        $avvio = (int) Impostazione::get('contabilita_anno_avvio', 2027);
        if (Schema::hasTable('gl_prima_nota') && DB::table('gl_prima_nota')->whereYear('data', '>=', $avvio)->exists()) {
            return "in Prima Nota ci sono movimenti dal {$avvio}, l'anno d'avvio della contabilità: sono dati veri.";
        }
        if (Schema::hasTable('gl_rinnovi') && DB::table('gl_rinnovi')->where('anno_ricevuta', '>=', $avvio)->exists()) {
            return "ci sono ricevute di quote con numerazione {$avvio}: sono dati veri.";
        }

        return null;
    }

    /**
     * Riepilogo senza cambiare niente.
     *
     * @return array{svuota: array<string, int>, scadenze_a_mano: int, scadenze_pronte: list<array{titolo: string, prima: string, dopo: string}>,
     *               utenti: list<string>, promemoria: int, file: array{numero: int, byte: int}, cartelle_sconosciute: list<string>}
     */
    public function riepilogo(): array
    {
        // Prima le tabelle con un nome leggibile, nell'ordine di ETICHETTE
        $ordine = array_flip(array_keys(self::ETICHETTE));
        $tabelle = $this->daSvuotare();
        usort($tabelle, fn ($a, $b) => [$ordine[$a] ?? PHP_INT_MAX, $a] <=> [$ordine[$b] ?? PHP_INT_MAX, $b]);
        $svuota = [];
        foreach ($tabelle as $t) {
            $svuota[$t] = (int) DB::table($t)->count();
        }

        $pronte = [];
        foreach (DB::table('gl_scadenzario')->whereNotNull('chiave')->orderBy('id')->get() as $s) {
            $pronte[] = ['titolo' => $s->titolo, 'prima' => Carbon::parse($s->data_scadenza)->format('d/m/Y'), 'dopo' => $this->prossimaData($s)->format('d/m/Y')];
        }

        $utenti = [];
        foreach (\App\Models\User::orderBy('id')->get() as $u) {
            $utenti[] = $u->name . ' – ' . ($u->etichettaRuoli() ?: 'nessun ruolo') . ($u->attivo ? '' : ' (disattivato)');
        }

        return [
            'svuota' => $svuota,
            'scadenze_a_mano' => (int) DB::table('gl_scadenzario')->whereNull('chiave')->count(),
            'scadenze_pronte' => $pronte,
            'utenti' => $utenti,
            'promemoria' => (int) DB::table('gl_promemoria')->count(),
            'file' => $this->file(),
            'cartelle_sconosciute' => $this->cartelleSconosciute(),
        ];
    }

    /**
     * Copia di sicurezza di tutto il database in SQL compresso, scritta da
     * PHP (su cPanel mysqldump non sempre c'è). Restituisce il percorso
     * relativo al disco local.
     */
    public function copiaDiSicurezza(?Carbon $quando = null): string
    {
        $quando ??= now();
        $percorso = self::BACKUP . '/azzeramento-' . $quando->format('Ymd-His') . '.sql.gz';
        $disco = Storage::disk('local');
        $disco->makeDirectory(self::BACKUP);
        $gz = gzopen($disco->path($percorso), 'wb6');
        if ($gz === false) {
            throw new \RuntimeException('Copia di sicurezza non scritta: controlla i permessi di storage/app/private.');
        }

        $pdo = DB::connection()->getPdo();
        gzwrite($gz, "-- Gestionale ASD: copia prima dell'azzeramento, " . $quando->format('d/m/Y H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n");
        foreach ($this->tabelle() as $t) {
            $crea = (array) DB::selectOne("SHOW CREATE TABLE `{$t}`");
            gzwrite($gz, "\nDROP TABLE IF EXISTS `{$t}`;\n" . end($crea) . ";\n");
            foreach (DB::table($t)->cursor() as $riga) {
                $valori = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $riga);
                gzwrite($gz, "INSERT INTO `{$t}` (`" . implode('`,`', array_keys((array) $riga)) . '`) VALUES (' . implode(',', $valori) . ");\n");
            }
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);

        return $percorso;
    }

    /**
     * Azzera davvero (dopo la copia di sicurezza, fatta dal chiamante).
     *
     * @return array{righe: int, tabelle: int, file: int, byte: int, audit_id: int}
     */
    public function esegui(string $copia): array
    {
        if ($m = $this->motivoRifiuto()) {
            throw new \RuntimeException('Non si può azzerare: ' . $m);
        }
        $riepilogo = $this->riepilogo();
        $righe = array_sum($riepilogo['svuota']) + $riepilogo['scadenze_a_mano'];
        $tabelle = count(array_filter($riepilogo['svuota']));

        // TRUNCATE rimette i contatori a 1; con i controlli delle chiavi
        // esterne sospesi l'ordine non conta.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($this->daSvuotare() as $t) {
                DB::table($t)->truncate();
            }
            DB::table('gl_scadenzario')->whereNull('chiave')->delete();
            // Gli utenti restano (D1) ma i soci di prova no: si ricollegano
            // ai soci veri dalla pagina Consiglio Direttivo (v0.42.0)
            foreach (array_keys(self::SCOLLEGATE) as $colonna) {
                [$tabella, $campo] = explode('.', $colonna);
                DB::table($tabella)->update([$campo => null]);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Scadenze pronte alla prossima data vera (D4); la scheda dell'ente
        // riprende la scadenza dell'affiliazione
        foreach (DB::table('gl_scadenzario')->whereNotNull('chiave')->get() as $s) {
            DB::table('gl_scadenzario')->where('id', $s->id)->update([
                'data_scadenza' => $this->prossimaData($s)->toDateString(),
                'completato' => false, 'completato_il' => null, 'note_completamento' => null, 'updated_at' => now(),
            ]);
        }
        if ($aff = Scadenza::perChiave(Scadenza::AFFILIAZIONE)) {
            Associazione::istanza()?->forceFill(['rete_affiliazione_scadenza' => $aff->data_scadenza])->save();
        }

        // Impostazioni
        DB::table('gl_impostazioni')->whereIn('chiave', self::IMPOSTAZIONI_DA_TOGLIERE)->delete();
        foreach (self::IMPOSTAZIONI_RIMESSE as $chiave => $valore) {
            DB::table('gl_impostazioni')->where('chiave', $chiave)->update(['valore' => $valore, 'updated_at' => now()]);
        }

        // File
        $disco = Storage::disk('local');
        foreach (self::CARTELLE as $c) {
            $disco->deleteDirectory($c);
        }

        // Prima riga della catena nuova
        $id = AuditLogService::scrivi([
            'evento' => 'AZZERAMENTO_DATI_PROVA',
            'operazione' => 'deleted',
            'entita_tipo' => 'Azzeramento',
            'utente_nome' => 'sistema (gestionale:azzera)',
            'ip_address' => '127.0.0.1',
            'livello_rischio' => 'HIGH',
            'dettaglio' => "Azzeramento dei dati di prova prima dell'apertura: {$righe} righe in {$tabelle} tabelle, "
                . "{$riepilogo['file']['numero']} file. Copia di sicurezza: {$copia}.",
            'old_values' => array_filter($riepilogo['svuota']) + ['scadenze_a_mano' => $riepilogo['scadenze_a_mano']],
        ]);

        return ['righe' => $righe, 'tabelle' => $tabelle, 'file' => $riepilogo['file']['numero'], 'byte' => $riepilogo['file']['byte'], 'audit_id' => $id];
    }

    /** Prossima data (da oggi) della ricorrenza di una scadenza pronta. */
    public function prossimaData(object $s): Carbon
    {
        $giorno = (int) ($s->giorno_ricorrenza ?: Carbon::parse($s->data_scadenza)->day);
        $mese = (int) ($s->mese_ricorrenza ?: Carbon::parse($s->data_scadenza)->month);
        $data = Carbon::create(today()->year, $mese, 1)->startOfDay();
        $data->day(min($giorno, $data->daysInMonth));
        if ($data->lt(today())) {
            $data = Carbon::create(today()->year + 1, $mese, 1)->startOfDay();
            $data->day(min($giorno, $data->daysInMonth));
        }

        return $data;
    }

    /** @return array{numero: int, byte: int} */
    private function file(): array
    {
        $disco = Storage::disk('local');
        $n = 0;
        $byte = 0;
        foreach (self::CARTELLE as $c) {
            foreach ($disco->allFiles($c) as $f) {
                $n++;
                $byte += (int) $disco->size($f);
            }
        }

        return ['numero' => $n, 'byte' => $byte];
    }

    /** @return list<string> cartelle di storage/app/private che il comando non conosce (restano) */
    private function cartelleSconosciute(): array
    {
        return array_values(array_diff(Storage::disk('local')->directories(), self::CARTELLE, [self::BACKUP]));
    }
}
