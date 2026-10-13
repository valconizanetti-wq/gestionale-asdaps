<?php

namespace Tests\Feature;

use App\Models\AnnoContabile;
use App\Models\AuditLog;
use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\PrimaNotaScontrino;
use App\Models\Soggetto;
use App\Models\User;
use App\Scontrini\Fornitori;
use App\Services\AnniContabili;
use App\Services\Scontrini;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\PrimaNotaCategoriaSeeder;
use Database\Seeders\ProfiloEnteSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as RichiestaHttp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Scontrini dal telefono con OCR, v0.39.0 (specifiche/scontrini.md, tavole
 * SN-1…SN-8). OCR.space finto con il testo vero letto il 12/10/2026
 * (tests/Fixtures/scontrini-ocrspace/risultati-ocr.json).
 */
class ScontriniTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private User $tesoriere;

    private User $segretario;

    private User $consigliere;

    private User $revisore;

    private array $ocr;

    /** Foto di cui OCR.space finto restituisce il testo; null: servizio che non risponde. */
    private ?string $fotoOcr = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-12 10:00:00');
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class,
            PrimaNotaCategoriaSeeder::class, ProfiloEnteSeeder::class]);
        Storage::fake('local');
        config(['services.ocr_space.key' => 'chiave-di-prova', 'services.ocr_space.url' => 'https://api.ocr.space/parse/image']);
        // Anno contabile 2026 di prova (come nel collaudo, blocco J): la contabilità parte dal 2027
        AnnoContabile::create(['anno' => 2026, 'saldo_apertura_cassa' => 0, 'saldo_apertura_banca' => 0]);
        $this->ocr = json_decode(file_get_contents(base_path('tests/Fixtures/scontrini-ocrspace/risultati-ocr.json')), true);
        // Un solo finto per tutto il test (i finti di Http si accumulano e vince il primo)
        Http::fake(['api.ocr.space/*' => function () {
            if ($this->fotoOcr === null) {
                throw new \Illuminate\Http\Client\ConnectionException('timeout');
            }

            return Http::response([
                'ParsedResults' => [['ParsedText' => $this->ocr[$this->fotoOcr]['tabella']['testo']]],
                'OCRExitCode' => 1,
                'IsErroredOnProcessing' => false,
            ]);
        }]);

        $this->presidente = User::factory()->conRuoli('presidente')->create(['name' => 'Andrea Presidente', 'email' => 'presidente@example.it', 'attivo' => true]);
        $this->tesoriere = User::factory()->conRuoli('tesoriere')->create(['name' => 'Paolo Tesoriere', 'email' => 'tesoriere@example.it', 'attivo' => true]);
        $this->segretario = User::factory()->conRuoli('segretario')->create(['name' => 'Luigi Segretario', 'email' => 'segretario@example.it', 'attivo' => true]);
        $this->consigliere = User::factory()->conRuoli('consigliere')->create(['name' => 'Massimo Consigliere', 'email' => 'consigliere@example.it', 'attivo' => true]);
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

    /** OCR.space finto: risponde con il testo vero della foto indicata. */
    private function fingiOcr(?string $foto): void
    {
        $this->fotoOcr = $foto;
    }

    /** Passo 1: foto (generata, 1200×1600) e nota; restituisce il gettone della bozza. */
    private function leggi(User $u, string $nota = 'Colla e nastro per le frecce'): string
    {
        $r = $this->actingAs($u)->post(route('scontrini.leggi'), [
            'foto' => UploadedFile::fake()->image('IMG_0001.jpg', 1200, 1600),
            'nota' => $nota,
        ]);
        $r->assertRedirect();
        $this->assertMatchesRegularExpression('#/scontrini/nuovo/([A-Za-z0-9]{32})$#', $r->headers->get('Location'));

        return substr($r->headers->get('Location'), -32);
    }

    private function registra(User $u, string $token, array $dati = [])
    {
        return $this->actingAs($u)->post(route('scontrini.store', $token), $dati + [
            'importo' => '14,50',
            'data' => '2026-10-03',
            'conto' => 'banca',
            'nota' => 'Colla e nastro per le frecce',
            'fornitore' => 'nuovo',
            'nuovo_nome' => 'Ferramenta Banella Franco',
            'nuovo_partita_iva' => '01452300567',
            'nuovo_indirizzo' => 'Via delle Piane 7/A',
            'nuovo_comune' => 'Castel Giorgio',
        ]);
    }

    private function categoria(string $nome): int
    {
        return PrimaNotaCategoria::where('tipo', 'uscita')->where('nome', $nome)->value('id');
    }

    /** Scontrino registrato dal consigliere (ferramenta, 14,50, banca). */
    private function scontrinoFerramenta(): PrimaNotaScontrino
    {
        $this->fingiOcr('Ferramenta Banella Franco 14,50 euro.jpg');
        $this->registra($this->consigliere, $this->leggi($this->consigliere))->assertRedirect(route('scontrini.miei'));

        return PrimaNotaScontrino::latest('id')->firstOrFail();
    }

    // ----------------------------------------------------------------
    // Passi 1 e 2 dal telefono
    // ----------------------------------------------------------------

    public function test_il_consigliere_carica_uno_scontrino_letto_dalla_foto(): void
    {
        $this->fingiOcr('Ferramenta Banella Franco 14,50 euro.jpg');
        $this->actingAs($this->consigliere)->get(route('scontrini.create'))->assertOk()->assertSee('Fotografa lo scontrino');
        $token = $this->leggi($this->consigliere);

        // Una richiesta, motore 2, a tabella, con la copia ridotta (sotto 1 MB)
        Http::assertSentCount(1);
        Http::assertSent(fn (RichiestaHttp $r) => $r->hasHeader('apikey', 'chiave-di-prova')
            && $r['isTable'] === 'true' && $r['OCREngine'] === '2' && $r['language'] === 'ita'
            && str_starts_with($r['base64Image'], 'data:image/jpeg;base64,')
            && strlen(base64_decode(substr($r['base64Image'], 23))) < 1024 * 1024);
        // Fino a «Registra» nel database non c'è nulla
        $this->assertSame(0, PrimaNotaScontrino::count());

        $this->actingAs($this->consigliere)->get(route('scontrini.conferma', $token))->assertOk()
            ->assertSee('Letti dalla foto: controllali')
            ->assertSee('value="14,50"', false)
            ->assertSee('value="2026-10-03"', false)
            ->assertSee('FERRAMENTA BANELLA FRANCO')
            ->assertSee('Nessun fornitore simile')
            ->assertSee('ultima occasione per correggere');
        $this->actingAs($this->consigliere)->get(route('scontrini.conferma.foto', $token))->assertOk();

        $this->registra($this->consigliere, $token)->assertRedirect(route('scontrini.miei'));

        $s = PrimaNotaScontrino::with(['movimenti.categoria', 'file', 'righe', 'soggetto'])->sole();
        $this->assertSame(1, $s->numero);
        $this->assertSame('SC2026-001', $s->codice());
        $this->assertSame('14.50', (string) $s->importo_totale);
        $this->assertSame('da_verificare', $s->stato);
        $this->assertSame('letto', $s->ocr_esito);
        $this->assertSame($this->consigliere->id, $s->registrato_da);
        // Fornitore nuovo con i dati corretti
        $this->assertSame('Ferramenta Banella Franco', $s->soggetto->nome);
        $this->assertSame('01452300567', $s->soggetto->partita_iva);
        $this->assertSame('Castel Giorgio', $s->soggetto->comune);
        // Un movimento su «Uscite da classificare», banca
        $m = $s->movimenti->sole();
        $this->assertSame(PrimaNotaCategoria::DA_CLASSIFICARE, $m->categoria->codice);
        $this->assertSame('14.50', (string) $m->importo);
        $this->assertSame('banca', $m->conto);
        $this->assertSame('Ferramenta Banella Franco', $m->soggetto);
        $this->assertSame('SC2026-001', $m->numero_documento);
        // Foto archiviata col nome codificato, anteprima e miniatura
        $this->assertSame('SC2026-001.jpg', $s->file->sole()->nome);
        Storage::disk('local')->assertExists(['scontrini/2026/SC2026-001.jpg', $s->anteprima_path, $s->miniatura_path]);
        // Righe lette che non tornano col totale: una riga sola col totale
        $this->assertSame('14.50', (string) $s->righe->sole()->importo);
        // Bozza tolta
        $this->assertSame([], Storage::disk('local')->files('tmp/scontrini/' . $this->consigliere->id));

        $this->actingAs($this->consigliere)->get(route('scontrini.miei'))->assertOk()
            ->assertSee('Scontrino n. 1 registrato')->assertSee('Da verificare');
    }

    public function test_righe_lette_che_tornano_si_conservano(): void
    {
        $this->fingiOcr('Toxon Sport 9,20 euro.jpg');
        $token = $this->leggi($this->consigliere, 'Punte e cocche');
        $this->actingAs($this->consigliere)->get(route('scontrini.conferma', $token))->assertSee('Righe lette: 3');
        $this->registra($this->consigliere, $token, ['importo' => '9,20', 'data' => '2026-09-17', 'conto' => 'cassa',
            'nuovo_nome' => 'Toxon Sport', 'nuovo_partita_iva' => '01930680549', 'nuovo_comune' => 'Perugia', 'nuovo_indirizzo' => null])->assertRedirect();

        $s = PrimaNotaScontrino::with('righe')->sole();
        $this->assertSame(['3.00', '4.40', '1.80'], $s->righe->pluck('importo')->map(fn ($i) => (string) $i)->all());
        // Il codice fiscale del titolare stampato sullo scontrino non si salva, nemmeno nel testo letto
        $this->assertStringNotContainsString('[codice fiscale]', (string) json_encode(Soggetto::sole()->toArray()));
        $this->assertSame('C.F. [codice fiscale]', \App\Scontrini\ServizioOcr::senzaCodiciFiscali('C.F. RSSMRA80A01H501U'));
    }

    public function test_fornitore_riconosciuto_per_partita_iva_e_per_nome(): void
    {
        $ferramenta = Soggetto::create(['nome' => 'Ferramenta Banella', 'partita_iva' => '01452300567', 'comune' => 'Castel Giorgio']);
        $this->fingiOcr('Ferramenta Banella Franco 14,50 euro.jpg');
        $token = $this->leggi($this->consigliere);
        $this->actingAs($this->consigliere)->get(route('scontrini.conferma', $token))
            ->assertSee('È la partita IVA di un fornitore in elenco')->assertSee('già in elenco');
        $this->registra($this->consigliere, $token, ['fornitore' => (string) $ferramenta->id])->assertRedirect();
        $this->assertSame(1, Soggetto::count());
        $this->assertSame('Ferramenta Banella', PrimaNota::sole()->soggetto);

        // Per nome («Tabaccheria Chiasso Roberto» già in elenco senza partita IVA): le si aggiunge quella letta
        $tabaccheria = Soggetto::create(['nome' => 'Tabaccheria Chiasso Roberto']);
        $this->fingiOcr('Tabaccheria Chiasso 3 euro.jpg');
        $token = $this->leggi($this->consigliere, 'Marche da bollo');
        $this->actingAs($this->consigliere)->get(route('scontrini.conferma', $token))->assertSee("C'è già un fornitore con questo nome", false);
        $this->registra($this->consigliere, $token, ['fornitore' => (string) $tabaccheria->id, 'aggiungi_piva' => '00460930555',
            'importo' => '3,00', 'data' => '2026-09-07', 'conto' => 'cassa'])->assertRedirect();
        $this->assertSame('00460930555', $tabaccheria->fresh()->partita_iva);
    }

    public function test_forse_e_uno_di_questi_con_partita_iva_letta_male(): void
    {
        // Eurospin 26,48: partita IVA letta «84788841003» (vera 04766841003)
        Soggetto::create(['nome' => 'Eurospin Lazio S.p.A.', 'partita_iva' => '04766841003', 'comune' => 'Acquapendente']);
        $r = (new Fornitori())->riconosci('Eurospin', '84788841003');
        $this->assertNull($r['trovato']);
        $this->assertSame('Eurospin Lazio S.p.A.', $r['simili']->first()->nome);

        $this->assertSame(Fornitori::normalizza('ACQUA & SAPONE S.R.L.'), Fornitori::normalizza('Acqua e Sapone srl'));
        $this->assertSame('geaflor tiberi emanuele', Fornitori::normalizza('GEAFLOR S.N.C. di Tiberi Emanuele & C.'));
        $this->assertSame(3, Fornitori::cifreDiverse('04766841003', '84788841003'));
    }

    public function test_partita_iva_gia_di_un_altro_fornitore(): void
    {
        Soggetto::create(['nome' => 'Banella Ferramenta', 'partita_iva' => '01452300567']);
        $this->fingiOcr('Ferramenta Banella Franco 14,50 euro.jpg');
        $token = $this->leggi($this->consigliere);
        $this->registra($this->consigliere, $token)->assertSessionHasErrors('nuovo_partita_iva');
        $this->assertSame(0, PrimaNotaScontrino::count());
        $this->assertSame(1, Soggetto::count());
        // La bozza resta: si corregge e si registra
        $this->actingAs($this->consigliere)->get(route('scontrini.conferma', $token))->assertOk();
    }

    public function test_foto_non_letta_si_scrive_a_mano(): void
    {
        config(['services.ocr_space.key' => null]);
        $token = $this->leggi($this->consigliere);
        Http::assertNothingSent();
        $this->actingAs($this->consigliere)->get(route('scontrini.conferma', $token))->assertOk()
            ->assertSee('Foto non letta')->assertSee('La lettura automatica non è configurata');
        $this->registra($this->consigliere, $token)->assertRedirect(route('scontrini.miei'));
        $this->assertSame('non_disponibile', PrimaNotaScontrino::sole()->ocr_esito);

        // Servizio che non risponde: stessa cosa, nessuna eccezione
        config(['services.ocr_space.key' => 'chiave-di-prova']);
        $this->fingiOcr(null);
        $token = $this->leggi($this->consigliere);
        $this->actingAs($this->consigliere)->get(route('scontrini.conferma', $token))->assertSee('Il servizio di lettura non ha risposto');
    }

    public function test_controlli_del_passo_1_e_2(): void
    {
        $this->actingAs($this->consigliere)->post(route('scontrini.leggi'), ['nota' => ''])
            ->assertSessionHasErrors(['foto', 'nota']);
        // HEIC con il nome .jpg
        $heic = UploadedFile::fake()->createWithContent('IMG_0002.jpg', "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic");
        $this->actingAs($this->consigliere)->post(route('scontrini.leggi'), ['foto' => $heic, 'nota' => 'Colla'])
            ->assertSessionHasErrors(['foto' => "La foto è in formato HEIC e non si può leggere. Sull'iPhone: Impostazioni › Fotocamera › Formati › «Più compatibile», poi rifai la foto."]);

        $this->fingiOcr('Bar Tiracorrendo 9 euro.jpg');
        $token = $this->leggi($this->consigliere);
        // Data nel futuro, fornitore mancante, partita IVA sbagliata
        $this->registra($this->consigliere, $token, ['data' => '2026-10-20'])->assertSessionHasErrors('data');
        $this->registra($this->consigliere, $token, ['fornitore' => null])->assertSessionHasErrors('fornitore');
        $this->registra($this->consigliere, $token, ['nuovo_partita_iva' => '01452300568'])->assertSessionHasErrors('nuovo_partita_iva');
        // Anno chiuso
        AnnoContabile::create(['anno' => 2025, 'saldo_apertura_cassa' => 0, 'saldo_apertura_banca' => 0]);
        AnnoContabile::where('anno', 2025)->update(['stato' => 'chiuso', 'saldo_chiusura_cassa' => 0, 'saldo_chiusura_banca' => 0]);
        $this->registra($this->consigliere, $token, ['data' => '2025-12-30'])->assertSessionHasErrors('data');
        $this->assertSame(0, PrimaNotaScontrino::count());
        // Prima dell'avvio della contabilità (nessun anno contabile 2024): vecchio gestionale
        $this->registra($this->consigliere, $token, ['data' => '2024-05-10'])
            ->assertSessionHasErrors(['data' => "Lo scontrino del 2024 è prima dell'avvio della contabilità nel gestionale (2025): va registrato nel vecchio gestionale."]);

        // «Annulla» toglie la bozza
        $this->actingAs($this->consigliere)->delete(route('scontrini.annulla', $token))->assertRedirect(route('scontrini.create'));
        $this->actingAs($this->consigliere)->get(route('scontrini.conferma', $token))->assertRedirect(route('scontrini.create'));
    }

    // ----------------------------------------------------------------
    // Permessi
    // ----------------------------------------------------------------

    public function test_permessi(): void
    {
        $s = $this->scontrinoFerramenta();

        // Il consigliere: Prima Nota in lettura, carica scontrini, non li modifica
        $this->actingAs($this->consigliere)->get(route('prima-nota.index'))->assertOk()->assertSee('Nuovo scontrino');
        $this->actingAs($this->consigliere)->get(route('prima-nota.create'))->assertForbidden();
        $this->actingAs($this->consigliere)->get(route('scontrini.show', $s))->assertOk()->assertSee('Sola lettura')->assertDontSee('Registra la divisione');
        $this->actingAs($this->consigliere)->put(route('scontrini.update', $s), ['importo' => '1,00'])->assertForbidden();
        $this->actingAs($this->consigliere)->post(route('scontrini.dividi', $s), [])->assertForbidden();
        $this->actingAs($this->consigliere)->delete(route('scontrini.destroy', $s), ['conferma' => 'si'])->assertForbidden();
        $this->actingAs($this->consigliere)->post(route('scontrini.fornitori.unisci'), [])->assertForbidden();
        $this->actingAs($this->consigliere)->get(route('scontrini.fornitori.index'))->assertOk()->assertDontSee('Nuovo fornitore');
        // Segretario: carica anche lui
        $this->actingAs($this->segretario)->get(route('scontrini.create'))->assertOk();
        // Revisore: legge tutto, non carica
        $this->actingAs($this->revisore)->get(route('scontrini.index'))->assertOk();
        $this->actingAs($this->revisore)->get(route('scontrini.show', $s))->assertOk();
        $this->actingAs($this->revisore)->get(route('scontrini.create'))->assertForbidden();
        // Tesoriere: scheda modificabile
        $this->actingAs($this->tesoriere)->get(route('scontrini.show', $s))->assertOk()->assertSee('Registra la divisione')->assertSee('Elimina lo scontrino');
        $this->actingAs($this->tesoriere)->get(route('scontrini.index'))->assertOk()->assertSee('Ferramenta Banella Franco')->assertSee('Da verificare');
        $this->actingAs($this->tesoriere)->get(route('scontrini.anteprima', [$s, 'mini' => 1]))->assertOk();
        $this->actingAs($this->tesoriere)->get(route('scontrini.foto.show', [$s, $s->file->first()]))->assertOk();
    }

    // ----------------------------------------------------------------
    // Divisione (SN-6)
    // ----------------------------------------------------------------

    public function test_registra_la_divisione_un_movimento_per_categoria(): void
    {
        $s = $this->scontrinoFerramenta();
        $daClassificare = $s->movimenti()->sole();
        $materiali = $this->categoria('Acquisto materiali');
        $bagni = $this->categoria('Manutenzione e gestione bagni');
        $attrezzature = $this->categoria('Acquisto attrezzature');

        $this->actingAs($this->tesoriere)->post(route('scontrini.dividi', $s), ['righe' => [
            ['descrizione' => 'Colla per frecce', 'importo' => '3,90', 'categoria_id' => $materiali],
            ['descrizione' => 'Nastro telato', 'importo' => '2,00', 'categoria_id' => $materiali],
            ['descrizione' => 'Cerniera inox', 'importo' => '5,10', 'categoria_id' => $bagni],
            ['descrizione' => 'Pinza', 'importo' => '4,50', 'categoria_id' => $attrezzature],
            ['descrizione' => 'Sconto', 'importo' => '-1,00', 'categoria_id' => $attrezzature],
        ]])->assertRedirect(route('scontrini.show', $s))->assertSessionHas('success', 'Divisione registrata: 3 movimenti in Prima Nota.');

        $s->refresh();
        $this->assertSame('verificato', $s->stato);
        $movimenti = $s->movimenti()->orderBy('id')->get();
        $this->assertCount(3, $movimenti);
        // Il movimento su «Uscite da classificare» si riusa (stesso numero) per il primo gruppo
        $this->assertSame($daClassificare->id, $movimenti[0]->id);
        $this->assertSame($materiali, $movimenti[0]->categoria_id);
        $this->assertSame('5.90', (string) $movimenti[0]->importo);
        $this->assertSame('Colla per frecce, Nastro telato', $movimenti[0]->descrizione);
        $this->assertSame('3.50', (string) $movimenti[2]->importo);
        $this->assertSame('banca', $movimenti[2]->conto);
        $this->assertSame('Ferramenta Banella Franco', $movimenti[2]->soggetto);
        $this->assertSame(5, $s->righe()->count());
        $this->assertSame($movimenti[2]->id, $s->righe()->where('descrizione', 'Sconto')->value('prima_nota_id'));
        $this->assertTrue(AuditLog::where('evento', 'SCONTRINO_DIVISO')->where('entita_id', $s->id)->exists());

        // Di nuovo, tutto in una categoria: restano un movimento, gli altri si tolgono
        $this->actingAs($this->tesoriere)->post(route('scontrini.dividi', $s), ['righe' => [
            ['descrizione' => 'Materiale vario', 'importo' => '14,50', 'categoria_id' => $bagni],
        ]])->assertSessionHas('success', 'Divisione registrata: 1 movimento in Prima Nota, 2 tolti.');
        $this->assertSame([$movimenti[1]->id], $s->movimenti()->pluck('id')->all());
        $this->assertSame('verificato', $s->fresh()->stato);
    }

    public function test_la_divisione_deve_tornare_al_centesimo(): void
    {
        $s = $this->scontrinoFerramenta();
        $materiali = $this->categoria('Acquisto materiali');
        $this->actingAs($this->tesoriere)->post(route('scontrini.dividi', $s), ['righe' => [
            ['descrizione' => 'Colla', 'importo' => '10,00', 'categoria_id' => $materiali],
            ['descrizione' => 'Nastro', 'importo' => '4,49', 'categoria_id' => $materiali],
        ]])->assertSessionHasErrorsIn('divisione', ['righe']);
        // Categoria di sistema non ammessa
        $this->actingAs($this->tesoriere)->post(route('scontrini.dividi', $s), ['righe' => [
            ['descrizione' => 'Tutto', 'importo' => '14,50', 'categoria_id' => PrimaNotaCategoria::where('codice', PrimaNotaCategoria::DA_CLASSIFICARE)->value('id')],
        ]])->assertSessionHasErrorsIn('divisione', ['righe.0.categoria_id']);
        $this->assertSame('da_verificare', $s->fresh()->stato);
        $this->assertSame(1, $s->movimenti()->count());

        // Piano: stessa regola della scheda
        $piano = Scontrini::piano([
            ['descrizione' => 'A', 'importo' => '1.00', 'categoria_id' => 5],
            ['descrizione' => 'B', 'importo' => '2.00', 'categoria_id' => 7],
            ['descrizione' => 'C', 'importo' => '3.00', 'categoria_id' => 5],
        ], [['id' => 10, 'categoria_id' => 99], ['id' => 11, 'categoria_id' => 7], ['id' => 12, 'categoria_id' => 1]]);
        $this->assertSame([[5, '4.00', 'A, C', 10], [7, '2.00', 'B', 11]], array_map(fn ($g) => array_values($g), $piano['gruppi']));
        $this->assertSame([12], $piano['da_togliere']);
    }

    public function test_salva_i_dati_e_da_quadrare(): void
    {
        $s = $this->scontrinoFerramenta();
        $altro = Soggetto::create(['nome' => 'Brico Orvieto srl']);

        // Un movimento solo: il totale passa al movimento e alla riga
        $this->actingAs($this->tesoriere)->put(route('scontrini.update', $s), [
            'importo' => '15,50', 'data' => '2026-10-04', 'conto' => 'cassa', 'soggetto_id' => $altro->id,
        ])->assertRedirect(route('scontrini.show', $s));
        $m = $s->movimenti()->sole();
        $this->assertSame('15.50', (string) $m->importo);
        $this->assertSame('cassa', $m->conto);
        $this->assertSame('2026-10-04', $m->data->toDateString());
        $this->assertSame('Brico Orvieto srl', $m->soggetto);
        $this->assertSame('da_verificare', $s->fresh()->stato);
        // La data resta nell'anno dello scontrino
        $this->actingAs($this->tesoriere)->put(route('scontrini.update', $s), [
            'importo' => '15,50', 'data' => '2025-12-31', 'conto' => 'cassa', 'soggetto_id' => $altro->id,
        ])->assertSessionHasErrorsIn('dati', ['data']);

        // Divisa in due: un nuovo totale la rende «da quadrare» e blocca la chiusura dell'anno
        $this->actingAs($this->tesoriere)->post(route('scontrini.dividi', $s), ['righe' => [
            ['descrizione' => 'Colla', 'importo' => '10,00', 'categoria_id' => $this->categoria('Acquisto materiali')],
            ['descrizione' => 'Pinza', 'importo' => '5,50', 'categoria_id' => $this->categoria('Acquisto attrezzature')],
        ]]);
        $this->actingAs($this->tesoriere)->put(route('scontrini.update', $s), [
            'importo' => '16,00', 'data' => '2026-10-04', 'conto' => 'cassa', 'soggetto_id' => $altro->id,
        ])->assertSessionHas('success', 'Dati salvati. Il totale non torna più con i movimenti: rifai la divisione.');
        $this->assertSame('da_quadrare', $s->fresh()->stato);
        $controllo = collect(app(AnniContabili::class)->controlliChiusura(2026))->firstWhere(0, 'Nessuno scontrino da quadrare');
        $this->assertFalse($controllo[1]);
        $this->actingAs($this->tesoriere)->get(route('scontrini.index', ['stato' => 'da_quadrare']))->assertSee('Da quadrare');
    }

    public function test_dalla_prima_nota_si_cambia_la_categoria_non_l_importo(): void
    {
        $s = $this->scontrinoFerramenta();
        $m = $s->movimenti()->sole();
        $this->actingAs($this->tesoriere)->get(route('prima-nota.edit', $m))->assertOk()->assertSee('Movimento dello scontrino SC2026-001');
        $this->actingAs($this->tesoriere)->put(route('prima-nota.update', $m), [
            'tipo' => 'entrata', 'conto' => 'cassa', 'data' => '2026-01-01', 'importo' => '999,00',
            'categoria_id' => $this->categoria('Acquisto materiali'), 'descrizione' => 'Colla e nastro',
        ])->assertRedirect(route('prima-nota.show', $m));
        $m->refresh();
        $this->assertSame('14.50', (string) $m->importo);
        $this->assertSame('uscita', $m->tipo);
        $this->assertSame('banca', $m->conto);
        $this->assertSame('verificato', $s->fresh()->stato);
        $this->assertSame($this->categoria('Acquisto materiali'), $s->righe()->value('categoria_id'));
        // Dalla Prima Nota non si elimina
        $this->actingAs($this->tesoriere)->delete(route('prima-nota.destroy', $m), ['conferma' => 'si'])->assertSessionHas('error');
        $this->actingAs($this->tesoriere)->get(route('prima-nota.show', $m))->assertSee('Scontrino SC2026-001')->assertSee('SC2026-001.jpg');
    }

    public function test_elimina_lo_scontrino(): void
    {
        $s = $this->scontrinoFerramenta();
        $file = $s->file->first()->path;
        $this->actingAs($this->tesoriere)->delete(route('scontrini.destroy', $s), ['conferma' => 'si'])
            ->assertRedirect(route('scontrini.index', ['anno' => 2026]));
        $this->assertSame(0, PrimaNotaScontrino::count());
        $this->assertSame(0, PrimaNota::count());
        Storage::disk('local')->assertMissing($file);
        $riga = AuditLog::where('evento', 'SCONTRINO_ELIMINATO')->sole();
        $this->assertSame('HIGH', $riga->livello_rischio);
        // Una sola riga per l'operazione: i movimenti si tolgono senza eventi
        $this->assertSame(0, AuditLog::where('modello', 'PrimaNota')->where('operazione', 'deleted')->count());
    }

    public function test_aggiungi_una_foto(): void
    {
        $s = $this->scontrinoFerramenta();
        $this->actingAs($this->tesoriere)->post(route('scontrini.foto.store', $s), [
            'foto' => UploadedFile::fake()->create('retro.pdf', 200, 'application/pdf'),
        ])->assertRedirect(route('scontrini.show', $s));
        $this->assertSame(['SC2026-001.jpg', 'SC2026-001-02.pdf'], $s->file()->pluck('nome')->all());
    }

    // ----------------------------------------------------------------
    // Fornitori (SN-7)
    // ----------------------------------------------------------------

    public function test_fornitori_rinomina_unisci_elimina(): void
    {
        $s = $this->scontrinoFerramenta();
        $doppione = Soggetto::create(['nome' => 'FERRAMENTA BANELLA FRANCO S.N.C.']);
        PrimaNota::create(['tipo' => 'uscita', 'conto' => 'cassa', 'data' => '2026-03-14', 'importo' => 5, 'descrizione' => 'Viti',
            'categoria_id' => $this->categoria('Acquisto materiali'), 'soggetto' => $doppione->nome]);
        $this->actingAs($this->tesoriere)->get(route('scontrini.fornitori.index'))->assertOk()->assertSee('Possibili doppioni');

        $ferramenta = $s->soggetto;
        $this->actingAs($this->tesoriere)->post(route('scontrini.fornitori.unisci'), ['tieni' => $ferramenta->id, 'togli' => $doppione->id])
            ->assertRedirect(route('scontrini.fornitori.index'));
        $this->assertNull(Soggetto::find($doppione->id));
        $this->assertSame(2, PrimaNota::where('soggetto', 'Ferramenta Banella Franco')->count());
        $this->assertTrue(AuditLog::where('evento', 'FORNITORI_UNITI')->exists());

        // Rinomina: anche i movimenti
        $this->actingAs($this->tesoriere)->put(route('scontrini.fornitori.update', $ferramenta), [
            'nome' => 'Ferramenta Banella', 'partita_iva' => '01452300567', 'comune' => 'Castel Giorgio', 'attivo' => '1',
        ])->assertRedirect();
        $this->assertSame(2, PrimaNota::where('soggetto', 'Ferramenta Banella')->count());
        $this->assertSame('Ferramenta Banella', $s->fresh()->fornitoreNome());

        // Usato: non si elimina; mai usato sì
        $this->actingAs($this->tesoriere)->delete(route('scontrini.fornitori.destroy', $ferramenta))->assertSessionHas('error');
        $nuovo = Soggetto::create(['nome' => 'Vivai Vitale']);
        $this->actingAs($this->tesoriere)->delete(route('scontrini.fornitori.destroy', $nuovo))->assertRedirect();
        $this->assertNull(Soggetto::find($nuovo->id));
        // Partita IVA sbagliata o di un altro
        $this->actingAs($this->tesoriere)->post(route('scontrini.fornitori.store'), ['nome' => 'Altro', 'partita_iva' => '01452300567'])
            ->assertSessionHasErrorsIn('nuovo', ['partita_iva']);
    }
}
