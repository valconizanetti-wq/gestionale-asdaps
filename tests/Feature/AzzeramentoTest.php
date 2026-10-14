<?php

namespace Tests\Feature;

use App\Models\AnnoContabile;
use App\Models\Impostazione;
use App\Models\Rinnovo;
use App\Models\Socio;
use App\Models\User;
use App\Services\AuditIntegrityService;
use App\Services\AuditLogService;
use App\Services\Azzeramento;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\PrimaNotaCategoriaSeeder;
use Database\Seeders\PromemoriaSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\ScadenzarioSeeder;
use Database\Seeders\SezioneSeeder;
use Database\Seeders\TesseraTipoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Comando gestionale:azzera (v0.41.0, analisi-azzeramento.md).
 *
 * L'azzeramento usa TRUNCATE, che in MySQL chiude la transazione: niente
 * RefreshDatabase. Il database si ricrea prima e dopo ogni prova, e i test
 * seguenti lo ritrovano pulito.
 */
class AzzeramentoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class,
            PrimaNotaCategoriaSeeder::class, ScadenzarioSeeder::class, PromemoriaSeeder::class, TesseraTipoSeeder::class]);
        Mail::fake();
        Storage::fake('local');
        Carbon::setTestNow('2026-11-20 21:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    /** Dati di prova come dopo il collaudo. */
    private function datiDiProva(): array
    {
        $pres = User::factory()->conRuoli('presidente')->create(['name' => 'Andrea Presidente']);
        $paolo = User::factory()->conRuoli('segretario', 'tesoriere')->create(['name' => 'Paolo Doppio']);
        $s = new Socio();
        $s->forceFill(['nome' => 'Luca', 'cognome' => 'Verdi', 'codice_fiscale' => 'VRDLCU90E10L117B',
            'data_prima_iscrizione' => '2026-01-01', 'tipo' => 'ordinario', 'stato' => 'attivo'])->save();
        (new Rinnovo())->forceFill(['socio_id' => $s->id, 'anno' => 2026, 'importo' => 70, 'categoria' => 'ordinario',
            'data_pagamento' => '2026-03-01', 'metodo_pagamento' => 'contante', 'numero_ricevuta' => '1/2026',
            'anno_ricevuta' => 2026, 'progressivo' => 1, 'ricevuta_stato' => 'non_generata'])->save();
        AnnoContabile::create(['anno' => 2026, 'stato' => 'aperto', 'saldo_apertura_cassa' => 0, 'saldo_apertura_banca' => 0]);
        DB::table('gl_prima_nota')->insert(['data' => '2026-03-01', 'tipo' => 'entrata', 'conto' => 'cassa', 'importo' => 70,
            'descrizione' => 'Prova', 'categoria_id' => DB::table('gl_prima_nota_categorie')->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gl_scadenzario')->where('chiave', 'affiliazione_eps')->update(['data_scadenza' => '2027-12-31', 'completato' => true]);
        DB::table('gl_scadenzario')->insert(['titolo' => 'Scadenza di prova', 'tipo' => 'una_tantum', 'categoria' => 'altro',
            'data_scadenza' => '2026-12-01', 'giorni_preavviso' => 7, 'created_at' => now(), 'updated_at' => now()]);
        Impostazione::set('iscrizioni_aperte', '1', 'boolean');
        Impostazione::set('audit_ultima_verifica', ['righe' => 3], 'json');
        AuditLogService::scrivi(['evento' => 'PROVA', 'dettaglio' => 'Riga di prova']);
        Storage::disk('local')->put('ricevute/2026/1-2026.pdf', 'pdf di prova');
        Storage::disk('local')->put('scontrini/2026/SC2026-001.jpg', 'foto');
        Storage::disk('local')->put('altro/da-non-toccare.txt', 'x');

        return [$pres, $paolo];
    }

    public function test_ogni_tabella_e_classificata_e_nessuna_tenuta_dipende_da_una_svuotata(): void
    {
        $azz = app(Azzeramento::class);
        $this->assertSame([], $azz->nonClassificate(), 'Tabelle nuove da classificare in App\Services\Azzeramento');
        $this->assertSame([], array_values(array_intersect(Azzeramento::TIENI, Azzeramento::SVUOTA)));
        $this->assertSame([], array_values(array_intersect(Azzeramento::PARZIALI, Azzeramento::SVUOTA)));
        // Nessuna voce degli elenchi senza tabella (un nome scritto male)
        $this->assertSame([], array_values(array_diff([...Azzeramento::TIENI, ...Azzeramento::PARZIALI, ...Azzeramento::SVUOTA], $azz->tabelle())));

        // Una tabella che si tiene non deve puntare a una che si svuota
        $fk = DB::select('SELECT TABLE_NAME AS figlia, REFERENCED_TABLE_NAME AS madre FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL');
        $tenute = [...Azzeramento::TIENI, ...Azzeramento::PARZIALI];
        foreach ($fk as $r) {
            if (in_array($r->figlia, $tenute, true)) {
                $this->assertNotContains($r->madre, Azzeramento::SVUOTA, "{$r->figlia} punta a {$r->madre}, che si svuota");
            }
        }
    }

    public function test_prova_a_vuoto_non_cambia_niente(): void
    {
        $this->datiDiProva();
        $prima = [Socio::count(), Rinnovo::count(), DB::table('gl_prima_nota')->count(), DB::table('gl_audit_log')->count(), DB::table('gl_scadenzario')->count()];

        $this->artisan('gestionale:azzera')
            ->expectsOutputToContain('prova a vuoto (non cambia niente)')
            ->expectsOutputToContain('Soci')
            ->expectsOutputToContain('Andrea Presidente – Presidente; Paolo Doppio – Segretario e Tesoriere')
            ->expectsOutputToContain('Affiliazione CSEN 31/12/2027 → 31/12/2026')
            ->expectsOutputToContain('Cartelle che il comando non conosce (restano): altro')
            ->expectsOutputToContain('php artisan gestionale:azzera --esegui')
            ->assertSuccessful();

        $this->assertSame($prima, [Socio::count(), Rinnovo::count(), DB::table('gl_prima_nota')->count(), DB::table('gl_audit_log')->count(), DB::table('gl_scadenzario')->count()]);
        Storage::disk('local')->assertExists('ricevute/2026/1-2026.pdf');
        $this->assertSame([], Storage::disk('local')->files('backup'));
    }

    public function test_conferma_sbagliata_non_fa_niente(): void
    {
        $this->datiDiProva();
        $this->artisan('gestionale:azzera --esegui')
            ->expectsQuestion('Scrivi AZZERA per continuare', 'si')
            ->expectsOutputToContain('Niente fatto: conferma non data.')
            ->assertFailed();
        $this->assertSame(1, Socio::count());
        $this->assertSame([], Storage::disk('local')->files('backup'));
    }

    public function test_azzeramento(): void
    {
        [$pres, $paolo] = $this->datiDiProva();
        $categorie = DB::table('gl_prima_nota_categorie')->count();
        $promemoria = DB::table('gl_promemoria')->count();

        $this->artisan('gestionale:azzera --esegui')
            ->expectsQuestion('Scrivi AZZERA per continuare', 'AZZERA')
            ->expectsOutputToContain('Copia di sicurezza: storage/app/private/backup/azzeramento-20261120-213000.sql.gz')
            ->expectsOutputToContain('Audit Log ripartito: riga n. 1')
            ->assertSuccessful();

        // Svuotate, contatori compresi
        foreach (['gl_soci', 'gl_rinnovi', 'gl_prima_nota', 'gl_anni_contabili', 'gl_email_log', 'gl_scadenzario_rinnovi'] as $t) {
            $this->assertSame(0, DB::table($t)->count(), $t);
        }
        // Tenute
        $this->assertSame(['presidente'], $pres->fresh()->codiciRuoli());
        $this->assertSame(['segretario', 'tesoriere'], $paolo->fresh()->codiciRuoli());
        $this->assertSame($categorie, DB::table('gl_prima_nota_categorie')->count());
        $this->assertSame($promemoria, DB::table('gl_promemoria')->count());
        $this->assertTrue(DB::table('gl_tessere_tipi')->exists());
        $this->assertNotNull(DB::table('gl_associazione')->first());
        // Scadenze: solo le tre pronte, alla prossima data
        $this->assertSame(3, DB::table('gl_scadenzario')->count());
        $aff = DB::table('gl_scadenzario')->where('chiave', 'affiliazione_eps')->first();
        $this->assertSame('2026-12-31', Carbon::parse($aff->data_scadenza)->toDateString());
        $this->assertSame(0, (int) $aff->completato);
        $this->assertSame('2027-04-30', Carbon::parse(DB::table('gl_scadenzario')->where('chiave', 'rendiconto')->value('data_scadenza'))->toDateString());
        // Impostazioni
        $this->assertFalse(Impostazione::get('iscrizioni_aperte'));
        $this->assertFalse(Impostazione::get('domande_modalita_test'));
        $this->assertNull(Impostazione::get('audit_ultima_verifica'));
        $this->assertSame(2027, Impostazione::get('contabilita_anno_avvio'));
        // Audit Log: una riga, catena integra
        $this->assertSame(['AZZERAMENTO_DATI_PROVA'], DB::table('gl_audit_log')->pluck('evento')->all());
        $this->assertSame([], AuditIntegrityService::verificaCatena()['anomalie']);
        // File: cartelle di prova via, le altre restano, copia di sicurezza scritta
        Storage::disk('local')->assertMissing('ricevute/2026/1-2026.pdf');
        Storage::disk('local')->assertMissing('scontrini/2026/SC2026-001.jpg');
        Storage::disk('local')->assertExists('altro/da-non-toccare.txt');
        Storage::disk('local')->assertExists('backup/azzeramento-20261120-213000.sql.gz');
        $sql = gzdecode(Storage::disk('local')->get('backup/azzeramento-20261120-213000.sql.gz'));
        $this->assertStringContainsString("INSERT INTO `gl_soci`", $sql);
        $this->assertStringContainsString('VRDLCU90E10L117B', $sql);
        // Il gestionale non è rimasto in manutenzione
        $this->assertFalse(app()->isDownForMaintenance());
        // Ripartono i numeri
        $s = new Socio();
        $s->forceFill(['nome' => 'Anna', 'cognome' => 'Bianchi', 'codice_fiscale' => 'BNCNNA88D45L117B',
            'data_prima_iscrizione' => '2026-11-20', 'tipo' => 'ordinario', 'stato' => 'attivo'])->save();
        $this->assertSame(1, $s->id);
    }

    public function test_rifiuto_con_dati_veri(): void
    {
        $this->datiDiProva();
        DB::table('gl_anni_contabili')->update(['stato' => 'chiuso']);
        $this->artisan('gestionale:azzera')->expectsOutputToContain("Non si può azzerare: l'anno contabile 2026 è chiuso")->assertFailed();
        $this->artisan('gestionale:azzera --esegui --force')->assertFailed();
        $this->assertSame(1, Socio::count());

        DB::table('gl_anni_contabili')->update(['stato' => 'aperto']);
        DB::table('gl_prima_nota')->update(['data' => '2027-01-05']);
        $this->artisan('gestionale:azzera')->expectsOutputToContain('movimenti dal 2027')->assertFailed();
    }
}
