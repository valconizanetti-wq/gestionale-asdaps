<?php

namespace Tests\Feature;

use App\Models\AnnoContabile;
use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\PrimaNotaDocumento;
use App\Models\Soggetto;
use App\Models\Tag;
use App\Models\User;
use App\Services\AnniContabili;
use App\Support\Importo;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\PrimaNotaCategoriaSeeder;
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
 * Prima Nota v0.35.0 (prima-nota.md): movimenti, giroconti, anni contabili,
 * categorie e tag. Gira su MySQL come gli altri test.
 */
class PrimaNotaTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private User $tesoriere;

    private User $revisore;

    private User $segretario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2027-03-15 10:00:00');
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class, PrimaNotaCategoriaSeeder::class]);
        Mail::fake();
        Storage::fake('local');
        $this->presidente = User::factory()->conRuoli('presidente')->create(['attivo' => true]);
        $this->tesoriere = User::factory()->conRuoli('tesoriere')->create(['attivo' => true]);
        $this->revisore = User::factory()->conRuoli('revisore')->create(['attivo' => true]);
        $this->segretario = User::factory()->conRuoli('segretario')->create(['attivo' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cat(string $nome, string $tipo = 'uscita'): PrimaNotaCategoria
    {
        return PrimaNotaCategoria::where('tipo', $tipo)->where('nome', $nome)->firstOrFail();
    }

    private function movimento(array $dati = []): PrimaNota
    {
        $m = new PrimaNota();
        $m->forceFill($dati + [
            'data' => '2027-02-10', 'tipo' => 'uscita', 'conto' => 'cassa', 'importo' => '10.00',
            'descrizione' => 'Prova', 'categoria_id' => $this->cat('Acquisto materiali')->id,
        ])->save();
        app(AnniContabili::class)->assicura((int) substr((string) $m->getAttributes()['data'], 0, 4));

        return $m;
    }

    private function verbale(string $data, string $titolo, string $tipo = 'assemblea'): \App\Models\Verbale
    {
        return \App\Models\Verbale::create(['tipo' => $tipo, 'titolo' => $titolo, 'data_seduta' => $data, 'corpo_html' => '<p>Verbale</p>']);
    }

    private function datiForm(array $extra = []): array
    {
        return $extra + [
            'tipo' => 'uscita', 'conto' => 'banca', 'data' => '2027-02-12', 'importo' => '412,00',
            'categoria_id' => $this->cat('Opere e progetti')->id, 'descrizione' => 'Travi e lamiera per la tettoia',
        ];
    }

    // ----------------------------------------------------------------

    public function test_importo_all_italiana(): void
    {
        $this->assertSame('1450.00', Importo::leggi('1.450'));
        $this->assertSame('1234.56', Importo::leggi('1.234,56'));
        $this->assertSame('12.50', Importo::leggi('12,50'));
        $this->assertSame('12.50', Importo::leggi('12.50'));
        $this->assertSame('23.00', Importo::leggi('23'));
        $this->assertSame('0.00', Importo::leggi('0'));
        $this->assertNull(Importo::leggi('1,450'));   // tre decimali: ambiguo
        $this->assertNull(Importo::leggi('12,5,0'));
        $this->assertNull(Importo::leggi('-5'));
        $this->assertNull(Importo::leggi('abc'));
        $this->assertSame('1.450,00', Importo::it(1450));
    }

    public function test_categorie_della_bas(): void
    {
        $this->assertSame(33, PrimaNotaCategoria::count());
        $this->assertSame('Quote associative', PrimaNotaCategoria::perCodice(PrimaNotaCategoria::QUOTE)->nome);
        $this->assertSame('sistema', PrimaNotaCategoria::perCodice(PrimaNotaCategoria::DA_CLASSIFICARE)->uso);
        // Rilanciare il seeder non duplica
        $this->seed(PrimaNotaCategoriaSeeder::class);
        $this->assertSame(33, PrimaNotaCategoria::count());
        // Nei movimenti: niente "solo bilancio", niente "di sistema", niente disattivate
        $uscite = PrimaNotaCategoria::perMovimenti('uscita')->pluck('nome');
        $this->assertNotContains('Disavanzo anno precedente', $uscite);
        $this->assertNotContains('Uscite da classificare', $uscite);
        $this->assertNotContains('Anticipo spese', $uscite);
        $this->assertContains('Taglio erba', $uscite);
    }

    public function test_permessi(): void
    {
        $this->actingAs($this->tesoriere)->get(route('prima-nota.index'))->assertOk()->assertSee('Nuovo movimento');
        $this->actingAs($this->presidente)->get(route('prima-nota.create'))->assertOk();
        $this->actingAs($this->revisore)->get(route('prima-nota.index'))->assertOk()->assertDontSee('Nuovo movimento')->assertSee('Sola lettura');
        $this->actingAs($this->revisore)->get(route('prima-nota.create'))->assertForbidden();
        $this->actingAs($this->revisore)->post(route('prima-nota.store'), $this->datiForm())->assertForbidden();
        $this->actingAs($this->revisore)->get(route('prima-nota.anni.index'))->assertOk();
        $this->actingAs($this->revisore)->get(route('prima-nota.categorie.index'))->assertOk();
        // Dalla v0.39.0 il Segretario (membro del CD) legge la Prima Nota e carica solo scontrini
        $this->actingAs($this->segretario)->get(route('prima-nota.index'))->assertOk();
        $this->actingAs($this->segretario)->get(route('prima-nota.create'))->assertForbidden();
        $this->assertSame(0, PrimaNota::count());
    }

    public function test_nuovo_movimento_con_fornitore_tag_e_giustificativo(): void
    {
        Soggetto::create(['nome' => 'Edilizia Rossi s.r.l.']);

        $r = $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm([
            'importo' => '1.450',
            'soggetto' => '  edilizia rossi S.R.L. ',
            'tag' => 'Tettoia',
            'tipo_documento' => 'fattura', 'numero_documento' => '45/2027', 'data_documento' => '2027-02-10',
            'documenti' => [UploadedFile::fake()->create('fattura.pdf', 300, 'application/pdf')],
        ]));

        $m = PrimaNota::firstOrFail();
        $r->assertRedirect(route('prima-nota.show', $m));
        $this->assertSame('1450.00', $m->importo);
        $this->assertSame('Edilizia Rossi s.r.l.', $m->soggetto);   // ritrovato, non duplicato
        $this->assertSame(1, Soggetto::count());
        $this->assertSame('Tettoia', $m->tag->nome);
        $this->assertSame($this->tesoriere->id, (int) $m->registrato_da);
        $this->assertTrue(AnnoContabile::where('anno', 2027)->where('stato', 'aperto')->exists());
        $d = $m->documenti()->firstOrFail();
        Storage::disk('local')->assertExists($d->path);
        // Nome codificato, non quello originale (collaudo J4)
        $this->assertSame('PN2027-' . sprintf('%05d', $m->id) . '-01.pdf', $d->nome);
        $this->assertSame('prima-nota/2027/' . $d->nome, $d->path);
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'PRIMANOTA_CREATED')->exists());

        // Stesso tag con un'altra grafia: nessun doppione
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm(['tag' => 'tettoia', 'importo' => '28,70']));
        $this->assertSame(1, Tag::count());

        $this->actingAs($this->revisore)->get(route('prima-nota.show', $m))->assertOk()->assertSee($d->nome)->assertDontSee('fattura.pdf')->assertSee('1.450,00');
        $this->actingAs($this->revisore)->get(route('prima-nota.documenti.show', [$m, $d]))->assertOk();
    }

    public function test_validazione_del_movimento(): void
    {
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm(['importo' => '1,450']))
            ->assertSessionHasErrors('importo');
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm(['categoria_id' => $this->cat('Quote associative', 'entrata')->id]))
            ->assertSessionHasErrors('categoria_id');
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm(['categoria_id' => $this->cat('Disavanzo anno precedente')->id]))
            ->assertSessionHasErrors('categoria_id');
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm(['categoria_id' => $this->cat('Uscite da classificare')->id]))
            ->assertSessionHasErrors('categoria_id');
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm(['categoria_id' => $this->cat('Anticipo spese')->id]))
            ->assertSessionHasErrors('categoria_id');
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm([
            'documenti' => [UploadedFile::fake()->create('virus.exe', 10)],
        ]))->assertSessionHasErrors('documenti');
        // Nessun movimento e nessun fornitore o tag "fantasma"
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm(['importo' => 'x', 'soggetto' => 'Nuovo', 'tag' => 'Nuovo']));
        $this->assertSame(0, PrimaNota::count());
        $this->assertSame(0, Soggetto::count());
        $this->assertSame(0, Tag::count());
    }

    public function test_saldi_giroconto_ed_elenco(): void
    {
        $anni = app(AnniContabili::class);
        $this->actingAs($this->tesoriere)->put(route('prima-nota.anni.saldi', 2027), [
            'saldo_apertura_cassa' => '380,00', 'saldo_apertura_banca' => '2.950,40', 'saldi_fonte' => 'Chiusura 2026 di gestionale-acv',
        ])->assertRedirect(route('prima-nota.anni.index'));

        $this->movimento(['tipo' => 'entrata', 'conto' => 'cassa', 'importo' => '30.00', 'categoria_id' => $this->cat('Quote associative', 'entrata')->id, 'descrizione' => 'Quota Bianchi']);
        $this->movimento(['conto' => 'cassa', 'importo' => '18.40', 'descrizione' => 'Colla e nastro']);
        // Anticipo non rimborsato: compare ma non conta nel saldo
        $this->movimento(['conto' => null, 'importo' => '64.50', 'sospeso' => true, 'anticipato_da' => 'Luigi Tascio', 'descrizione' => 'Benzina']);

        $this->actingAs($this->tesoriere)->post(route('prima-nota.giroconto.store'), [
            'data' => '2027-02-05', 'conto_da' => 'cassa', 'conto_a' => 'banca', 'importo' => '200',
        ])->assertRedirect();
        $this->assertSame(2, PrimaNota::whereNotNull('giroconto_id')->count());

        $f = $anni->finali(2027);
        $this->assertSame(round(380 + 30 - 18.40 - 200, 2), $f['cassa']);
        $this->assertSame(round(2950.40 + 200, 2), $f['banca']);

        $pagina = $this->actingAs($this->revisore)->get(route('prima-nota.index'))->assertOk()
            ->assertSee('191,60 €')->assertSee('3.150,40 €')       // saldi
            ->assertSee('Versamento contanti in banca')->assertSee('Da rimborsare');
        // Il giroconto compare una volta sola senza filtro di conto
        $this->assertSame(1, substr_count($pagina->getContent(), 'Versamento contanti in banca'));
        $this->actingAs($this->revisore)->get(route('prima-nota.index', ['conto' => 'banca']))->assertOk()->assertSee('Versamento contanti in banca')->assertDontSee('Colla e nastro');
        $this->actingAs($this->revisore)->get(route('prima-nota.index', ['tipo' => 'anticipi']))->assertOk()->assertSee('Benzina')->assertDontSee('Colla e nastro');
        $this->actingAs($this->revisore)->get(route('prima-nota.index', ['q' => '%']))->assertOk()->assertSee('Nessun movimento corrisponde');

        $csv = $this->actingAs($this->revisore)->get(route('prima-nota.export-csv'))->assertOk();
        $this->assertStringContainsString('Colla e nastro', $csv->streamedContent());
        $this->actingAs($this->revisore)->get(route('prima-nota.export-pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(2, DB::table('gl_audit_log')->where('evento', 'PRIMA_NOTA_ESPORTATA')->count());

        // Eliminare un giroconto toglie entrambe le righe
        $g = PrimaNota::whereNotNull('giroconto_id')->where('tipo', 'entrata')->firstOrFail();
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.destroy', $g), ['conferma' => 'si'])->assertRedirect();
        $this->assertSame(0, PrimaNota::whereNotNull('giroconto_id')->count());
    }

    public function test_anno_successivo_parte_dai_saldi_finali(): void
    {
        $anni = app(AnniContabili::class);
        AnnoContabile::create(['anno' => 2027, 'saldo_apertura_cassa' => 100, 'saldo_apertura_banca' => 1000]);
        $this->movimento(['importo' => '40.00']);
        $this->movimento(['data' => '2028-01-10', 'importo' => '5.00']);

        $this->assertEquals(['cassa' => 60.0, 'banca' => 1000.0, 'da_impostare' => false, 'da_anno_prima' => true], $anni->apertura(2028));
        $this->assertSame(55.0, $anni->finali(2028)['cassa']);
        // I saldi di apertura si scrivono solo per il primo anno
        $this->actingAs($this->tesoriere)->put(route('prima-nota.anni.saldi', 2028), ['saldo_apertura_cassa' => '1', 'saldo_apertura_banca' => '1'])->assertStatus(422);
        // Cambiare i saldi del primo anno con movimenti già registrati chiede una motivazione
        $this->actingAs($this->tesoriere)->put(route('prima-nota.anni.saldi', 2027), ['saldo_apertura_cassa' => '120', 'saldo_apertura_banca' => '1000'])
            ->assertSessionHasErrors('motivazione');
        $this->actingAs($this->tesoriere)->put(route('prima-nota.anni.saldi', 2027), ['saldo_apertura_cassa' => '120', 'saldo_apertura_banca' => '1000', 'motivazione' => 'Contati di nuovo i contanti in cassa al 31/12.'])
            ->assertRedirect();
        $this->assertSame(80.0, $anni->apertura(2028)['cassa']);
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'SALDI_APERTURA_MODIFICATI')->exists());
    }

    public function test_chiusura_correzioni_e_riapertura(): void
    {
        $anni = app(AnniContabili::class);
        AnnoContabile::create(['anno' => 2027, 'saldo_apertura_cassa' => 100, 'saldo_apertura_banca' => 0]);
        $m = $this->movimento(['importo' => '40.00']);
        $daClass = $this->movimento(['importo' => '5.00', 'categoria_id' => $this->cat('Uscite da classificare')->id]);

        // Nel 2027 l'anno non è finito
        $this->assertFalse($anni->chiudibile(2027));
        Carbon::setTestNow('2028-04-20 10:00:00');
        $this->assertFalse($anni->chiudibile(2027));   // un movimento da classificare
        $this->actingAs($this->tesoriere)->get(route('prima-nota.anni.index'))->assertOk()->assertSee('1 movimento in «Uscite da classificare»', false);
        $daClass->update(['categoria_id' => $this->cat('Altre spese')->id]);
        $this->assertTrue($anni->chiudibile(2027));

        // Il verbale si sceglie tra le Assemblee dell'anno in corso (collaudo J11)
        $this->actingAs($this->tesoriere)->get(route('prima-nota.anni.index'))->assertSee('Nessun verbale da scegliere.');
        $vecchio = $this->verbale('2027-12-01', 'Assemblea di dicembre');
        $cd = $this->verbale('2028-02-01', 'Consiglio direttivo', 'cd_delibera');
        $giusto = $this->verbale('2028-04-18', 'Assemblea ordinaria, verbale n. 2/2028');
        $this->actingAs($this->tesoriere)->get(route('prima-nota.anni.index'))
            ->assertSee('18/04/2028 — Assemblea ordinaria, verbale n. 2/2028 (bozza)')->assertDontSee('Assemblea di dicembre')->assertDontSee('Consiglio direttivo');
        foreach ([$vecchio, $cd] as $v) {
            $this->actingAs($this->tesoriere)->post(route('prima-nota.anni.chiudi', 2027), ['verbale_id' => $v->id])->assertSessionHasErrors('verbale_id');
        }
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anni.chiudi', 2027), ['verbale_id' => $giusto->id])
            ->assertRedirect(route('prima-nota.anni.index'));
        $riga = $anni->trova(2027);
        $this->assertTrue($riga->isChiuso());
        $this->assertSame($giusto->id, (int) $riga->approvazione_verbale_id);
        $this->assertSame('2028-04-18', $riga->approvazione_data->toDateString());
        $this->assertSame('Assemblea ordinaria, verbale n. 2/2028', $riga->approvazione_rif);
        $this->assertSame('55.00', $riga->saldo_chiusura_cassa);
        $this->assertSame(55.0, $anni->apertura(2028)['cassa']);
        $this->assertSame('HIGH', DB::table('gl_audit_log')->where('evento', 'ANNOCONTABILE_UPDATED')->orderByDesc('id')->value('livello_rischio'));

        // Niente nuovi movimenti nell'anno chiuso
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm(['data' => '2027-12-30']))->assertSessionHas('error');
        $this->assertSame(2, PrimaNota::count());

        // Correzione: serve la motivazione, la data resta nell'anno, i saldi si aggiornano
        $dati = ['tipo' => 'uscita', 'conto' => 'cassa', 'data' => '2027-02-10', 'importo' => '45,00',
            'categoria_id' => $m->categoria_id, 'descrizione' => 'Prova corretta'];
        $this->actingAs($this->tesoriere)->put(route('prima-nota.update', $m), $dati)->assertSessionHasErrors('motivazione');
        $this->actingAs($this->tesoriere)->put(route('prima-nota.update', $m), array_merge($dati, ['data' => '2028-01-02', 'motivazione' => 'Importo letto male dalla fattura originale.']))
            ->assertSessionHas('error');
        $this->actingAs($this->tesoriere)->put(route('prima-nota.update', $m), $dati + ['motivazione' => 'Importo letto male dalla fattura originale.'])
            ->assertRedirect(route('prima-nota.show', $m));
        $this->assertSame('45.00', $m->fresh()->importo);
        $this->assertSame('50.00', $anni->trova(2027)->saldo_chiusura_cassa);
        $this->assertSame(50.0, $anni->apertura(2028)['cassa']);
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'PRIMA_NOTA_CORREZIONE_ANNO_CHIUSO')->where('livello_rischio', 'HIGH')->exists());

        // Eliminazione su anno chiuso: motivazione obbligatoria
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.destroy', $m), ['conferma' => 'si'])->assertSessionHasErrors('motivazione');
        $this->assertNotNull($m->fresh());

        // Riapertura: solo il Presidente, con motivazione
        $this->actingAs($this->tesoriere)->post(route('prima-nota.anni.riapri', 2027), ['motivazione' => 'Errore grave nel rendiconto approvato.'])->assertForbidden();
        $this->actingAs($this->presidente)->post(route('prima-nota.anni.riapri', 2027), ['motivazione' => 'breve'])->assertSessionHasErrors('motivazione');
        $this->actingAs($this->presidente)->post(route('prima-nota.anni.riapri', 2027), ['motivazione' => 'Errore grave nel rendiconto approvato.'])->assertRedirect();
        $this->assertFalse($anni->chiuso(2027));
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'ANNO_CONTABILE_RIAPERTO')->exists());
    }

    public function test_movimenti_collegati_e_giustificativi(): void
    {
        $quota = $this->movimento(['tipo' => 'entrata', 'categoria_id' => $this->cat('Quote associative', 'entrata')->id]);
        DB::table('gl_soci')->insert(['id' => 1, 'nome' => 'Marco', 'cognome' => 'Rossi', 'codice_fiscale' => 'RSSMRC80A01H501U', 'tipo' => 'ordinario', 'stato' => 'attivo', 'data_prima_iscrizione' => '2027-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $rinnovo = DB::table('gl_rinnovi')->insertGetId(['socio_id' => 1, 'anno' => 2027, 'importo' => 70, 'data_pagamento' => '2027-02-10', 'created_at' => now(), 'updated_at' => now()]);
        $quota->forceFill(['rinnovo_id' => $rinnovo])->save();
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.destroy', $quota), ['conferma' => 'si'])->assertSessionHas('error');
        $this->assertNotNull($quota->fresh());

        $m = $this->movimento();
        $altro = $this->movimento();
        $this->actingAs($this->tesoriere)->post(route('prima-nota.documenti.store', $m), [
            'documenti' => [UploadedFile::fake()->image('scontrino.jpg')],
        ])->assertRedirect();
        $d = PrimaNotaDocumento::firstOrFail();
        // Il documento di un movimento non si apre dalla scheda di un altro
        $this->actingAs($this->tesoriere)->get(route('prima-nota.documenti.show', [$altro, $d]))->assertNotFound();
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.documenti.destroy', [$m, $d]))->assertRedirect();
        Storage::disk('local')->assertMissing($d->path);

        // Eliminazione senza conferma: niente
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.destroy', $m))->assertSessionHasErrors('conferma');
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.destroy', $m), ['conferma' => 'si'])->assertRedirect();
        $this->assertNull($m->fresh());
        $this->assertSame('HIGH', DB::table('gl_audit_log')->where('evento', 'PRIMANOTA_DELETED')->value('livello_rischio'));
    }

    public function test_categorie_e_tag(): void
    {
        $this->actingAs($this->tesoriere)->get(route('prima-nota.categorie.index'))->assertOk()
            ->assertSee('Taglio erba')->assertSee('Solo bilancio')->assertSee('Da verificare (398)', false);

        $this->actingAs($this->tesoriere)->post(route('prima-nota.categorie.store'), ['tipo' => 'uscita', 'nome' => 'taglio ERBA', 'natura' => 'istituzionale'])
            ->assertSessionHasErrorsIn('nuovaCategoria', 'nome');
        // Stesso nome con tipo diverso: ammesso (gestionale-acv 1.0.2)
        $this->actingAs($this->tesoriere)->post(route('prima-nota.categorie.store'), ['tipo' => 'entrata', 'nome' => 'Taglio erba', 'natura' => 'commerciale'])
            ->assertRedirect();
        $nuova = PrimaNotaCategoria::where('tipo', 'entrata')->where('nome', 'Taglio erba')->firstOrFail();
        $this->assertTrue($nuova->flag_non_istituzionale);

        $this->actingAs($this->tesoriere)->put(route('prima-nota.categorie.update', $nuova), ['nome' => 'Vendita fieno', 'natura' => 'da_verificare'])->assertRedirect();
        $this->assertSame('Vendita fieno', $nuova->fresh()->nome);
        $this->assertTrue($nuova->fresh()->natura_da_verificare);

        // Sposta su: scambia l'ordine con la precedente
        $prima = $nuova->ordine;
        $this->actingAs($this->tesoriere)->post(route('prima-nota.categorie.sposta', $nuova), ['verso' => 'su'])->assertRedirect();
        $this->assertLessThan($prima, $nuova->fresh()->ordine);

        // Usata: non si elimina, si disattiva
        $usata = $this->cat('Acquisto materiali');
        $this->movimento(['categoria_id' => $usata->id]);
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.categorie.destroy', $usata))->assertSessionHas('error');
        $this->actingAs($this->tesoriere)->post(route('prima-nota.categorie.attiva', $usata))->assertRedirect();
        $this->assertFalse($usata->fresh()->attiva);
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.categorie.destroy', $nuova))->assertRedirect();
        $this->assertNull($nuova->fresh());

        // Di sistema: intoccabile
        $sistema = $this->cat('Uscite da classificare');
        $this->actingAs($this->presidente)->put(route('prima-nota.categorie.update', $sistema), ['nome' => 'Altro', 'natura' => 'istituzionale'])->assertForbidden();
        $this->actingAs($this->presidente)->post(route('prima-nota.categorie.attiva', $sistema))->assertForbidden();
        $this->actingAs($this->revisore)->post(route('prima-nota.categorie.store'), ['tipo' => 'uscita', 'nome' => 'X', 'natura' => 'istituzionale'])->assertForbidden();

        // Tag: rinomina senza doppioni, elimina solo se non usato
        $t1 = Tag::create(['nome' => 'Tettoia']);
        $t2 = Tag::create(['nome' => 'Campo']);
        $this->movimento(['tag_id' => $t1->id, 'categoria_id' => $this->cat('Opere e progetti')->id, 'importo' => '412.00']);
        $this->actingAs($this->tesoriere)->get(route('prima-nota.categorie.index'))->assertOk()->assertSee('412,00');
        $this->actingAs($this->tesoriere)->put(route('prima-nota.tag.update', $t2), ['nome' => 'tettoia'])->assertSessionHasErrorsIn('tag', 'nome');
        $this->actingAs($this->tesoriere)->put(route('prima-nota.tag.update', $t2), ['nome' => 'Campo estivo'])->assertRedirect();
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.tag.destroy', $t1))->assertSessionHas('error');
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.tag.destroy', $t2))->assertRedirect();
        $this->assertSame(['Tettoia'], Tag::pluck('nome')->all());
    }

    /** Revisione, giro 1 (v0.35.0): correzioni trovate rileggendo il codice. */
    public function test_revisione_giro_1(): void
    {
        // CSV: una descrizione che comincia con "=" non diventa una formula
        $this->movimento(['descrizione' => '=SOMMA(A1:A9)']);
        $csv = $this->actingAs($this->revisore)->get(route('prima-nota.export-csv'))->streamedContent();
        $this->assertStringContainsString("'=SOMMA(A1:A9)", $csv);

        // Nome del giustificativo con accenti: intestazione valida
        $m = $this->movimento();
        $this->actingAs($this->tesoriere)->post(route('prima-nota.documenti.store', $m), [
            'documenti' => [UploadedFile::fake()->create('ricevuta caffè.pdf', 20, 'application/pdf')],
        ])->assertRedirect();
        $d = $m->documenti()->firstOrFail();
        $r = $this->actingAs($this->tesoriere)->get(route('prima-nota.documenti.show', [$m, $d, 'scarica' => 1]))->assertOk();
        // Dalla v0.35.1 il nome è codificato (PN…): niente accenti, ma l'intestazione resta valida
        $this->assertStringContainsString('filename=' . $d->nome, $r->headers->get('content-disposition'));

        // Spesa anticipata non rimborsata: non si modifica dal modulo normale
        $anticipo = $this->movimento(['conto' => null, 'sospeso' => true, 'anticipato_da' => 'Luigi Tascio']);
        $this->actingAs($this->tesoriere)->get(route('prima-nota.edit', $anticipo))->assertRedirect(route('prima-nota.show', $anticipo));
        $this->actingAs($this->tesoriere)->put(route('prima-nota.update', $anticipo), $this->datiForm(['data' => '2027-02-10']))->assertSessionHas('error');
        $this->assertNull($anticipo->fresh()->conto);

        // Anno chiuso: aggiungere un giustificativo chiede la motivazione
        AnnoContabile::where('anno', 2027)->update(['stato' => 'chiuso', 'saldo_chiusura_cassa' => 0, 'saldo_chiusura_banca' => 0]);
        $this->actingAs($this->tesoriere)->post(route('prima-nota.documenti.store', $m), [
            'documenti' => [UploadedFile::fake()->image('altra.jpg')],
        ])->assertSessionHasErrorsIn('giustificativi', 'motivazione');
        $this->assertSame(1, $m->documenti()->count());
        $this->actingAs($this->tesoriere)->post(route('prima-nota.documenti.store', $m), [
            'documenti' => [UploadedFile::fake()->image('altra.jpg')], 'motivazione' => 'Ritrovata la ricevuta originale cartacea.',
        ])->assertRedirect();
        $this->assertSame(2, $m->documenti()->count());
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'PRIMA_NOTA_CORREZIONE_ANNO_CHIUSO')->exists());
    }

    /** Revisione, giro 2: anno senza movimenti in mezzo. */
    public function test_revisione_giro_2_anno_senza_movimenti(): void
    {
        $anni = app(AnniContabili::class);
        AnnoContabile::create(['anno' => 2026, 'saldo_apertura_cassa' => 100, 'saldo_apertura_banca' => 50]);
        $this->movimento(['data' => '2026-05-01', 'importo' => '10.00']);
        // 2027 senza movimenti, 2028 con un movimento
        $this->movimento(['data' => '2028-01-10', 'importo' => '5.00']);
        $this->assertFalse($anni->primoAnno(2028));
        $this->assertSame(['cassa' => 90.0, 'banca' => 50.0, 'da_impostare' => false, 'da_anno_prima' => true], $anni->apertura(2028));
        $this->assertSame(85.0, $anni->finali(2028)['cassa']);
        // Anno mai usato prima del primo: nessun avviso sui saldi
        $this->actingAs($this->revisore)->get(route('prima-nota.index', ['anno' => 2020]))->assertOk()->assertDontSee('Saldi di apertura 2020 da impostare');
    }

    // ----------------------------------------------------------------
    // Collaudo J del 06/10/2026 (v0.35.1)
    // ----------------------------------------------------------------

    /** J2: nome doppio → la finestra resta aperta con l'errore. */
    public function test_collaudo_j2_finestra_categoria_con_errore(): void
    {
        $this->actingAs($this->tesoriere)->from(route('prima-nota.categorie.index'))
            ->post(route('prima-nota.categorie.store'), ['tipo' => 'uscita', 'nome' => 'acquisto MATERIALI', 'natura' => 'istituzionale'])
            ->assertRedirect(route('prima-nota.categorie.index'))
            ->assertSessionHasErrorsIn('nuovaCategoria', 'nome');
        $pagina = $this->actingAs($this->tesoriere)->followingRedirects()->from(route('prima-nota.categorie.index'))
            ->post(route('prima-nota.categorie.store'), ['tipo' => 'entrata', 'nome' => 'Quote associative', 'natura' => 'commerciale']);
        $pagina->assertSee('Esiste già una categoria di entrata con questo nome.')
            ->assertSee('value="Quote associative"', false)                  // il nome scritto resta
            ->assertSee('<option value="commerciale" selected>', false)      // e la natura scelta
            ->assertDontSee('Operazione non eseguita.');                     // niente avviso in cima: l'errore è nella finestra

        $cat = $this->cat('Altre spese');
        $this->actingAs($this->tesoriere)->followingRedirects()->from(route('prima-nota.categorie.index'))
            ->put(route('prima-nota.categorie.update', $cat), ['_id' => $cat->id, 'nome' => 'Acquisto materiali', 'natura' => 'istituzionale'])
            ->assertSee('Esiste già una categoria di uscita con questo nome.');
    }

    /** J4: dopo un errore i file restano; nome codificato; anteprima; Nuovo movimento nella scheda. */
    public function test_collaudo_j4_giustificativi_trattenuti(): void
    {
        $this->actingAs($this->tesoriere)->from(route('prima-nota.create'))->post(route('prima-nota.store'), $this->datiForm([
            'importo' => '1,450',   // errore
            'documenti' => [UploadedFile::fake()->image('IMG_20271002.jpg'), UploadedFile::fake()->create('fattura.pdf', 50, 'application/pdf')],
        ]))->assertSessionHasErrors('importo');
        $this->assertSame(0, PrimaNota::count());
        $gettoni = session()->getOldInput('file_temp');
        $this->assertCount(2, $gettoni);
        $this->assertSame(2, count(Storage::disk('local')->files('tmp/prima-nota/' . $this->tesoriere->id)));

        // Il modulo li ripresenta come "già caricato"
        $this->actingAs($this->tesoriere)->get(route('prima-nota.create'))
            ->assertSee('IMG_20271002.jpg')->assertSee('già caricato')->assertSee('value="' . $gettoni[0] . '"', false);

        // Secondo invio: corretto l'importo, i file tornano come gettoni; uno sconosciuto si ignora
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm(['file_temp' => [...$gettoni, 'gettone-inventato']]))
            ->assertRedirect();
        $m = PrimaNota::firstOrFail();
        $this->assertSame(['PN2027-' . sprintf('%05d', $m->id) . '-01.jpg', 'PN2027-' . sprintf('%05d', $m->id) . '-02.pdf'],
            $m->documenti()->orderBy('id')->pluck('nome')->all());
        $this->assertSame([], Storage::disk('local')->files('tmp/prima-nota/' . $this->tesoriere->id));
        $this->assertSame([], session('prima_nota_file_temp'));

        // Un gettone già usato non si riusa
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm(['file_temp' => $gettoni]))->assertRedirect();
        $this->assertSame(0, PrimaNota::latest('id')->first()->documenti()->count());

        // Scheda: Nuovo movimento, anteprima dell'immagine, PDF con icona, visore
        $this->actingAs($this->tesoriere)->get(route('prima-nota.show', $m))->assertOk()
            ->assertSee('Nuovo movimento')
            ->assertSee('<img src="' . route('prima-nota.documenti.show', [$m, $m->documenti()->first()]) . '" alt="" loading="lazy">', false)
            ->assertSee('pn-anteprima-pdf', false)
            ->assertSee('Apri in una nuova scheda');
        $this->actingAs($this->revisore)->get(route('prima-nota.show', $m))->assertDontSee('Nuovo movimento');

        // Elenco: riga intera cliccabile
        $this->actingAs($this->tesoriere)->get(route('prima-nota.index', ['anno' => 2027]))
            ->assertSee('data-href="' . route('prima-nota.show', $m) . '"', false);
    }

    /** J9: ogni operazione dell'utente è una riga sola dell'Audit Log. */
    public function test_collaudo_j9_una_riga_per_operazione(): void
    {
        $conta = fn () => DB::table('gl_audit_log')->whereIn('entita_tipo', ['PrimaNota', 'PrimaNotaDocumento'])->count();

        $this->actingAs($this->tesoriere)->post(route('prima-nota.giroconto.store'), ['data' => '2027-03-01', 'conto_da' => 'cassa', 'conto_a' => 'banca', 'importo' => '100']);
        $this->assertSame(1, $conta());
        $riga = DB::table('gl_audit_log')->where('evento', 'PRIMANOTA_CREATED')->first();
        $this->assertSame('Cassa → Banca', json_decode($riga->new_values, true)['giroconto']);
        $uscita = PrimaNota::where('tipo', 'uscita')->firstOrFail();
        $this->assertSame((int) $uscita->id, (int) $riga->entita_id);
        $this->assertNotNull($uscita->giroconto->giroconto_id);   // coppia collegata nei due sensi

        $this->actingAs($this->tesoriere)->put(route('prima-nota.update', $uscita), ['data' => '2027-03-01', 'conto_da' => 'banca', 'conto_a' => 'cassa', 'importo' => '120']);
        $this->assertSame(2, $conta());
        $this->assertSame('cassa', $uscita->fresh()->giroconto->conto);

        // Eliminazione dalla riga di entrata: una riga sola, quella dell'uscita
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.destroy', $uscita->giroconto_id), ['conferma' => 'si'])->assertRedirect();
        $this->assertSame(0, PrimaNota::count());
        $this->assertSame(3, $conta());
        $this->assertSame((int) $uscita->id, (int) DB::table('gl_audit_log')->where('evento', 'PRIMANOTA_DELETED')->value('entita_id'));

        // Movimento con giustificativo: all'eliminazione una riga, con i giustificativi nei valori
        $this->actingAs($this->tesoriere)->post(route('prima-nota.store'), $this->datiForm(['documenti' => [UploadedFile::fake()->image('a.jpg')]]));
        $m = PrimaNota::firstOrFail();
        $prima = $conta();
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.destroy', $m), ['conferma' => 'si']);
        $this->assertSame($prima + 1, $conta());
        $old = json_decode(DB::table('gl_audit_log')->where('evento', 'PRIMANOTA_DELETED')->orderByDesc('id')->value('old_values'), true);
        $this->assertSame('PN2027-' . sprintf('%05d', $m->id) . '-01.jpg', $old['giustificativi']);
        $this->assertSame(0, PrimaNotaDocumento::count());

        // Etichette leggibili e hash per intero nel dettaglio
        $hash = DB::table('gl_audit_log')->orderByDesc('id')->value('hash');
        $this->actingAs($this->presidente)->get(route('audit-log.index', ['anno' => 2027]))->assertOk()
            ->assertSee('Prima Nota: eliminazione')->assertSee($hash);
    }
}
