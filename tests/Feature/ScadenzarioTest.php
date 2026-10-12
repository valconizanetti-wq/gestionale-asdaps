<?php

namespace Tests\Feature;

use App\Mail\PromemoriaEmail;
use App\Models\AnnoContabile;
use App\Models\Associazione;
use App\Models\AuditLog;
use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\Promemoria;
use App\Models\PromemoriaInvio;
use App\Models\Rinnovo;
use App\Models\Scadenza;
use App\Models\ScadenzaRinnovo;
use App\Models\Socio;
use App\Models\Tesseramento;
use App\Models\TesseraTipo;
use App\Models\User;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\PrimaNotaCategoriaSeeder;
use Database\Seeders\ProfiloEnteSeeder;
use Database\Seeders\PromemoriaSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\ScadenzarioSeeder;
use Database\Seeders\SezioneSeeder;
use Database\Seeders\TesseraTipoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Scadenze dell'associazione v0.38.0 (scadenzario.md; tavole SC-1…SC-3,
 * PR-3): Scadenzario con «Rinnovata», riquadri Affiliazione e Polizza RC,
 * promemoria «N giorni prima».
 */
class ScadenzarioTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private User $tesoriere;

    private User $segretario;

    private User $revisore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2027-02-15 10:00:00');
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class,
            PrimaNotaCategoriaSeeder::class, ProfiloEnteSeeder::class, TesseraTipoSeeder::class, ScadenzarioSeeder::class, PromemoriaSeeder::class]);
        Mail::fake();
        Storage::fake('local');
        // Nomi fissi (non Faker): niente coincidenze nelle assertDontSee (collaudo K)
        $this->presidente = User::factory()->create(['name' => 'Andrea Presidente', 'email' => 'presidente@example.it', 'ruolo' => 'presidente', 'attivo' => true]);
        $this->tesoriere = User::factory()->create(['name' => 'Paolo Tesoriere', 'email' => 'tesoriere@example.it', 'ruolo' => 'tesoriere', 'attivo' => true]);
        $this->segretario = User::factory()->create(['name' => 'Luigi Segretario', 'email' => 'segretario@example.it', 'ruolo' => 'segretario', 'attivo' => true]);
        $this->revisore = User::factory()->create(['name' => 'Rita Revisore', 'email' => 'revisore@example.it', 'ruolo' => 'revisore', 'attivo' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function scadenza(string $chiave): Scadenza
    {
        return Scadenza::where('chiave', $chiave)->firstOrFail();
    }

    private function rinnova(User $u, Scadenza $s, array $extra = [])
    {
        return $this->actingAs($u)->from(route('scadenzario.index'))->post(route('scadenzario.rinnova', $s), $extra + [
            'per_scadenza' => $s->data_scadenza->toDateString(), 'fatto_il' => '2027-02-10', 'importo' => '350,00', 'conto' => 'banca',
            'movimento' => '1', 'prossima_scadenza' => $s->prossimaData()->toDateString(),
        ]);
    }

    // ----------------------------------------------------------------

    public function test_scadenze_pronte_e_categorie(): void
    {
        $this->assertSame('2027-12-31', $this->scadenza(Scadenza::AFFILIAZIONE)->data_scadenza->toDateString());
        $this->assertSame('2027-07-31', $this->scadenza(Scadenza::POLIZZA)->data_scadenza->toDateString());
        $this->assertSame('2027-04-30', $this->scadenza(Scadenza::RENDICONTO)->data_scadenza->toDateString());
        $this->assertSame('Affiliazione EPS', $this->scadenza(Scadenza::AFFILIAZIONE)->categoriaPrimaNota->nome);
        $this->assertSame('Polizze Assicurative', $this->scadenza(Scadenza::POLIZZA)->categoriaPrimaNota->nome);
        $this->assertNull($this->scadenza(Scadenza::RENDICONTO)->prima_nota_categoria_id);
        $this->assertSame('2027-12-31', Associazione::istanza()->rete_affiliazione_scadenza->toDateString());
        $this->assertSame('Unipol, tramite CSEN', Associazione::istanza()->polizza_compagnia);

        // Rilanciare i seeder non duplica né cambia nulla
        $this->seed([ScadenzarioSeeder::class, PrimaNotaCategoriaSeeder::class]);
        $this->assertSame(3, Scadenza::count());
        $this->assertSame(1, PrimaNotaCategoria::where('nome', 'Polizze Assicurative')->count());
    }

    public function test_permessi(): void
    {
        $this->actingAs($this->tesoriere)->get(route('scadenzario.index'))->assertOk()->assertSee('Nuova scadenza')->assertSee('Rinnovata');
        $this->actingAs($this->segretario)->get(route('scadenzario.index'))->assertOk()->assertSee('Nuova scadenza');
        $this->actingAs($this->revisore)->get(route('scadenzario.index'))->assertOk()->assertDontSee('Nuova scadenza')->assertSee('Sola lettura');
        $this->actingAs($this->revisore)->get(route('scadenzario.create'))->assertForbidden();
        $this->rinnova($this->revisore, $this->scadenza(Scadenza::POLIZZA))->assertForbidden();
        $this->actingAs($this->revisore)->get(route('scadenzario.edit', $this->scadenza(Scadenza::POLIZZA)))->assertOk()->assertSee('Sola lettura');
        // Voce di menu per il Tesoriere
        $this->actingAs($this->tesoriere)->get(route('dashboard'))->assertSee(route('scadenzario.index'));
    }

    public function test_rinnovata_con_uscita_e_giustificativo(): void
    {
        $polizza = $this->scadenza(Scadenza::POLIZZA);
        $this->rinnova($this->tesoriere, $polizza, [
            'documenti' => [UploadedFile::fake()->create('quietanza.pdf', 50, 'application/pdf')],
        ])->assertRedirect(route('scadenzario.index'))->assertSessionHas('success');

        $polizza->refresh();
        $this->assertSame('2028-07-31', $polizza->data_scadenza->toDateString());
        $r = ScadenzaRinnovo::firstOrFail();
        $this->assertSame('2027-07-31', $r->data_scadenza->toDateString());
        $m = PrimaNota::findOrFail($r->prima_nota_id);
        $this->assertSame('uscita', $m->tipo);
        $this->assertSame('banca', $m->conto);
        $this->assertEquals(350.00, (float) $m->importo);
        $this->assertSame('Polizze Assicurative', $m->categoria->nome);
        $this->assertSame('Unipol, tramite CSEN', $m->soggetto);
        $this->assertSame(1, $m->documenti()->count());
        Storage::disk('local')->assertExists($m->documenti()->first()->path);
        $this->assertTrue(AuditLog::where('modello', 'ScadenzaRinnovo')->exists());

        // Doppio invio con la stessa scadenza: rifiutato, la data non si sposta di due anni
        $this->actingAs($this->tesoriere)->from(route('scadenzario.index'))->post(route('scadenzario.rinnova', $polizza), [
            'per_scadenza' => '2027-07-31', 'fatto_il' => '2027-02-10', 'importo' => '350,00', 'conto' => 'banca',
            'movimento' => '1', 'prossima_scadenza' => '2028-07-31',
        ])->assertSessionHasErrorsIn('rinnovo', 'rinnovo')->assertSessionHas('rinnovo_aperto', $polizza->id);
        $this->assertSame('2028-07-31', $polizza->fresh()->data_scadenza->toDateString());
        $this->assertSame(1, PrimaNota::count());

        // Riquadro e pagina: ultimo pagamento e proposta del prossimo importo
        $this->actingAs($this->tesoriere)->get(route('scadenzario.index'))->assertSee('Pagata il 10/02/2027 · 350,00 €')->assertSee('uscita n. ' . $m->id);
        $this->actingAs($this->tesoriere)->get(route('impostazioni.index'))->assertOk()->assertSee('31/07/2028')->assertSee('10/02/2027 · 350,00 €');
    }

    public function test_errori_della_finestra(): void
    {
        $polizza = $this->scadenza(Scadenza::POLIZZA);
        // Importo mancante con l'uscita, prossima scadenza non dopo quella attuale
        $this->rinnova($this->tesoriere, $polizza, ['importo' => ''])->assertSessionHasErrorsIn('rinnovo', ['importo']);
        $this->rinnova($this->tesoriere, $polizza, ['prossima_scadenza' => '2027-07-31'])->assertSessionHasErrorsIn('rinnovo', ['rinnovo']);
        $this->rinnova($this->tesoriere, $polizza, ['fatto_il' => '2027-03-01'])->assertSessionHasErrorsIn('rinnovo', ['fatto_il']);
        // Pagamento prima dell'avvio della contabilità (2027): uscita rifiutata, senza uscita va
        $this->rinnova($this->tesoriere, $polizza, ['fatto_il' => '2026-12-20'])->assertSessionHasErrorsIn('rinnovo', ['rinnovo']);
        $this->rinnova($this->tesoriere, $polizza, ['fatto_il' => '2026-12-20', 'movimento' => '0'])->assertSessionHas('success');
        $this->assertSame(0, PrimaNota::count());
        $this->assertEquals(350.0, (float) ScadenzaRinnovo::firstOrFail()->importo);   // resta per la proposta
        $this->assertSame('2028-07-31', $polizza->fresh()->data_scadenza->toDateString());

        // Anno chiuso
        AnnoContabile::updateOrCreate(['anno' => 2027], ['stato' => 'chiuso']);
        $aff = $this->scadenza(Scadenza::AFFILIAZIONE);
        $this->rinnova($this->tesoriere, $aff)->assertSessionHasErrorsIn('rinnovo', ['rinnovo']);
        $this->assertSame('2027-12-31', $aff->fresh()->data_scadenza->toDateString());
    }

    public function test_affiliazione_aggiorna_la_scheda_dell_ente(): void
    {
        $aff = $this->scadenza(Scadenza::AFFILIAZIONE);
        $this->rinnova($this->presidente, $aff, ['importo' => '120', 'prossima_scadenza' => '2028-12-31'])->assertSessionHas('success');
        $this->assertSame('2028-12-31', Associazione::istanza()->rete_affiliazione_scadenza->toDateString());
        $this->assertSame('CSEN', PrimaNota::firstOrFail()->soggetto);
        $this->assertSame('Affiliazione EPS', PrimaNota::firstOrFail()->categoria->nome);
    }

    public function test_segretario_senza_prima_nota(): void
    {
        $polizza = $this->scadenza(Scadenza::POLIZZA);
        $this->rinnova($this->segretario, $polizza)->assertForbidden();   // l'uscita no
        $this->rinnova($this->segretario, $polizza, ['movimento' => '0'])->assertSessionHas('success');
        $this->assertSame(0, PrimaNota::count());
        $this->assertSame('2028-07-31', $polizza->fresh()->data_scadenza->toDateString());
        $this->actingAs($this->segretario)->get(route('scadenzario.index'))->assertSee('senza uscita in Prima Nota');
    }

    public function test_fatta_e_una_tantum(): void
    {
        $rend = $this->scadenza(Scadenza::RENDICONTO);
        $this->actingAs($this->segretario)->get(route('scadenzario.index'))->assertSee('Fatta');
        $this->rinnova($this->segretario, $rend, ['movimento' => '1', 'importo' => '', 'conto' => ''])->assertSessionHas('success');
        $this->assertSame('2028-04-30', $rend->fresh()->data_scadenza->toDateString());
        $this->assertNull(ScadenzaRinnovo::firstOrFail()->prima_nota_id);   // adempimento: niente uscita anche se chiesta

        $this->actingAs($this->segretario)->post(route('scadenzario.store'), [
            'titolo' => 'Rinnovo del certificato del defibrillatore', 'tipo' => 'una_tantum', 'categoria' => 'altro',
            'data_scadenza' => '2027-03-10', 'giorni_preavviso' => 30,
        ])->assertRedirect(route('scadenzario.index'));
        $dae = Scadenza::where('titolo', 'like', '%defibrillatore%')->firstOrFail();
        $this->assertSame('in_scadenza', $dae->stato()['stato']);
        $this->assertNull($dae->giorno_ricorrenza);
        $this->rinnova($this->segretario, $dae, ['movimento' => '0', 'prossima_scadenza' => ''])->assertSessionHas('success');
        $this->assertTrue($dae->fresh()->completato);
        $this->assertSame('fatta', $dae->fresh()->stato()['stato']);
        // Una tantum fatta: niente secondo «Fatta»
        $this->rinnova($this->segretario, $dae, ['movimento' => '0', 'per_scadenza' => '2027-03-10'])->assertSessionHasErrorsIn('rinnovo', ['rinnovo']);
        // Con rinnovi non si elimina; una nuova senza rinnovi sì; quelle del gestionale mai
        $this->actingAs($this->segretario)->delete(route('scadenzario.destroy', $dae))->assertSessionHas('error');
        $this->actingAs($this->segretario)->delete(route('scadenzario.destroy', $rend))->assertSessionHas('error');
        $altra = Scadenza::create(['titolo' => 'Prova', 'tipo' => 'una_tantum', 'categoria' => 'altro', 'data_scadenza' => '2027-05-01', 'giorni_preavviso' => 10]);
        $this->actingAs($this->segretario)->delete(route('scadenzario.destroy', $altra))->assertSessionHas('success');
        $this->assertModelMissing($altra);
    }

    public function test_annulla_ultimo_rinnovo(): void
    {
        $polizza = $this->scadenza(Scadenza::POLIZZA);
        $this->rinnova($this->tesoriere, $polizza, ['documenti' => [UploadedFile::fake()->create('q.pdf', 20, 'application/pdf')]]);
        $m = PrimaNota::firstOrFail();
        $file = $m->documenti()->first()->path;
        // Dicitura generica (collaudo M8, v0.38.1): vale anche per una una tantum
        $this->actingAs($this->tesoriere)->get(route('scadenzario.edit', $polizza))
            ->assertSee("Annulla l'ultima registrazione", false)->assertDontSee('ultimo rinnovo');

        $this->actingAs($this->tesoriere)->post(route('scadenzario.annulla', $polizza), ['motivo' => 'corto'])->assertSessionHasErrors('motivo');
        // Il segretario non annulla un rinnovo con uscita
        $this->actingAs($this->segretario)->post(route('scadenzario.annulla', $polizza), ['motivo' => 'Registrata sulla scadenza sbagliata'])->assertSessionHas('error');
        $this->actingAs($this->tesoriere)->post(route('scadenzario.annulla', $polizza), ['motivo' => 'Registrata sulla scadenza sbagliata'])
            ->assertRedirect(route('scadenzario.edit', $polizza))->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Ultima registrazione annullata'));

        $this->assertSame('2027-07-31', $polizza->fresh()->data_scadenza->toDateString());
        $this->assertSame(0, ScadenzaRinnovo::count());
        $this->assertModelMissing($m);
        Storage::disk('local')->assertMissing($file);
        $riga = AuditLog::where('evento', 'SCADENZA_RINNOVO_ANNULLATO')->firstOrFail();
        $this->assertStringNotContainsString('sbagliata', $riga->dettaglio);   // la motivazione fuori dall'hash
        $this->assertStringContainsString('sbagliata', (string) $riga->getRawOriginal('new_values'));
    }

    public function test_avviso_affiliazione_scaduta(): void
    {
        $this->actingAs($this->tesoriere)->get(route('dashboard'))->assertDontSee('Affiliazione CSEN scaduta');
        Carbon::setTestNow('2028-01-03 10:00:00');
        $this->actingAs($this->tesoriere)->get(route('dashboard'))->assertSee('Affiliazione CSEN scaduta il 31/12/2027');
        $this->actingAs($this->tesoriere)->get(route('scadenzario.index'))->assertSee('Affiliazione CSEN scaduta il 31/12/2027');
        $this->actingAs($this->tesoriere)->get(route('impostazioni.index'))->assertSee('Affiliazione CSEN scaduta il 31/12/2027')->assertSee('Scaduta');
        $this->actingAs($this->tesoriere)->get(route('tesseramento.index'))->assertSee('Affiliazione CSEN scaduta il 31/12/2027');
        // In scadenza (entro 30 giorni): avviso giallo
        Carbon::setTestNow('2027-12-10 10:00:00');
        $this->actingAs($this->presidente)->get(route('dashboard'))->assertSee('Affiliazione CSEN: tra 21 giorni');
    }

    public function test_riquadri_in_impostazioni(): void
    {
        // Tesoriere e segretario vedono, non modificano
        $this->actingAs($this->tesoriere)->get(route('impostazioni.index'))->assertOk()
            ->assertSee('Affiliazione all\'EPS')->assertSee('Polizza RC')->assertSee('Sola lettura')->assertDontSee('modifica-affiliazione');
        $this->actingAs($this->segretario)->get(route('impostazioni.index'))->assertOk();
        $this->actingAs($this->tesoriere)->put(route('impostazioni.ente.affiliazione'), ['rete_affiliazione_codice' => 'X'])->assertForbidden();
        $this->actingAs($this->tesoriere)->put(route('impostazioni.ente.dati'), [])->assertForbidden();
        $this->actingAs($this->revisore)->get(route('impostazioni.index'))->assertForbidden();

        $this->actingAs($this->presidente)->get(route('impostazioni.index'))->assertSee('modifica-affiliazione');
        $this->actingAs($this->presidente)->put(route('impostazioni.ente.affiliazione'), [
            'rete_affiliazione_codice' => 'TR-0421', 'rete_affiliazione_referente' => 'CSEN Terni · Mario Neri', 'rete_affiliazione_contatto' => 'terni@csen.example',
        ])->assertRedirect(route('impostazioni.index'));
        $this->actingAs($this->presidente)->put(route('impostazioni.ente.polizza'), [
            'polizza_compagnia' => 'Unipol, tramite CSEN', 'polizza_numero' => '1/2345/67', 'polizza_copertura' => 'RC verso terzi',
        ])->assertRedirect(route('impostazioni.index'));
        $a = Associazione::istanza();
        $this->assertSame('TR-0421', $a->rete_affiliazione_codice);
        $this->assertSame('1/2345/67', $a->polizza_numero);
        $this->assertSame('2027-12-31', $a->rete_affiliazione_scadenza->toDateString());   // la scadenza non si tocca da qui
        $this->actingAs($this->presidente)->get(route('impostazioni.index'))->assertSee('TR-0421')->assertSee('terni@csen.example')->assertSee('1/2345/67');
        $this->assertTrue(AuditLog::where('evento', 'PROFILO_ENTE_MODIFICATO')->exists());
    }

    public function test_promemoria_scadenze_n_giorni_prima(): void
    {
        $arrivo = Promemoria::where('funzione', 'scadenze_associazione')->where('giorni', -30)->firstOrFail();
        $this->actingAs($this->tesoriere)->get(route('promemoria.index'))->assertSee('Scadenze dell&#039;associazione', false)
            ->assertSee('30 giorni prima, alle 9:00')->assertSee('Presidente e Tesoriere')->assertSee('Approvazione del rendiconto');
        // Prossimo invio: 30 giorni prima del rendiconto (30/04)
        $this->assertSame('2027-03-31 09:00', \App\Promemoria\Invii::prossimo($arrivo)->format('Y-m-d H:i'));

        // Il giorno prima: niente
        Carbon::setTestNow('2027-06-30 09:30:00');
        $this->artisan('promemoria:invia')->assertSuccessful();
        Mail::assertNotSent(PromemoriaEmail::class, fn ($m) => str_contains($m->render(), 'Polizza RC'));

        // 30 giorni prima della polizza (31/07): Presidente e Tesoriere
        Mail::fake();
        Carbon::setTestNow('2027-07-01 09:05:00');
        $this->artisan('promemoria:invia')->assertSuccessful();
        Mail::assertSent(PromemoriaEmail::class, 2);
        Mail::assertSent(PromemoriaEmail::class, fn ($m) => $m->hasTo('presidente@example.it'));
        Mail::assertSent(PromemoriaEmail::class, fn ($m) => $m->hasTo('tesoriere@example.it') && str_contains($m->render(), 'scade il 31/07/2027'));
        Mail::assertNotSent(PromemoriaEmail::class, fn ($m) => $m->hasTo('segretario@example.it'));
        $this->assertSame(2, PromemoriaInvio::where('promemoria_id', $arrivo->id)->where('periodo', 's' . $this->scadenza(Scadenza::POLIZZA)->id . ':2027-07-31')->count());

        // Secondo giro: nessun doppione
        Mail::fake();
        Carbon::setTestNow('2027-07-02 09:05:00');
        $this->artisan('promemoria:invia')->assertSuccessful();
        Mail::assertNothingSent();

        // Server spento il 24/07: «Ultimi giorni» recuperato il 26, ma non dopo la scadenza
        Carbon::setTestNow('2027-07-26 09:05:00');
        $this->artisan('promemoria:invia')->assertSuccessful();
        Mail::assertSent(PromemoriaEmail::class, 2);
        Mail::assertSent(PromemoriaEmail::class, fn ($m) => str_starts_with($m->envelope()->subject, 'Ultimi giorni: Polizza RC'));
        $ultimi = Promemoria::where('funzione', 'scadenze_associazione')->where('giorni', -7)->firstOrFail();
        $this->assertSame(2, PromemoriaInvio::where('promemoria_id', $ultimi->id)->count());

        // Rinnovata: si riparte dalla scadenza dell'anno dopo
        Mail::fake();
        $this->rinnova($this->tesoriere, $this->scadenza(Scadenza::POLIZZA), ['fatto_il' => '2027-07-26'])->assertSessionHas('success');
        Carbon::setTestNow('2028-07-01 09:05:00');
        $this->artisan('promemoria:invia')->assertSuccessful();
        Mail::assertSent(PromemoriaEmail::class, fn ($m) => str_contains($m->render(), 'scade il 31/07/2028'));
    }

    public function test_promemoria_prima_non_parte_dopo_la_data(): void
    {
        // Polizza scaduta e non rinnovata: il «7 giorni prima» non parte il 2 agosto
        Carbon::setTestNow('2027-08-02 09:05:00');
        $this->artisan('promemoria:invia')->assertSuccessful();
        Mail::assertNotSent(PromemoriaEmail::class, fn ($m) => str_contains($m->render(), '31/07/2027'));

        // «3 giorni dopo», creato dalla pagina: parte il 3 agosto
        $this->actingAs($this->tesoriere)->post(route('promemoria.store'), [
            'funzione' => 'scadenze_associazione', 'nome' => 'Scaduta e non rinnovata', 'attivo' => '1', 'quando' => 'relativa',
            'giorni_quanti' => '3', 'verso' => 'dopo', 'ora' => '09:00', 'parametri' => ['destinatari' => 'tesoriere'],
            'oggetto' => '{scadenza} scaduta il {data}', 'testo' => 'Ciao {saluto}, {scadenza} è {giorni}.',
        ])->assertSessionHasNoErrors();
        $p = Promemoria::where('nome', 'Scaduta e non rinnovata')->firstOrFail();
        $this->assertSame(3, $p->giorni);
        $this->assertNull($p->giorno);
        Carbon::setTestNow('2027-08-03 09:20:00');
        $this->artisan('promemoria:invia')->assertSuccessful();
        Mail::assertSent(PromemoriaEmail::class, fn ($m) => $m->hasTo('tesoriere@example.it') && str_contains($m->render(), 'scaduta da 3 giorni'));
        Mail::assertNotSent(PromemoriaEmail::class, fn ($m) => $m->hasTo('presidente@example.it') && str_contains($m->render(), 'scaduta da 3 giorni'));

        // La funzione ammette solo «N giorni prima o dopo»; il revisore non modifica
        $this->actingAs($this->tesoriere)->post(route('promemoria.store'), [
            'funzione' => 'scadenze_associazione', 'nome' => 'X', 'quando' => 'data_fissa', 'giorno' => 1, 'mese' => 1, 'ora' => '09:00',
            'parametri' => ['destinatari' => 'tesoriere'], 'oggetto' => 'x', 'testo' => 'x',
        ])->assertSessionHasErrors('quando');
        $this->actingAs($this->revisore)->get(route('promemoria.edit', $p))->assertOk()->assertSee('Sola lettura');
        $this->actingAs($this->revisore)->put(route('promemoria.update', $p), [])->assertForbidden();
    }

    public function test_promemoria_tessere_in_scadenza(): void
    {
        $silver = TesseraTipo::where('nome', 'Silver Base')->firstOrFail();
        $socio = function (string $cognome, string $cf) {
            $s = new Socio();
            $s->forceFill(['nome' => 'Luca', 'cognome' => $cognome, 'codice_fiscale' => $cf, 'tipo' => 'ordinario', 'stato' => 'attivo',
                'data_nascita' => '1980-01-01', 'luogo_nascita' => 'Orvieto (TR)', 'email' => strtolower($cognome) . '@example.it',
                'data_prima_iscrizione' => '2025-03-01'])->save();

            return $s;
        };
        $tessera = function (Socio $s, string $numero, string $dal) use ($silver) {
            Tesseramento::create(['socio_id' => $s->id, 'numero_tessera_csen' => $numero, 'progressivo' => (int) substr($numero, 3),
                'tipo_id' => $silver->id, 'ente_affiliante' => 'CSEN', 'stagione_sportiva' => (int) substr($dal, 0, 4),
                'data_rilascio' => $dal, 'scadenza' => Tesseramento::scadenzaDa($dal)->toDateString()]);
        };
        $a = $socio('Tascio', 'TSCLCU80A01E700X');
        $b = $socio('Bianchi', 'BNCLCU80A01E701X');
        $c = $socio('Rossetti', 'RSSLCU80A01E702X');
        $tessera($a, '26B0000101', '2026-03-02');   // scade 01/03/2027
        $tessera($b, '26B0000102', '2026-03-02');   // scade 01/03/2027
        $tessera($c, '26B0000103', '2026-03-02');
        $tessera($c, '27B0000104', '2027-02-10');   // già rinnovata: l'ultima scade nel 2028
        $r = new Rinnovo();
        $r->forceFill(['socio_id' => $a->id, 'anno' => 2027, 'importo' => 70, 'categoria' => 'ordinario', 'data_pagamento' => '2027-01-15',
            'metodo_pagamento' => 'bonifico', 'numero_ricevuta' => '5/2027', 'anno_ricevuta' => 2027, 'progressivo' => 5])->save();

        $p = Promemoria::where('funzione', 'tessere_in_scadenza')->firstOrFail();
        $this->assertSame('2027-02-14 09:00', \App\Promemoria\Invii::prossimoDettaglio($p, Carbon::parse('2027-02-01 08:00'))['quando']->format('Y-m-d H:i'));
        $this->assertSame('2 tessere', \App\Promemoria\Invii::prossimoDettaglio($p, Carbon::parse('2027-02-01 08:00'))['cosa']);

        // 15 giorni prima del 01/03: una sola email al Tesoriere, con le due tessere e la quota
        Carbon::setTestNow('2027-02-14 09:10:00');
        $this->artisan('promemoria:invia')->assertSuccessful();
        Mail::assertSent(PromemoriaEmail::class, 1);
        Mail::assertSent(PromemoriaEmail::class, function ($m) {
            $html = $m->render();

            return $m->hasTo('tesoriere@example.it') && str_contains($html, 'Bianchi Luca, tessera 26B0000102')
                && str_contains($html, 'Tascio Luca, tessera 26B0000101 · Quota 2027 versata')
                && str_contains($html, 'Bianchi Luca, tessera 26B0000102 · Quota 2027 non ancora versata')
                && ! str_contains($html, 'Rossetti');
        });
        Mail::assertSent(PromemoriaEmail::class, fn ($m) => $m->envelope()->subject === 'Tessere CSEN in scadenza il 01/03/2027: 2 tessere');
        // Secondo giro: niente doppioni
        Mail::fake();
        Carbon::setTestNow('2027-02-15 09:10:00');
        $this->artisan('promemoria:invia')->assertSuccessful();
        Mail::assertNothingSent();
        // Pagina di modifica: destinatari e anteprima
        $this->actingAs($this->tesoriere)->get(route('promemoria.edit', $p))->assertOk()->assertSee('tessere al 01/03/2027');
    }

    /** Collaudo M (v0.38.1): stato dei riquadri, prova senza destinatari, comando che spiega. */
    public function test_correzioni_collaudo_m(): void
    {
        // M9: nel riquadro solo lo stato, la data è nella riga «Scadenza»
        $this->actingAs($this->tesoriere)->get(route('impostazioni.index'))
            ->assertSee('Valida')->assertDontSee('Valida · tra');
        Carbon::setTestNow('2027-12-10 10:00:00');
        $this->actingAs($this->tesoriere)->get(route('impostazioni.index'))->assertSee('In scadenza')->assertDontSee('Scade tra');
        Carbon::setTestNow('2027-02-15 10:00:00');

        // M11: nessuna tessera → prova e anteprima con dati d'esempio, mai i segnaposti tra parentesi quadre
        $p = Promemoria::where('funzione', 'tessere_in_scadenza')->firstOrFail();
        $this->actingAs($this->tesoriere)->get(route('promemoria.edit', $p))->assertOk()
            ->assertSee('Anteprima con dati d&#039;esempio', false)->assertSee('Rossi Mario, tessera')->assertDontSee('[elenco]');
        $this->actingAs($this->tesoriere)->post(route('promemoria.prova', $p))->assertSessionHas('success', fn ($m) => str_contains($m, "dati d'esempio"));
        Mail::assertSent(PromemoriaEmail::class, function ($m) {
            $html = $m->render();

            return $m->hasTo('tesoriere@example.it') && $m->envelope()->subject === "[Prova, dati d'esempio] Tessere CSEN in scadenza il 02/03/2027: 2 tessere"
                && str_contains($html, 'Ciao Paolo') && str_contains($html, 'Verdi Anna') && ! str_contains($html, '[');
        });
        // Scadenze dell'associazione: con le scadenze pronte la prova usa i dati veri
        $s = Promemoria::where('funzione', 'scadenze_associazione')->orderBy('id')->firstOrFail();
        $this->actingAs($this->tesoriere)->post(route('promemoria.prova', $s))->assertSessionHas('success', fn ($m) => str_contains($m, 'primo destinatario'));

        // Comando: una riga anche senza invii; --prova spiega
        $this->artisan('promemoria:invia')->expectsOutputToContain('Nessun promemoria da inviare adesso')->assertSuccessful();
        $this->artisan('promemoria:invia --prova')
            ->expectsOutputToContain('«Tessere da rinnovare»: 0 da avvisare adesso. Nessun destinatario per nessuna data')
            ->expectsOutputToContain('«Invito a rinnovare la quota»: non è il momento; prossimo invio il 15/12/2027 09:00')
            ->assertSuccessful();
        // Nessun tesoriere attivo: lo dice la pagina e il comando
        $this->tesoriere->update(['attivo' => false]);
        $t = Promemoria::where('funzione', 'tessere_in_scadenza')->firstOrFail();
        $this->actingAs($this->presidente)->get(route('promemoria.edit', $t))->assertSee('Nessun utente attivo con il ruolo scelto.');
        $this->artisan('promemoria:invia --prova')->expectsOutputToContain('Nessun utente attivo con il ruolo scelto')->assertSuccessful();
    }
}
