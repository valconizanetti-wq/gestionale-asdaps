<?php

namespace Tests\Feature;

use App\Mail\AuditAnomalia;
use App\Models\AuditLogIntegrity;
use App\Models\Socio;
use App\Models\User;
use App\Services\AuditIntegrityService;
use App\Services\AuditLogService;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Audit Log: consultazione, verifica, certificato, attestazione, verifica
 * mensile (v0.34.0, audit-log.md §9). Gira su MySQL come gli altri test.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private User $revisore;

    private User $segretario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class]);
        Mail::fake();
        Storage::fake('local');
        $this->presidente = User::factory()->create(['ruolo' => 'presidente', 'attivo' => true, 'email' => 'pres@esempio.it']);
        $this->revisore = User::factory()->create(['ruolo' => 'revisore', 'attivo' => true, 'email' => 'rev@esempio.it']);
        $this->segretario = User::factory()->create(['ruolo' => 'segretario', 'attivo' => true]);
    }

    private function socio(string $nome = 'Luca'): Socio
    {
        $s = new Socio();
        $s->forceFill(['nome' => $nome, 'cognome' => 'Verdi', 'codice_fiscale' => strtoupper(substr(md5($nome), 0, 16)),
            'data_prima_iscrizione' => '2026-01-01', 'tipo' => 'ordinario', 'stato' => 'attivo'])->save();

        return $s;
    }

    public function test_elenco_filtri_dettaglio_e_permessi(): void
    {
        $s = $this->socio();
        $s->update(['email' => 'nuova@esempio.it']);
        AuditLogService::scrivi(['evento' => 'PRIVACY_LINK_INVIATO', 'entita_tipo' => 'Socio', 'entita_id' => $s->id, 'dettaglio' => 'Link privacy n. 1 inviato (socio).']);

        $this->actingAs($this->presidente)->get(route('audit-log.index'))->assertOk()
            ->assertSee('Registro delle operazioni')->assertSee('Link privacy inviato')->assertSee('Modifica')
            ->assertSee('Prima')->assertSee('nuova@esempio.it')
            ->assertSee("Certificato dell'anno non ancora generato", false);

        $this->actingAs($this->presidente)->get(route('audit-log.index', ['evento' => 'PRIVACY_LINK_INVIATO']))->assertOk()
            ->assertSee('Link privacy n. 1 inviato')->assertDontSee('Socio aggiornato');
        // % scritto da chi cerca non vale come jolly
        $this->actingAs($this->presidente)->get(route('audit-log.index', ['cerca' => '%']))->assertOk()->assertSee('Nessuna operazione corrisponde');
        // Senza righe filtrate, niente esportazione (prima usciva un file vuoto)
        $this->actingAs($this->presidente)->get(route('audit-log.index', ['cerca' => '%']))->assertDontSee(route('audit-log.export-csv', ['cerca' => '%']), false);

        $this->actingAs($this->revisore)->get(route('audit-log.index'))->assertOk();
        $this->actingAs($this->segretario)->get(route('audit-log.index'))->assertForbidden();
    }

    public function test_esportazioni_registrate(): void
    {
        $this->socio();
        $csv = $this->actingAs($this->revisore)->get(route('audit-log.export-csv'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('SOCIO_CREATED', $csv->streamedContent());
        $this->actingAs($this->revisore)->get(route('audit-log.export-pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(2, DB::table('gl_audit_log')->where('evento', 'AUDIT_ESPORTATO')->count());
    }

    public function test_verifica_e_certificato(): void
    {
        $this->socio('Anna');
        $this->socio('Bruno');

        $this->actingAs($this->revisore)->post(route('audit-log.verifica.esegui'), ['anno' => now()->year])
            ->assertRedirect(route('audit-log.verifica', ['anno' => now()->year]));
        $this->actingAs($this->revisore)->get(route('audit-log.verifica', ['anno' => now()->year]))
            ->assertSee('Catena integra.')->assertSee('Genera il certificato');

        // Il Revisore (sola lettura) può generare il certificato; il segretario non vede la sezione
        $this->actingAs($this->revisore)->post(route('audit-log.certificati.genera'), ['anno' => now()->year])->assertSessionHas('success');
        $cert = AuditLogIntegrity::firstOrFail();
        $this->assertNotNull($cert->uuid_documento);
        $this->assertStringContainsString('(Revisore)', $cert->generato_da);
        Storage::disk('local')->assertExists(\App\Http\Controllers\AuditLogController::percorsoCertificato($cert->anno, $cert->uuid_documento));

        // Secondo certificato dello stesso anno: un file nuovo, il primo resta
        $this->actingAs($this->presidente)->post(route('audit-log.certificati.genera'), ['anno' => now()->year])->assertSessionHas('success');
        $this->assertSame(2, AuditLogIntegrity::count());
        foreach (AuditLogIntegrity::all() as $c) {
            $this->actingAs($this->presidente)->get(route('audit-log.certificati.scarica', $c))->assertOk();
        }

        $this->actingAs($this->presidente)->get(route('audit-log.index'))->assertSee('Registro integro e certificato');
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'CERTIFICATO_INTEGRITA_GENERATO', 'livello_rischio' => 'MEDIUM', 'operazione' => null]);
        $this->actingAs($this->segretario)->post(route('audit-log.certificati.genera'), ['anno' => now()->year])->assertForbidden();
    }

    public function test_anomalia_blocca_il_certificato_e_avvisa(): void
    {
        $this->socio('Anna');
        $this->socio('Bruno');
        // manomissione: dettaglio cambiato a mano
        DB::table('gl_audit_log')->where('id', DB::table('gl_audit_log')->min('id'))->update(['dettaglio' => 'Riga cambiata a mano.']);

        $this->artisan('audit:verifica-integrita')->assertExitCode(1);
        Mail::assertSent(AuditAnomalia::class, 2); // Presidente e Revisore
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'AUDIT_INTEGRITY_FAIL', 'livello_rischio' => 'HIGH']);

        $this->actingAs($this->presidente)->get(route('audit-log.index'))->assertSee('la catena risulta alterata');
        $this->actingAs($this->presidente)->post(route('audit-log.certificati.genera'), ['anno' => now()->year])->assertSessionHas('error');
        $this->assertSame(0, AuditLogIntegrity::count());

        $this->actingAs($this->presidente)->post(route('audit-log.verifica.esegui'), ['anno' => now()->year]);
        $this->actingAs($this->presidente)->get(route('audit-log.verifica'))->assertSee('Contenuto: l&#039;impronta non si ricalcola', false)
            ->assertDontSee('Genera il certificato');
    }

    public function test_senza_revisore_avvisa_solo_il_presidente(): void
    {
        $this->revisore->delete();
        $this->socio();
        DB::table('gl_audit_log')->update(['utente_nome' => 'altro']);
        $this->artisan('audit:verifica-integrita')->assertExitCode(1);
        Mail::assertSent(AuditAnomalia::class, 1);
        Mail::assertSent(AuditAnomalia::class, fn ($m) => $m->hasTo('pres@esempio.it'));
    }

    public function test_la_catena_non_dipende_dal_fuso_di_mysql(): void
    {
        $this->socio('Anna');
        $this->socio('Bruno');
        $this->assertSame([], AuditIntegrityService::verificaCatena()['anomalie']);

        // Un altro fuso della connessione (nuovo server, altro provider)
        DB::statement("SET time_zone = '+05:00'");
        $this->assertSame([], AuditIntegrityService::verificaCatena()['anomalie']);
        $this->artisan('audit:verifica-integrita')->assertExitCode(0);
    }

    public function test_attestazione_tecnica(): void
    {
        $this->socio();
        $this->actingAs($this->presidente)->get(route('audit-log.attestazioni.bozza'))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->presidente)->post(route('audit-log.attestazioni.carica'), [
            'oggetto' => 'Verifica annuale', 'data_firma' => today()->toDateString(),
            'file' => UploadedFile::fake()->createWithContent('firmata.pdf', self::PDF_FIRMATO),
            'relazione' => UploadedFile::fake()->create('relazione.pdf', 50, 'application/pdf'),
        ])->assertSessionHas('success');

        $id = DB::table('gl_attestazioni_tecniche')->value('id');
        $this->actingAs($this->revisore)->get(route('audit-log.attestazioni.scarica', $id))->assertOk();
        $this->actingAs($this->revisore)->get(route('audit-log.attestazioni.scarica', [$id, 'relazione']))->assertOk();
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'ATTESTAZIONE_TECNICA_CARICATA']);

        $this->actingAs($this->presidente)->post(route('audit-log.attestazioni.carica'), ['oggetto' => 'X', 'data_firma' => today()->toDateString(),
            'file' => UploadedFile::fake()->create('a.txt', 10, 'text/plain')])->assertSessionHasErrors('file');

        // PDF senza firma digitale: rifiutato, con il motivo accanto al campo
        $this->actingAs($this->presidente)->post(route('audit-log.attestazioni.carica'), ['oggetto' => 'X', 'data_firma' => today()->toDateString(),
            'file' => UploadedFile::fake()->createWithContent('bozza.pdf', "%PDF-1.7\n1 0 obj << /Type /Catalog >> endobj\n%%EOF")])
            ->assertSessionHasErrors(['file' => "Nel file non c'è una firma digitale: firmalo (firma digitale o CIE) e caricalo di nuovo."]);

        // Busta .p7m (CAdES), binaria e in base64: accettata
        $p7m = "\x30\x80\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x07\x02\xA0\x80" . str_repeat("\x00", 32);
        foreach ([$p7m, base64_encode($p7m)] as $contenuto) {
            $this->actingAs($this->presidente)->post(route('audit-log.attestazioni.carica'), ['oggetto' => 'Con CIE', 'data_firma' => today()->toDateString(),
                'file' => UploadedFile::fake()->createWithContent('attestazione.pdf.p7m', $contenuto)])->assertSessionHas('success');
        }
        $this->assertSame(3, DB::table('gl_attestazioni_tecniche')->count());
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'ATTESTAZIONE_TECNICA_CARICATA', 'dettaglio' => 'Attestazione tecnica n. ' . $id . ' firmata il ' . today()->format('d/m/Y') . ' (firma PAdES); eccezioni note: 0. Con relazione tecnica.']);
    }

    /** Righe per pagina: scelta ricordata per la sessione, "Tutte" senza paginazione. */
    public function test_righe_per_pagina(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            AuditLogService::scrivi(['evento' => 'PRIVACY_LINK_INVIATO', 'dettaglio' => "Riga di prova {$i}."]);
        }

        $n = DB::table('gl_audit_log')->count();

        $this->actingAs($this->presidente)->get(route('audit-log.index', ['per_pagina' => 10]))->assertOk()
            ->assertSee("Operazioni 1–10 di {$n}")->assertSee('Righe per pagina');
        $this->actingAs($this->presidente)->get(route('audit-log.index'))->assertSee("Operazioni 1–10 di {$n}");
        $this->actingAs($this->presidente)->get(route('audit-log.index', ['per_pagina' => 0]))->assertSee("Operazioni 1–{$n} di {$n}");
        // Valore non previsto: resta la scelta precedente
        $this->actingAs($this->presidente)->get(route('audit-log.index', ['per_pagina' => 7]))->assertSee("Operazioni 1–{$n} di {$n}");
    }

    /** PDF con un dizionario di firma (PAdES), ridotto all'essenziale per il controllo. */
    private const PDF_FIRMATO = "%PDF-1.7\n1 0 obj << /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /ByteRange [0 120 2000 300] /Contents <3082> >> endobj\n%%EOF";
}
