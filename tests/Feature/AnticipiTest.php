<?php

namespace Tests\Feature;

use App\Mail\OperazioneAvviso;
use App\Models\AnnoContabile;
use App\Models\GovernanceCd;
use App\Models\NotaRimborso;
use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\PrimaNotaScontrino;
use App\Models\Socio;
use App\Models\User;
use App\Services\AnniContabili;
use App\Services\NoteRimborso;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\PrimaNotaCategoriaSeeder;
use Database\Seeders\ProfiloEnteSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Anticipi e note di rimborso, v0.43.0 (specifiche/anticipi.md, tavole
 * AN-1…AN-4b, analisi-moduli-anticipi.md §2.2-bis, §2.4, §2.8).
 */
class AnticipiTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private User $tesoriere;

    private User $revisore;

    private User $consigliere;

    private Socio $papini;

    private Socio $zanetti;

    private Socio $tascio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2027-03-15 10:00:00');
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class,
            PrimaNotaCategoriaSeeder::class, ProfiloEnteSeeder::class]);
        Mail::fake();
        Storage::fake('local');
        AnnoContabile::create(['anno' => 2027, 'saldo_apertura_cassa' => 500, 'saldo_apertura_banca' => 1000]);

        $this->papini = $this->socio('Papini', 'Andrea', 'PPNNDR70A01C100X');
        $this->zanetti = $this->socio('Zanetti', 'Paolo', 'ZNTPLA60A01C100X');
        $this->tascio = $this->socio('Tascio', 'Luigi', 'TSCLGU70A01C100X');
        $this->carica($this->papini, 'presidente', 'Presidente');
        $this->carica($this->zanetti, 'segretario_tesoriere', 'Segretario e Tesoriere');
        $this->carica($this->tascio, 'consigliere', 'Consigliere');

        $this->presidente = User::factory()->conRuoli('presidente')->create(['name' => 'Andrea Papini', 'email' => 'presidente@example.it', 'attivo' => true, 'socio_id' => $this->papini->id]);
        $this->tesoriere = User::factory()->conRuoli('segretario', 'tesoriere')->create(['name' => 'Paolo Zanetti', 'email' => 'tesoriere@example.it', 'attivo' => true, 'socio_id' => $this->zanetti->id]);
        $this->consigliere = User::factory()->conRuoli('consigliere')->create(['name' => 'Luigi Tascio', 'email' => 'consigliere@example.it', 'attivo' => true, 'socio_id' => $this->tascio->id]);
        $this->revisore = User::factory()->conRuoli('revisore')->create(['name' => 'Rita Revisore', 'email' => 'revisore@example.it', 'attivo' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ----------------------------------------------------------------
    // Supporto
    // ----------------------------------------------------------------

    private function socio(string $cognome, string $nome, string $cf, array $altro = []): Socio
    {
        $s = new Socio();
        $s->forceFill($altro + ['cognome' => $cognome, 'nome' => $nome, 'codice_fiscale' => $cf, 'tipo' => 'ordinario',
            'stato' => 'attivo', 'data_nascita' => '1970-01-01', 'data_prima_iscrizione' => '2024-07-24'])->save();

        return $s;
    }

    private function carica(Socio $s, string $codice, string $etichetta): void
    {
        (new GovernanceCd())->forceFill(['socio_id' => $s->id, 'carica' => $etichetta, 'carica_codice' => $codice,
            'data_inizio_mandato' => '2026-11-16', 'in_carica' => true])->save();
    }

    private function cat(string $nome, string $tipo = 'uscita'): int
    {
        return PrimaNotaCategoria::where('tipo', $tipo)->where('nome', $nome)->value('id');
    }

    /** Spesa anticipata dal modulo (AN-1), con un giustificativo. */
    private function spesa(Socio $s, string $importo, string $data, string $descrizione, string $categoria = 'Taglio erba'): PrimaNota
    {
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), [
            'tipo' => 'uscita', 'anticipata' => '1', 'anticipante_socio_id' => $s->id,
            'data' => $data, 'importo' => $importo, 'categoria_id' => $this->cat($categoria),
            'descrizione' => $descrizione, 'soggetto' => 'Ferramenta Bianchi', 'tipo_documento' => 'scontrino',
            'documenti' => [UploadedFile::fake()->image('scontrino.jpg')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return PrimaNota::latest('id')->firstOrFail();
    }

    private function nota(Socio $s, array $spese, array $dati = []): NotaRimborso
    {
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.store'), $dati + [
            'socio_id' => $s->id, 'spese' => array_map(fn ($m) => $m->id, $spese), 'tipo' => 'rimborso',
            'data_nota' => '2027-02-14', 'modalita' => 'cassa',
        ])->assertRedirect()->assertSessionHasNoErrors();

        return NotaRimborso::latest('id')->firstOrFail();
    }

    private function firma(NotaRimborso $n): void
    {
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.firmata', $n), [
            'firmata' => UploadedFile::fake()->create('nota firmata.pdf', 30, 'application/pdf'),
        ])->assertRedirect(route('prima-nota.anticipi.show', $n))->assertSessionHasNoErrors();
    }

    private function saldi(): array
    {
        return app(AnniContabili::class)->finali(2027);
    }

    // ----------------------------------------------------------------
    // AN-1: la spesa anticipata
    // ----------------------------------------------------------------

    public function test_spesa_anticipata_dal_modulo(): void
    {
        $prima = $this->saldi();
        $r = $this->actingAs($this->tesoriere)->get(route('prima-nota.create', ['anticipata' => 1]))->assertOk()
            ->assertSee('Anticipata da un socio (pagata di tasca propria)')->assertSee('Zanetti Paolo · Segretario e Tesoriere');
        // Prima i membri del CD, nell'ordine delle cariche
        $this->assertLessThan(strpos($r->getContent(), 'Tascio Luigi · Consigliere'), strpos($r->getContent(), 'Papini Andrea · Presidente'));

        // Senza socio e senza giustificativo (E4) non si registra
        $base = ['tipo' => 'uscita', 'anticipata' => '1', 'data' => '2027-02-20', 'importo' => '38,90',
            'categoria_id' => $this->cat('Spese ufficio'), 'descrizione' => 'Toner per la stampante'];
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $base)
            ->assertSessionHasErrors(['anticipante_socio_id', 'documenti']);
        $this->assertSame(0, PrimaNota::count());

        // Un socio minorenne non anticipa (E5)
        $minore = $this->socio('Rossi', 'Anna', 'RSSNNA12A41C100X', ['data_nascita' => '2012-01-01', 'tipo' => 'minorenne']);
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $base + ['anticipante_socio_id' => $minore->id,
            'documenti' => [UploadedFile::fake()->image('s.jpg')]])->assertSessionHasErrors('anticipante_socio_id');

        $m = $this->spesa($this->zanetti, '38,90', '2027-02-20', 'Toner per la stampante', 'Spese ufficio');
        $this->assertTrue($m->isAnticipoAperto());
        $this->assertNull($m->conto);
        $this->assertSame($this->zanetti->id, $m->anticipante_socio_id);
        $this->assertSame('2027-02-20', $m->data_spesa->toDateString());
        $this->assertSame(1, $m->documenti()->count());
        // Fuori dai saldi
        $this->assertSame($prima, $this->saldi());

        $this->actingAs($this->revisore)->get(route('prima-nota.index'))->assertOk()
            ->assertSee('anticipata da Zanetti Paolo')->assertSee('Da rimborsare');
        $this->actingAs($this->revisore)->get(route('prima-nota.show', $m))->assertOk()
            ->assertSee('Spesa anticipata da Zanetti Paolo.')->assertSee('Da rimborsare a Zanetti Paolo');
    }

    // ----------------------------------------------------------------
    // AN-3, AN-4, AN-2: nota, firma, pagamento
    // ----------------------------------------------------------------

    public function test_nota_firma_e_pagamento(): void
    {
        $prima = $this->saldi();
        $benzina = $this->spesa($this->tascio, '64,50', '2027-02-03', 'Benzina per il decespugliatore');
        $filo = $this->spesa($this->tascio, '12,90', '2027-02-09', 'Filo per decespugliatore');
        $toner = $this->spesa($this->zanetti, '38,90', '2027-02-10', 'Toner per la stampante', 'Spese ufficio');

        $this->actingAs($this->revisore)->get(route('prima-nota.anticipi.index'))->assertOk()
            ->assertSee('116,30 €')->assertSee('3 spese di 2 soci')
            ->assertSee('Tascio Luigi · Consigliere')->assertSee('Zanetti Paolo · Segretario e Tesoriere')
            ->assertDontSee('Prepara la nota di rimborso');
        $this->actingAs($this->tesoriere)->get(route('prima-nota.anticipi.index'))->assertSee('Prepara la nota di rimborso');
        // Revisione, giro 3: chi consulta non prepara note
        $this->actingAs($this->revisore)->get(route('prima-nota.anticipi.create', ['socio' => $this->tascio->id, 'spese' => [$benzina->id]]))->assertForbidden();
        $this->actingAs($this->consigliere)->post(route('prima-nota.anticipi.store'), [
            'socio_id' => $this->tascio->id, 'spese' => [$benzina->id], 'tipo' => 'rimborso', 'data_nota' => '2027-02-14', 'modalita' => 'cassa',
        ])->assertForbidden();
        $this->actingAs($this->consigliere)->get(route('prima-nota.anticipi.index'))->assertOk()->assertDontSee('Prepara la nota di rimborso');

        // Passo 1: le spese di due soci non vanno nella stessa nota
        $this->actingAs($this->tesoriere)->get(route('prima-nota.anticipi.create', ['socio' => $this->tascio->id, 'spese' => [$benzina->id, $toner->id]]))
            ->assertRedirect(route('prima-nota.anticipi.index'))->assertSessionHas('error', 'Le spese di una nota devono essere tutte dello stesso socio.');
        $this->actingAs($this->tesoriere)->get(route('prima-nota.anticipi.create', ['socio' => $this->tascio->id, 'spese' => [$benzina->id, $filo->id]]))
            ->assertOk()->assertSee('77,40 €')->assertSee('RIMB-2027-001')->assertSeeText('Visto: il Tesoriere (Paolo Zanetti).');
        // Data della nota prima della spesa più recente
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.store'), [
            'socio_id' => $this->tascio->id, 'spese' => [$benzina->id, $filo->id], 'tipo' => 'rimborso', 'data_nota' => '2027-02-05', 'modalita' => 'cassa',
        ])->assertSessionHasErrors('data_nota');
        $this->assertSame(0, NotaRimborso::count());

        $n = $this->nota($this->tascio, [$benzina, $filo]);
        $this->assertSame('RIMB-2027-001', $n->numero);
        $this->assertSame('emessa', $n->stato);
        $this->assertSame('77.40', (string) $n->totale);
        $this->assertSame('Consigliere', $n->dati['socio']['carica']);
        $this->assertSame(['carica' => 'il Tesoriere', 'nome' => 'Paolo Zanetti'], $n->dati['visto']);
        Storage::disk('local')->assertExists('prima-nota/rimborsi/2027/RIMB-2027-001.pdf');
        $this->assertSame($n->id, $benzina->fresh()->nota_rimborso_id);
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'NOTA_RIMBORSO_EMESSA')->where('entita_id', $n->id)->exists());

        // Il PDF (AN-2) con la quietanza dei contanti (E1)
        $html = view('pdf.nota-rimborso', ['n' => $n, 'dataDocumento' => $n->data_nota])->render();
        $this->assertStringContainsString('Nota di rimborso spese', $html);
        $this->assertStringContainsString('settantasette', $html);
        $this->assertStringContainsString('Per quietanza.', $html);
        $this->assertStringContainsString('Visto, il Tesoriere', $html);
        $this->assertStringContainsString('TSCLGU70A01C100X', $html);
        $this->actingAs($this->revisore)->get(route('prima-nota.anticipi.pdf', $n))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // In una nota la spesa non si elimina e importo e data non cambiano
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.destroy', $benzina), ['conferma' => 'si'])->assertSessionHas('error');
        $this->actingAs($this->tesoriere)->put(route('prima-nota.update', $benzina), [
            'tipo' => 'uscita', 'conto' => 'banca', 'data' => '2027-01-01', 'importo' => '1,00', 'anticipata' => '0',
            'categoria_id' => $this->cat('Acquisto materiali'), 'descrizione' => 'Benzina (miscela)',
        ])->assertRedirect(route('prima-nota.show', $benzina));
        $benzina->refresh();
        $this->assertSame('64.50', (string) $benzina->importo);
        $this->assertSame('2027-02-03', $benzina->data->toDateString());
        $this->assertTrue($benzina->isAnticipoAperto());
        $this->assertSame('Benzina (miscela)', $benzina->descrizione);

        // Passo 3 prima della firma: no
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.pagamento', $n), ['data_pagamento' => '2027-02-16'])
            ->assertSessionHasErrorsIn('pagamento', 'data_pagamento');

        // Passo 2: copia firmata
        $this->actingAs($this->revisore)->post(route('prima-nota.anticipi.firmata', $n), ['firmata' => UploadedFile::fake()->image('f.jpg')])->assertForbidden();
        $this->firma($n);
        $n->refresh();
        $this->assertSame('firmata', $n->stato);
        Storage::disk('local')->assertExists($n->firmata_path);
        $this->actingAs($this->revisore)->get(route('prima-nota.anticipi.firmata.show', $n))->assertOk();

        // Passo 3: data prima della nota, poi giusta
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.pagamento', $n), ['data_pagamento' => '2027-02-13'])
            ->assertSessionHasErrorsIn('pagamento', 'data_pagamento');
        $this->assertSame($prima, $this->saldi());
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.pagamento', $n), ['data_pagamento' => '2027-02-16'])
            ->assertRedirect(route('prima-nota.anticipi.show', $n))->assertSessionHasNoErrors();
        $n->refresh();
        $this->assertSame('pagata', $n->stato);
        $this->assertSame('cassa', $n->conto);   // E3: dalla modalità della nota
        $benzina->refresh();
        $this->assertSame('2027-02-16', $benzina->data->toDateString());          // A1
        $this->assertSame('2027-02-03', $benzina->data_spesa->toDateString());
        $this->assertSame('cassa', $benzina->conto);
        $this->assertFalse($benzina->isAnticipoAperto());
        $this->assertEqualsWithDelta($prima['cassa'] - 77.40, $this->saldi()['cassa'], 0.001);
        $this->assertSame($prima['banca'], $this->saldi()['banca']);
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'NOTA_RIMBORSO_PAGATA')->where('livello_rischio', 'HIGH')->exists());
        // Rischio alto: avviso agli altri, non a chi l'ha fatto (A5)
        Mail::assertSent(OperazioneAvviso::class, fn ($m) => $m->hasTo('presidente@example.it'));
        Mail::assertNotSent(OperazioneAvviso::class, fn ($m) => $m->hasTo('tesoriere@example.it'));

        $this->actingAs($this->revisore)->get(route('prima-nota.anticipi.index'))->assertOk()
            ->assertSee('Pagata il 16/02/2027 · Cassa')->assertSee('77,40 €');
        $this->actingAs($this->revisore)->get(route('prima-nota.anticipi.show', $n))->assertOk()
            ->assertSee('RIMB-2027-001')->assertSee('Benzina per il decespugliatore')->assertDontSee('Annulla il pagamento');

        // Una nota pagata non si annulla: prima il pagamento
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.annulla', $n), ['motivazione' => 'Prova di annullamento della nota'])
            ->assertSessionHasErrorsIn('annulla', 'motivazione');

        // Annullamento del pagamento (difetto 7)
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.annulla-pagamento', $n), ['motivazione' => 'corto'])
            ->assertSessionHasErrorsIn('annulla_pagamento', 'motivazione');
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.annulla-pagamento', $n), ['motivazione' => 'Pagamento registrato due volte per errore'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $n->refresh();
        $this->assertSame('firmata', $n->stato);
        $benzina->refresh();
        $this->assertTrue($benzina->isAnticipoAperto());
        $this->assertSame('2027-02-03', $benzina->data->toDateString());
        $this->assertNull($benzina->conto);
        $this->assertSame($prima, $this->saldi());
    }

    public function test_bonifico_iban_e_conto_fissato(): void
    {
        $toner = $this->spesa($this->tascio, '37,40', '2027-02-20', 'Cartucce', 'Spese ufficio');
        $dati = ['socio_id' => $this->tascio->id, 'spese' => [$toner->id], 'tipo' => 'rimborso', 'data_nota' => '2027-02-23', 'modalita' => 'banca'];

        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.store'), $dati)->assertSessionHasErrors('iban');
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.store'), $dati + ['iban' => 'IT60 X054 2811 1010 0000 0123 457'])->assertSessionHasErrors('iban');
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.store'), $dati + ['iban' => 'it60 x054 2811 1010 0000 0123 456'])->assertSessionHasNoErrors();
        $n = NotaRimborso::sole();
        $this->assertSame('IT60X0542811101000000123456', $n->iban);
        $html = view('pdf.nota-rimborso', ['n' => $n, 'dataDocumento' => $n->data_nota])->render();
        $this->assertStringContainsString('Con bonifico bancario', $html);
        $this->assertStringNotContainsString('Per quietanza.', $html);

        $this->firma($n);
        // Il conto lo decide la nota (E3): un «conto» mandato a mano non conta
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.pagamento', $n), ['data_pagamento' => '2027-02-24', 'conto' => 'cassa'])->assertSessionHasNoErrors();
        $this->assertSame('banca', $toner->fresh()->conto);
    }

    public function test_annullata_il_numero_non_si_riusa(): void
    {
        $a = $this->spesa($this->papini, '20,00', '2027-02-14', 'Spesa scritta due volte');
        $b = $this->spesa($this->papini, '45,00', '2027-02-14', 'Bersagli');

        $n1 = $this->nota($this->papini, [$a], ['data_nota' => '2027-02-15']);
        $this->firma($n1);
        $this->actingAs($this->revisore)->post(route('prima-nota.anticipi.annulla', $n1), ['motivazione' => 'Spesa scritta due volte'])->assertForbidden();
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.annulla', $n1), ['motivazione' => 'Spesa scritta due volte'])
            ->assertRedirect(route('prima-nota.anticipi.show', $n1))->assertSessionHasNoErrors();
        $n1->refresh();
        $this->assertSame('annullata', $n1->stato);
        $this->assertNull($a->fresh()->nota_rimborso_id);
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'NOTA_RIMBORSO_ANNULLATA')->where('livello_rischio', 'HIGH')->exists());
        $html = view('pdf.nota-rimborso', ['n' => $n1, 'dataDocumento' => $n1->data_nota])->render();
        $this->assertStringContainsString('ANNULLATA il', $html);

        // La spesa doppia, tornata libera, si elimina
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.destroy', $a), ['conferma' => 'si'])->assertSessionHasNoErrors();

        // La nota dopo ha il numero successivo (difetto 1 del Dante)
        $n2 = $this->nota($this->papini, [$b], ['data_nota' => '2027-02-15']);
        $this->assertSame('RIMB-2027-002', $n2->numero);
        $this->actingAs($this->revisore)->get(route('prima-nota.anticipi.index'))->assertOk()
            ->assertSee('RIMB-2027-001')->assertSee('Annullata')->assertSee('Spesa scritta due volte')->assertSee('RIMB-2027-002');
    }

    /** Revisione, giro 1 (R1.1): la scheda di un socio con spese anticipate non si elimina. */
    public function test_socio_con_anticipi_non_si_elimina(): void
    {
        $socio = $this->socio('Bianchi', 'Carlo', 'BNCCRL70A01C100X');
        $this->spesa($socio, '9,90', '2027-02-22', 'Nastro');
        $this->actingAs($this->presidente)->delete(route('soci.destroy', $socio), ['motivo' => 'Inserito per errore durante una prova'])
            ->assertSessionHas('error', fn ($e) => str_contains($e, 'ha spese anticipate o note di rimborso'));
        $this->assertFalse($socio->fresh()->trashed());
    }

    public function test_da_classificare_non_va_in_nota(): void
    {
        $m = $this->spesa($this->tascio, '12,40', '2027-02-22', 'Colla');
        PrimaNota::whereKey($m->id)->update(['categoria_id' => PrimaNotaCategoria::where('codice', PrimaNotaCategoria::DA_CLASSIFICARE)->value('id')]);
        $this->actingAs($this->tesoriere)->get(route('prima-nota.anticipi.index'))->assertOk()->assertSee('Da classificare');
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.store'), [
            'socio_id' => $this->tascio->id, 'spese' => [$m->id], 'tipo' => 'rimborso', 'data_nota' => '2027-02-23', 'modalita' => 'cassa',
        ])->assertSessionHasErrors(['spese' => 'Prima va scelta la categoria: una spesa «da classificare» non va in una nota.']);
    }

    public function test_visto_del_presidente_sulla_nota_del_tesoriere(): void
    {
        $m = $this->spesa($this->zanetti, '38,90', '2027-02-20', 'Toner', 'Spese ufficio');
        $n = $this->nota($this->zanetti, [$m], ['data_nota' => '2027-02-24']);
        $this->assertSame(['carica' => 'il Presidente', 'nome' => 'Andrea Papini'], $n->dati['visto']);
        $this->assertStringContainsString('Visto, il Presidente', view('pdf.nota-rimborso', ['n' => $n, 'dataDocumento' => $n->data_nota])->render());
    }

    // ----------------------------------------------------------------
    // AN-4b: rinuncia al rimborso
    // ----------------------------------------------------------------

    public function test_rinuncia_al_rimborso(): void
    {
        $prima = $this->saldi();
        $m = $this->spesa($this->zanetti, '38,90', '2027-02-20', 'Toner per la stampante', 'Spese ufficio');
        $this->actingAs($this->tesoriere)->get(route('prima-nota.anticipi.create', ['socio' => $this->zanetti->id, 'spese' => [$m->id], 'tipo' => 'rinuncia']))
            ->assertOk()->assertSee('Rinuncia al')->assertSee('Genera la dichiarazione (PDF)');
        $n = $this->nota($this->zanetti, [$m], ['tipo' => 'rinuncia', 'data_nota' => '2027-02-24', 'modalita' => null]);
        $this->assertTrue($n->isRinuncia());
        $this->assertSame('RIMB-2027-001', $n->numero);
        $html = view('pdf.nota-rimborso', ['n' => $n, 'dataDocumento' => $n->data_nota])->render();
        $this->assertStringContainsString('Dichiarazione di rinuncia al rimborso', $html);
        $this->assertStringContainsString('rinunciare al loro rimborso', $html);
        $this->assertStringNotContainsString('Per quietanza.', $html);

        $this->firma($n);
        $this->actingAs($this->tesoriere)->get(route('prima-nota.anticipi.show', $n))->assertOk()
            ->assertSee('Firmata · da registrare')->assertSee('Registra la donazione')->assertSee('Contributi e donazioni da terzi');
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.pagamento', $n), ['data_rinuncia' => '2027-02-24'])->assertSessionHasNoErrors();

        $n->refresh();
        $this->assertSame('pagata', $n->stato);
        $this->assertSame('Registrata il 24/02/2027', $n->statoTesto());
        $entrata = PrimaNota::where('tipo', 'entrata')->where('nota_rimborso_id', $n->id)->sole();
        $this->assertSame('cassa', $entrata->conto);
        $this->assertSame('38.90', (string) $entrata->importo);
        $this->assertSame(PrimaNotaCategoria::DONAZIONI, $entrata->categoria->codice);
        $this->assertSame('cassa', $m->fresh()->conto);
        // Il saldo non cambia, ma spesa e donazione sono nel registro
        $this->assertEquals($prima, $this->saldi());
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'RINUNCIA_RIMBORSO_REGISTRATA')->where('livello_rischio', 'HIGH')->exists());
        // L'entrata della rinuncia non si elimina da sola
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.destroy', $entrata), ['conferma' => 'si'])->assertSessionHas('error');

        $this->actingAs($this->tesoriere)->post(route('prima-nota.anticipi.annulla-pagamento', $n), ['motivazione' => 'Il socio vuole invece il rimborso'])->assertSessionHasNoErrors();
        $this->assertSame(0, PrimaNota::where('tipo', 'entrata')->count());
        $this->assertTrue($m->fresh()->isAnticipoAperto());
        $this->assertEquals($prima, $this->saldi());
    }

    // ----------------------------------------------------------------
    // A2: chiusura dell'anno con anticipi aperti
    // ----------------------------------------------------------------

    public function test_chiusura_non_bloccata_dagli_anticipi(): void
    {
        Carbon::setTestNow('2028-02-10 10:00:00');
        $this->spesa($this->tascio, '20,00', '2027-12-20', 'Colla di dicembre');
        $anni = app(AnniContabili::class);
        $controllo = collect($anni->controlliChiusura(2027))->firstWhere(0, 'Anticipi da rimborsare');
        $this->assertFalse($controllo[1]);
        $this->assertTrue($controllo[3]);
        $this->assertStringContainsString('non bloccano la chiusura', $controllo[2]);
        $this->assertTrue($anni->chiudibile(2027));
    }

    // ----------------------------------------------------------------
    // AN-1b: «Pagato da me» dal telefono
    // ----------------------------------------------------------------

    public function test_pagato_da_me_dagli_scontrini(): void
    {
        config(['services.ocr_space.key' => null]);
        $leggi = function (User $u): string {
            $r = $this->actingAs($u)->post(route('scontrini.leggi'), ['foto' => UploadedFile::fake()->image('IMG_0001.jpg', 1200, 1600), 'nota' => 'Colla e nastro per le frecce']);

            return substr($r->headers->get('Location'), -32);
        };
        $dati = ['importo' => '12,40', 'data' => '2027-02-22', 'conto' => 'anticipo', 'nota' => 'Colla e nastro per le frecce',
            'fornitore' => 'nuovo', 'nuovo_nome' => 'Ferramenta Bianchi'];

        $token = $leggi($this->consigliere);
        $this->actingAs($this->consigliere)->get(route('scontrini.conferma', $token))->assertOk()
            ->assertSee('Pagato da me')->assertSee('Di tasca mia: me li rimborsa la Compagnia');
        $this->actingAs($this->consigliere)->post(route('scontrini.store', $token), $dati)->assertRedirect()->assertSessionHasNoErrors();
        $s = PrimaNotaScontrino::sole();
        $this->assertSame('anticipo', $s->conto);
        $this->assertSame($this->tascio->id, $s->anticipante_socio_id);
        $m = $s->movimenti()->sole();
        $this->assertTrue($m->isAnticipoAperto());
        $this->assertNull($m->conto);
        $this->assertSame($this->tascio->id, $m->anticipante_socio_id);
        $this->assertSame('Pagato da Tascio Luigi', $s->contoLabel());

        // Un utente senza socio non ha «Pagato da me»
        $senza = User::factory()->conRuoli('consigliere')->create(['attivo' => true]);
        $token = $leggi($senza);
        $this->actingAs($senza)->get(route('scontrini.conferma', $token))->assertOk()->assertDontSee('Pagato da me');
        $this->actingAs($senza)->post(route('scontrini.store', $token), $dati)->assertSessionHasErrors('conto');

        // Classificato con la divisione resta anticipato, poi va in una nota e si blocca
        $this->actingAs($this->tesoriere)->post(route('scontrini.dividi', $s), [
            'righe' => [['descrizione' => 'Colla', 'importo' => '12,40', 'categoria_id' => $this->cat('Acquisto materiali')]],
        ])->assertSessionHasNoErrors();
        $m = $s->movimenti()->sole();
        $this->assertTrue($m->isAnticipoAperto());
        $this->assertSame($this->tascio->id, $m->anticipante_socio_id);
        $n = $this->nota($this->tascio, [$m], ['data_nota' => '2027-02-23']);
        $this->assertSame('scontrino SC2027-001', $n->dati['righe'][0]['documento']);
        $this->actingAs($this->tesoriere)->delete(route('scontrini.destroy', $s), ['conferma' => 'si'])->assertSessionHasErrorsIn('elimina', 'conferma');
        $this->actingAs($this->tesoriere)->put(route('scontrini.update', $s), [
            'importo' => '10,00', 'data' => '2027-02-22', 'conto' => 'cassa', 'soggetto_id' => $s->soggetto_id,
        ])->assertSessionHasErrorsIn('dati', 'conto');
        $this->assertSame('12.40', (string) $m->fresh()->importo);
        $this->actingAs($this->tesoriere)->get(route('scontrini.show', $s))->assertOk()->assertSee('Scontrino nella nota di rimborso RIMB-2027-001.');
    }
}
