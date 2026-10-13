<?php

namespace Tests\Feature;

use App\Mail\OperazioneAvviso;
use App\Models\Rinnovo;
use App\Models\Socio;
use App\Models\User;
use App\Services\Tessere;
use App\Support\LettoreXlsx;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\PrimaNotaCategoriaSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Importazione dei soci dal modello Excel (v0.40.0, analisi-importazione-soci.md).
 * File di prova in tests/fixtures (dati di fantasia, codici fiscali inventati).
 */
class ImportazioneSociTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private User $segretario;

    protected function setUp(): void
    {
        parent::setUp();
        if (! class_exists(\ZipArchive::class)) {
            $this->fail("Manca l'estensione PHP «zip»: attivala (Laragon: menu PHP › Extensions › zip; deploy.md §6) e rilancia i test.");
        }
        $this->withoutVite();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class, PrimaNotaCategoriaSeeder::class]);
        \App\Models\Impostazione::set('contabilita_anno_avvio', 2027, 'integer');
        Mail::fake();
        Storage::fake('local');
        $this->presidente = User::factory()->conRuoli('presidente')->create(['name' => 'Andrea Presidente', 'email' => 'presidente@example.it']);
        $this->segretario = User::factory()->conRuoli('segretario')->create(['name' => 'Luigi Segretario', 'email' => 'segretario@example.it']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function file(string $nome): UploadedFile
    {
        return new UploadedFile(base_path("tests/fixtures/{$nome}.xlsx"), "{$nome}.xlsx", null, null, true);
    }

    public function test_lettore_xlsx_e_data_excel(): void
    {
        $l = new LettoreXlsx(resource_path('modelli/importazione-soci-modello.xlsx'));
        $this->assertSame(['Istruzioni', 'Soci'], $l->nomiFogli());
        $righe = $l->righe('Soci');
        $this->assertSame('Cognome *', $righe[1][0]);
        $this->assertSame('05014', $righe[2][4]);
        $this->assertSame('15/03/2019', LettoreXlsx::dataExcel($righe[2][9])->format('d/m/Y'));
        $this->assertSame(2, LettoreXlsx::colonna('C7'));
        $this->assertSame(27, LettoreXlsx::colonna('AB1'));

        $this->expectException(\RuntimeException::class);
        new LettoreXlsx(base_path('composer.json'));
    }

    public function test_permessi(): void
    {
        $this->actingAs($this->presidente)->get(route('soci.importa'))->assertOk()->assertSee('Scarica il modello');
        $this->actingAs($this->segretario)->get(route('soci.importa'))->assertOk();
        $this->actingAs($this->segretario)->get(route('soci.importa.modello'))->assertOk()->assertDownload('importazione-soci-modello.xlsx');
        $this->actingAs($this->segretario)->get(route('soci.index'))->assertSee('Importa da Excel');
        foreach (['tesoriere', 'consigliere', 'revisore'] as $ruolo) {
            $u = User::factory()->conRuoli($ruolo)->create();
            $this->actingAs($u)->get(route('soci.importa'))->assertForbidden();
        }
    }

    public function test_file_con_errori_non_si_importa(): void
    {
        $this->actingAs($this->segretario)->post(route('soci.importa.leggi'), ['file' => $this->file('soci-errati')])
            ->assertRedirect(route('soci.importa'));
        $this->actingAs($this->segretario)->get(route('soci.importa'))->assertOk()
            ->assertSee('4 righe con errori.')
            ->assertSee('Il codice fiscale non è corretto: controlla di averlo scritto bene.')
            ->assertSee('Minorenne: del genitore mancano codice fiscale, telefono e email.')
            ->assertSee('Codice fiscale ripetuto nel file (anche alla riga 6).')
            ->assertSee('«31/02/2020» non è una data', false)
            ->assertDontSee('Importa 4 soci');
        $this->actingAs($this->segretario)->post(route('soci.importa.conferma'))->assertRedirect(route('soci.importa'))->assertSessionHas('error');
        $this->assertSame(0, Socio::count());
        // Annulla: il file sparisce
        $this->actingAs($this->segretario)->post(route('soci.importa.annulla'));
        $this->assertSame([], Storage::disk('local')->files('tmp/soci-import'));
    }

    public function test_file_sbagliato(): void
    {
        $this->actingAs($this->segretario)->post(route('soci.importa.leggi'), ['file' => UploadedFile::fake()->create('soci.csv', 1)])
            ->assertSessionHasErrors('file');
        // Un .xlsx che non è il modello
        $finto = UploadedFile::fake()->createWithContent('soci.xlsx', 'non sono uno zip');
        $this->actingAs($this->segretario)->post(route('soci.importa.leggi'), ['file' => $finto]);
        $this->actingAs($this->segretario)->get(route('soci.importa'))->assertSee('Il file non è un foglio Excel (.xlsx) leggibile.');
    }

    public function test_importazione_completa(): void
    {
        $this->actingAs($this->presidente)->post(route('soci.importa.leggi'), ['file' => $this->file('soci-buoni')]);
        $this->actingAs($this->presidente)->get(route('soci.importa'))->assertOk()
            ->assertSee('Nessun errore.')->assertSee("Riga d'esempio scartata")->assertSee('Importa 4 soci')
            ->assertSee('10/09/2012 · Macerata (MC) · F', false);

        $this->actingAs($this->presidente)->post(route('soci.importa.conferma'))->assertRedirect(route('soci.importa.fatto'));
        $this->actingAs($this->presidente)->get(route('soci.importa.fatto'))->assertOk()->assertSee('Importati 4 soci.');

        $this->assertSame(4, Socio::where('importato', true)->count());
        $this->assertSame([], Storage::disk('local')->files('tmp/soci-import'));

        $anna = Socio::where('codice_fiscale', 'BNCNNA88D45L117B')->firstOrFail();
        $this->assertTrue((bool) $anna->fondatore);
        $this->assertSame('ordinario', $anna->tipo);
        $this->assertSame('TR', $anna->provincia);
        $this->assertSame('1988-04-05', $anna->data_nascita->toDateString());
        $this->assertSame('F', $anna->sesso);
        $this->assertSame('2019-03-15', $anna->data_prima_iscrizione->toDateString());
        $this->assertSame('2026-12-31', $anna->quota_scadenza->toDateString());
        $this->assertNull($anna->privacy_accettata_il);
        // Quota 2026: prima dell'avvio della contabilità, nessuna riga
        $this->assertSame(0, Rinnovo::where('socio_id', $anna->id)->count());

        $mario = Socio::where('codice_fiscale', 'RSSMRA75C12L117Q')->firstOrFail();
        $this->assertSame('05014', $mario->cap);
        $this->assertSame('3334444444', $mario->telefono);
        $this->assertNull($mario->quota_scadenza);

        $sofia = Socio::where('codice_fiscale', 'NRESFO12P50E783I')->firstOrFail();
        $this->assertSame('minorenne', $sofia->tipo);
        $this->assertSame('Neri Marco', $sofia->tutore_nome);
        $this->assertSame('Padre', $sofia->tutore_relazione);
        $this->assertSame('marco@example.it', $sofia->tutore_email);

        // Quote 2027 del vecchio gestionale: senza ricevuta né movimento
        $luca = Socio::where('codice_fiscale', 'VRDLCU90E10L117B')->firstOrFail();
        $r = Rinnovo::where('socio_id', $luca->id)->sole();
        $this->assertSame(2027, (int) $r->anno);
        $this->assertTrue($r->senzaRicevuta());
        $this->assertSame('70.00', $r->importo);
        $this->assertSame(0, \App\Models\PrimaNota::count());
        $rs = Rinnovo::where('socio_id', $sofia->id)->sole();
        $this->assertSame('giovane', $rs->categoria);
        $q = Tessere::quota($luca, Carbon::create(2027, 3, 1));
        $this->assertTrue($q['versata']);
        $this->assertStringContainsString('Quota 2027 del vecchio gestionale', $q['testo']);

        // Elenco «importati oggi», pagina dei rinnovi 2027 senza link alla ricevuta
        $this->actingAs($this->presidente)->get(route('soci.index', ['importati' => '2026-11-20', 'stato' => 'tutti']))
            ->assertSee('Soci importati il 20/11/2026.')->assertSee('Verdi');
        $this->actingAs($this->presidente)->get(route('rinnovi.index', ['anno' => 2027]))->assertOk()->assertSee('vecchio gestionale');
        $this->actingAs($this->presidente)->post(route('rinnovi.invia', $r))->assertStatus(422);

        // Audit e avviso al Segretario (non a chi ha importato)
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'IMPORT_SOCI', 'livello_rischio' => 'HIGH']);
        Mail::assertSent(OperazioneAvviso::class, fn ($m) => $m->hasTo('segretario@example.it') && $m->righe[0]['evento'] === 'IMPORT_SOCI');
        Mail::assertNotSent(OperazioneAvviso::class, fn ($m) => $m->hasTo('presidente@example.it'));

        // Lo stesso file di nuovo: tutti già presenti
        $this->actingAs($this->presidente)->post(route('soci.importa.leggi'), ['file' => $this->file('soci-buoni')]);
        $this->actingAs($this->presidente)->get(route('soci.importa'))->assertSee('Già nel libro soci: Bianchi Anna. Togli la riga.');
    }

    public function test_annullare_la_quota_importata(): void
    {
        $this->actingAs($this->presidente)->post(route('soci.importa.leggi'), ['file' => $this->file('soci-buoni')]);
        $this->actingAs($this->presidente)->post(route('soci.importa.conferma'));
        $luca = Socio::where('codice_fiscale', 'VRDLCU90E10L117B')->firstOrFail();
        $r = Rinnovo::where('socio_id', $luca->id)->sole();

        $tesoriere = User::factory()->conRuoli('tesoriere')->create();
        $this->actingAs($tesoriere)->post(route('rinnovi.annulla', $r), ['motivo' => 'Quota caricata per errore nel foglio'])
            ->assertSessionHas('success', 'Quota 2027 del vecchio gestionale annullata.');
        $this->assertTrue($r->fresh()->isAnnullato());
        // Resta la quota del vecchio gestionale fino al 31/12/2026
        $this->assertSame('2026-12-31', $luca->fresh()->quota_scadenza->toDateString());
    }
}
