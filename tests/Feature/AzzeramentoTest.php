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
        $paolo->forceFill(['socio_id' => $s->id])->save(); // utente del CD collegato a un socio di prova (v0.42.0)
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
        $fk = DB::select('SELECT TABLE_NAME AS figlia, COLUMN_NAME AS colonna, REFERENCED_TABLE_NAME AS madre FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL');
        $tenute = [...Azzeramento::TIENI, ...Azzeramento::PARZIALI];
        foreach ($fk as $r) {
            // Colonne che il comando ripunta alla riga rimessa (l'utente tenuto al suo socio)
            if ((Azzeramento::RICOLLEGATE["{$r->figlia}.{$r->colonna}"] ?? null) === $r->madre) {
                continue;
            }
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
            ->expectsOutputToContain('Manca --tieni=<email>')
            ->expectsOutputToContain('Utenti che si possono tenere: Paolo Doppio (')
            ->expectsOutputToContain('php artisan gestionale:azzera --tieni=<email> --esegui')
            ->assertSuccessful();

        $this->assertSame($prima, [Socio::count(), Rinnovo::count(), DB::table('gl_prima_nota')->count(), DB::table('gl_audit_log')->count(), DB::table('gl_scadenzario')->count()]);
        Storage::disk('local')->assertExists('ricevute/2026/1-2026.pdf');
        $this->assertSame([], Storage::disk('local')->files('backup'));
    }

    public function test_conferma_sbagliata_non_fa_niente(): void
    {
        [, $paolo] = $this->datiDiProva();
        $this->artisan('gestionale:azzera --esegui --tieni=' . $paolo->email)
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

        $paoloPassword = $paolo->password;
        $this->artisan('gestionale:azzera --tieni=' . strtoupper($paolo->email))
            ->expectsOutputToContain('Andrea Presidente – Presidente')
            ->expectsOutputToContain("Il socio e l'utente Paolo Doppio (" . $paolo->email . '): unico socio, unico utente, Presidente nel CD')
            ->assertSuccessful();
        $this->artisan('gestionale:azzera --esegui --tieni=' . $paolo->email)
            ->expectsQuestion('Scrivi AZZERA per continuare', 'AZZERA')
            ->expectsOutputToContain('Copia di sicurezza: storage/app/private/backup/azzeramento-20261120-213000.sql.gz')
            ->expectsOutputToContain('Resta solo Paolo Doppio (')
            ->expectsOutputToContain('Audit Log ripartito: riga n. 1')
            ->assertSuccessful();

        // Resta solo il socio di Paolo, con il suo utente Presidente (v0.44.1, D-SU2)
        $this->assertSame(1, User::count());
        $u = User::first();
        $this->assertSame([1, 1, 'Paolo Doppio', ['presidente'], true, null], [$u->id, $u->socio_id, $u->name, $u->codiciRuoli(), $u->attivo, $u->eliminato_il]);
        $this->assertSame($paoloPassword, $u->password, 'si entra con la password di prima');
        $this->assertSame(['VRDLCU90E10L117B'], Socio::pluck('codice_fiscale')->all());
        $this->assertSame(1, Socio::first()->id);
        $cd = DB::table('gl_governance_cd')->get();
        $this->assertCount(1, $cd);
        $this->assertSame([1, 'presidente', 'Presidente', 1], [(int) $cd[0]->socio_id, $cd[0]->carica_codice, $cd[0]->carica, (int) $cd[0]->in_carica]);
        $this->assertSame(['presidente'], \App\Services\ConsiglioDirettivo::membri()->pluck('carica_codice')->all());

        // Svuotate, contatori compresi
        foreach (['gl_rinnovi', 'gl_prima_nota', 'gl_anni_contabili', 'gl_email_log', 'gl_scadenzario_rinnovi'] as $t) {
            $this->assertSame(0, DB::table($t)->count(), $t);
        }
        // Tenute
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
        $this->assertSame(2, $s->id);
    }

    public function test_senza_un_utente_da_tenere_non_si_azzera(): void
    {
        [$pres, $paolo] = $this->datiDiProva();
        $this->artisan('gestionale:azzera --esegui --force')
            ->expectsOutputToContain("Non si può azzerare: indica con --tieni=<email> l'utente da tenere")
            ->expectsOutputToContain('Utenti che si possono tenere: Paolo Doppio (' . $paolo->email . ')')
            ->assertFailed();
        // Il Presidente di prova non è collegato a un socio: non va bene
        $this->artisan('gestionale:azzera --esegui --force --tieni=' . $pres->email)
            ->expectsOutputToContain('deve essere attivo e collegato a un socio attivo')
            ->assertFailed();
        $this->artisan('gestionale:azzera --tieni=nessuno@example.com')
            ->expectsOutputToContain("nessun utente entra con l'email nessuno@example.com")
            ->assertFailed();
        $this->assertSame(1, Socio::count());
        $this->assertSame(2, User::count());
        $this->assertSame([], Storage::disk('local')->files('backup'));
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
