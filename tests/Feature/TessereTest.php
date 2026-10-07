<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PrimaNota;
use App\Models\Rinnovo;
use App\Models\Socio;
use App\Models\Tesseramento;
use App\Models\TesseraPacchetto;
use App\Models\TesseraTipo;
use App\Models\User;
use App\Services\Tessere;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\PrimaNotaCategoriaSeeder;
use Database\Seeders\ProfiloEnteSeeder;
use Database\Seeders\QualificaSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Database\Seeders\TesseraTipoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tessere CSEN v0.37.0 (analisi-tessere-scadenze.md, tavole TS-1…TS-5).
 */
class TessereTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private User $tesoriere;

    private User $revisore;

    private User $segretario;

    private TesseraTipo $silver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2027-02-15 10:00:00');
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class,
            PrimaNotaCategoriaSeeder::class, ProfiloEnteSeeder::class, QualificaSeeder::class, TesseraTipoSeeder::class]);
        Mail::fake();
        Storage::fake('local');
        $this->presidente = User::factory()->create(['ruolo' => 'presidente', 'attivo' => true]);
        $this->tesoriere = User::factory()->create(['ruolo' => 'tesoriere', 'attivo' => true]);
        $this->revisore = User::factory()->create(['ruolo' => 'revisore', 'attivo' => true]);
        $this->segretario = User::factory()->create(['ruolo' => 'segretario', 'attivo' => true]);
        $this->silver = TesseraTipo::where('nome', 'Silver Base')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function socio(array $dati = []): Socio
    {
        static $n = 0;
        $n++;
        $s = new Socio();
        $s->forceFill($dati + [
            'nome' => 'Luca', 'cognome' => 'Tascio' . $n, 'codice_fiscale' => sprintf('TSCLCU80A01E7%02dX', $n),
            'tipo' => 'ordinario', 'stato' => 'attivo', 'data_nascita' => '1980-01-01', 'luogo_nascita' => 'Orvieto (TR)',
            'email' => "socio{$n}@example.it", 'data_prima_iscrizione' => '2025-03-01',
        ])->save();

        return $s;
    }

    private function pacchetto(string $dal = '1201', string $al = '1230', array $extra = [])
    {
        return $this->actingAs($this->tesoriere)->post(route('tesseramento.pacchetti.store'), $extra + [
            'tipo_id' => $this->silver->id, 'acquistato_il' => '2027-01-10', 'prezzo_unitario' => '4,00',
            'dal' => $dal, 'al' => $al, 'movimento' => '1', 'pagato_il' => '2027-01-10', 'conto' => 'banca',
        ]);
    }

    private function quota(Socio $s, int $anno = 2027): void
    {
        $r = new Rinnovo();
        $r->forceFill(['socio_id' => $s->id, 'anno' => $anno, 'importo' => 70, 'categoria' => 'ordinario', 'data_pagamento' => "{$anno}-01-15",
            'metodo_pagamento' => 'bonifico', 'numero_ricevuta' => "5/{$anno}", 'anno_ricevuta' => $anno, 'progressivo' => 5])->save();
    }

    private function emetti(Socio $s, array $extra = [])
    {
        return $this->actingAs($this->tesoriere)->post(route('tesseramento.store'), $extra + [
            'socio_id' => $s->id, 'tipo_id' => $this->silver->id, 'numero' => '1201', 'data_rilascio' => '2027-02-15',
        ]);
    }

    // ----------------------------------------------------------------

    public function test_numeri_e_scadenza(): void
    {
        $this->assertSame(['prefisso' => '26B', 'progressivo' => 2749167, 'cifre' => 7], Tessere::leggi('26B2749167'));
        $this->assertSame(['prefisso' => '', 'progressivo' => 951, 'cifre' => 4], Tessere::leggi('0951'));
        $this->assertSame(['prefisso' => '27B', 'progressivo' => 1024417, 'cifre' => 7], Tessere::leggi(' 27b 1024417 '));
        $this->assertNull(Tessere::leggi('ABC'));
        $this->assertNull(Tessere::leggi('12A'));
        $this->assertSame('0951', Tessere::formatta('', 951, 4));
        $this->assertSame('2027-01-08', Tesseramento::scadenzaDa('2026-01-09')->toDateString());
        $this->assertSame('2029-02-27', Tesseramento::scadenzaDa('2028-02-29')->toDateString());   // niente 1° marzo
        $this->assertSame('2027-12-31', Tesseramento::scadenzaDa('2027-01-01')->toDateString());
    }

    public function test_permessi(): void
    {
        $this->actingAs($this->tesoriere)->get(route('tesseramento.index'))->assertOk()->assertSee('Nuovo pacchetto');
        $this->actingAs($this->segretario)->get(route('tesseramento.index'))->assertOk()->assertSee('Nuovo pacchetto');
        $this->actingAs($this->revisore)->get(route('tesseramento.index'))->assertOk()->assertDontSee('Nuovo pacchetto')->assertSee('Sola lettura');
        $this->actingAs($this->revisore)->get(route('tesseramento.pacchetti.create'))->assertForbidden();
        $this->actingAs($this->revisore)->post(route('tesseramento.store'), [])->assertForbidden();
        $this->actingAs($this->revisore)->get(route('tesseramento.carica'))->assertForbidden();
        // Voce di menu per il Tesoriere
        $this->actingAs($this->tesoriere)->get(route('dashboard'))->assertSee(route('tesseramento.index'));
    }

    public function test_pacchetto_con_uscita_in_prima_nota_e_sovrapposizione(): void
    {
        $this->pacchetto('1201', '1230', ['documenti' => [UploadedFile::fake()->create('ricevuta.pdf', 50, 'application/pdf')]])
            ->assertRedirect(route('tesseramento.index'))->assertSessionHas('success');
        $p = TesseraPacchetto::firstOrFail();
        $this->assertSame([1201, 1230, 4, 30], [$p->dal, $p->al, $p->cifre, $p->quantita()]);
        $this->assertSame('120.00', (string) $p->totale);
        $m = PrimaNota::findOrFail($p->prima_nota_id);
        $this->assertSame(['uscita', 'banca', '120.00', 'TESSERE_EPS'], [$m->tipo, $m->conto, (string) $m->importo, $m->categoria->codice]);
        $this->assertSame(1, $m->documenti()->count());
        $this->assertStringStartsWith('PN2027-', $m->documenti()->first()->nome);

        // Sovrapposizione (anche di un solo numero) rifiutata
        $this->pacchetto('1225', '1240')->assertSessionHas('error');
        $this->assertSame(1, TesseraPacchetto::count());
        // Ultimo numero minore del primo, prefissi diversi
        $this->pacchetto('1300', '1290')->assertSessionHasErrors('al');
        $this->pacchetto('26B100', '27B110')->assertSessionHasErrors('al');
        // Pagamento prima dell'avvio della contabilità (2027): niente uscita
        $this->pacchetto('0951', '0960', ['acquistato_il' => '2026-03-03', 'pagato_il' => '2026-03-03'])->assertSessionHas('error');
        $this->pacchetto('0951', '0960', ['acquistato_il' => '2026-03-03', 'movimento' => null])->assertSessionHas('success');
        $vecchio = TesseraPacchetto::where('dal', 951)->firstOrFail();
        $this->assertNull($vecchio->prima_nota_id);
        $this->assertSame('0951–0960', $vecchio->intervallo());
    }

    public function test_emissione_numero_proposto_e_controlli(): void
    {
        $this->pacchetto();
        $s = $this->socio();
        $this->quota($s);
        $this->assertSame('1201', Tessere::primoLibero($this->silver->id)['numero']);

        $this->emetti($s)->assertRedirect(route('tesseramento.index'))->assertSessionHas('success');
        $t = Tesseramento::firstOrFail();
        $this->assertSame(['1201', 1201, '2027-02-15', '2028-02-14', 'emessa', false], [$t->numero_tessera_csen, $t->progressivo,
            $t->data_rilascio->toDateString(), $t->scadenza->toDateString(), $t->origine, $t->senza_quota]);
        $this->assertSame('1202', Tessere::primoLibero($this->silver->id)['numero']);

        // Numero già usato, fuori dai pacchetti, tipo senza pacchetto
        $altro = $this->socio();
        $this->emetti($altro)->assertSessionHasErrors('numero', null, 'emissione');
        $this->emetti($altro, ['numero' => '1500'])->assertSessionHasErrors('numero', null, 'emissione');
        $gold = TesseraTipo::create(['nome' => 'Gold Base', 'prezzo' => 9]);
        $this->emetti($altro, ['numero' => '1205', 'tipo_id' => $gold->id])->assertSessionHasErrors('numero', null, 'emissione');
        // Un numero scelto a mano dentro il pacchetto va bene
        $this->emetti($altro, ['numero' => '1217', 'conferma' => '1'])->assertSessionHas('success');
        $this->assertSame('1202', Tessere::primoLibero($this->silver->id)['numero']);

        // Tessera ancora valida: la nuova non si sovrappone
        $this->emetti($s, ['numero' => '1203', 'data_rilascio' => '2027-03-01'])->assertSessionHasErrors('numero', null, 'emissione');
        $this->assertStringContainsString('15/02/2028', session('errors')->getBag('emissione')->first('numero'));
    }

    public function test_rinnovo_dopo_12_mesi_e_quota(): void
    {
        $this->pacchetto();
        $s = $this->socio();
        $vecchia = new Tesseramento();
        $vecchia->forceFill(['socio_id' => $s->id, 'stagione_sportiva' => 2026, 'numero_tessera_csen' => '0953', 'progressivo' => 953,
            'data_rilascio' => '2026-02-27', 'scadenza' => '2027-02-26', 'tipo_id' => $this->silver->id, 'origine' => 'caricata'])->save();

        $sit = Tessere::situazione($s->tesseramenti()->get());
        $this->assertSame(['in_scadenza', 11, 'Scade tra 11 giorni'], [$sit['stato'], $sit['giorni'], $sit['testo']]);
        $this->assertSame('2027-02-27', Tessere::dataProposta($sit['tessera'])->toDateString());

        // 15/02/2027 senza quota 2027: in ritardo, serve «Rinnova lo stesso»
        $q = Tessere::quota($s, Carbon::parse('2027-02-27'));
        $this->assertSame([false, true, 'warn'], [$q['versata'], $q['conferma'], $q['variante']]);
        $this->emetti($s, ['data_rilascio' => '2027-02-27'])->assertSessionHasErrors('numero', null, 'emissione')->assertSessionHas('serve_conferma', true);
        $this->assertStringContainsString('Rinnova lo stesso', session('errors')->getBag('emissione')->first('numero'));

        $this->emetti($s, ['data_rilascio' => '2027-02-27', 'conferma' => '1', 'portale' => '1'])->assertSessionHas('success');
        $nuova = Tesseramento::where('numero_tessera_csen', '1201')->firstOrFail();
        $this->assertSame($vecchia->id, $nuova->precedente_id);
        $this->assertTrue($nuova->senza_quota);
        $this->assertNotNull($nuova->portale_csen_il);
        // Rinnovata in anticipo: la vecchia copre ancora oggi, ma niente avviso
        $sit = Tessere::situazione($s->tesseramenti()->get());
        $this->assertSame('valida', $sit['stato']);
        $this->assertSame('rinnovata', $vecchia->fresh()->statoOggi());

        // Con la quota versata non serve la conferma
        $t = $this->socio();
        $this->quota($t);
        $q = Tessere::quota($t, Carbon::parse('2027-02-15'));
        $this->assertSame([true, false, 'Quota 2027 versata'], [$q['versata'], $q['conferma'], $q['titolo']]);
        // A gennaio, ancora nel termine: niente conferma
        Carbon::setTestNow('2027-01-20');
        $this->assertFalse(Tessere::quota($this->socio(), Carbon::parse('2027-01-20'))['conferma']);
        // Prima dell'avvio della contabilità: quota nel vecchio gestionale
        Carbon::setTestNow('2026-10-07');
        $this->assertSame(['vecchio', false], array_values(array_intersect_key(Tessere::quota($this->socio(), Carbon::parse('2026-10-07')), ['situazione' => 0, 'conferma' => 0])));
    }

    public function test_situazioni_e_pagine(): void
    {
        $this->pacchetto();
        $mai = $this->socio(['cognome' => 'Marini']);
        $scaduto = $this->socio(['cognome' => 'Vitali']);
        $t = new Tesseramento();
        $t->forceFill(['socio_id' => $scaduto->id, 'stagione_sportiva' => 2026, 'numero_tessera_csen' => '1204', 'progressivo' => 1204,
            'data_rilascio' => '2026-02-03', 'scadenza' => '2027-02-02', 'tipo_id' => $this->silver->id, 'pacchetto_id' => TesseraPacchetto::value('id')])->save();

        $sit = Tessere::perSoci([$mai->id, $scaduto->id]);
        $this->assertSame('mancante', $sit[$mai->id]['stato']);
        $this->assertSame(['scaduta', 'Tessera scaduta'], [$sit[$scaduto->id]['stato'], $sit[$scaduto->id]['testo']]);

        $this->actingAs($this->tesoriere)->get(route('tesseramento.index'))->assertOk()
            ->assertSee('Mai tesserato')->assertSee('scaduta il 02/02/2027')->assertSee('1201–1230')
            ->assertSee('2 soci senza tessera valida')->assertDontSee('Vitali1');   // il Tesoriere non vede la scheda: niente link
        $this->actingAs($this->presidente)->get(route('soci.index'))->assertOk()->assertSee('Tessera scaduta')->assertSee('Senza tessera');
        $this->actingAs($this->presidente)->get(route('soci.show', $scaduto))->assertOk()
            ->assertSee('Tessera CSEN: scaduta')->assertSee('Tessera scaduta il 02/02/2027.')->assertSee('emetti=' . $scaduto->id, false);
        $this->actingAs($this->presidente)->get(route('rinnovi.index', ['anno' => 2027]))->assertOk()->assertSee('Quota netta 2027');
        // Apertura della finestra dalla scheda
        $this->actingAs($this->presidente)->get(route('tesseramento.index', ['emetti' => $scaduto->id, 'da' => 'socio']))->assertOk()
            ->assertSee('apri(' . $scaduto->id . ')', false)->assertSee('name="da" value="socio"', false);
    }

    public function test_carico_dell_elenco_csen(): void
    {
        $a = $this->socio(['codice_fiscale' => 'RSSMRA80A01E783Q', 'cognome' => 'Rossi']);
        $b = $this->socio(['codice_fiscale' => 'BNCLRA90B41E783R', 'cognome' => 'Bianchi']);
        $this->pacchetto('26B2229040', '26B2229060', ['acquistato_il' => '2026-05-01', 'movimento' => null]);
        $csv = "ASSOCIAZIONE;COGNOME;NOME;SESSO;DATA DI NASCITA;LUOGO DI NASCITA;CODICE FISCALE;DATA DI INSERIMENTO;DATA DI RILASCIO;NUMERO TESSERA;DISCIPLINE;QUALIFICA;TIPO TESSERA;TELEFONO;E-MAIL;ISTRUTTORE\n"
            . "PROVA;ROSSI;MARIO;M;01/01/1980;Orvieto;RSSMRA80A01E783Q;09/01/2026;09/01/2026;26B2749167;DQ001, DQ004;Dirigente;Silver Base;333;a@b.it;\n"
            . "PROVA;ROSSI;MARIO;M;01/01/1980;Orvieto;rssmra80a01e783q;10/01/2027;10/01/2027;27B2229050;DQ001;Atleta Praticante;SILVER BASE;;;\n"
            . "PROVA;BIANCHI;LAURA;F;01/02/1990;Orvieto;BNCLRA90B41E783R;02/09/2026;02/09/2026;27B2229058;DQ001;Atleta Praticante;Gold Base;;;Paolo Zanetti\n"
            . "PROVA;VERDI;ANNA;F;01/02/1990;Orvieto;VRDNNA90B41E783X;02/09/2026;02/09/2026;27B2229059;DQ001;Atleta Praticante;Silver Base;;;\n"
            . "PROVA;NERI;LUCA;M;01/02/1990;Orvieto;RSSMRA80A01E783Q;31/02/2026;;27B2229060;DQ001;Atleta Praticante;Silver Base;;;\n";
        $file = UploadedFile::fake()->createWithContent('CSEN.csv', $csv);

        $this->actingAs($this->tesoriere)->post(route('tesseramento.carica.file'), ['file' => $file])->assertRedirect(route('tesseramento.carica'));
        $this->actingAs($this->tesoriere)->get(route('tesseramento.carica'))->assertOk()
            ->assertSee('Carica 3 tessere')->assertSee('Socio non trovato')->assertSee('Data di rilascio mancante')
            ->assertDontSee('333')->assertDontSee('a@b.it');   // telefono ed email non si leggono
        $this->actingAs($this->tesoriere)->post(route('tesseramento.carica.conferma'))->assertRedirect(route('tesseramento.index'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '3 tessere caricate') && str_contains($m, 'Gold Base'));

        $this->assertSame(3, Tesseramento::count());
        $this->assertSame([], Storage::disk('local')->files('tmp/tessere'));   // il CSV si cancella
        $prima = Tesseramento::where('numero_tessera_csen', '26B2749167')->firstOrFail();
        $this->assertSame(['2027-01-08', 'caricata', 'DQ001, DQ004'], [$prima->scadenza->toDateString(), $prima->origine, $prima->discipline]);
        $this->assertSame(['dirigente'], $prima->qualifiche->pluck('codice')->all());
        $this->assertNull($prima->pacchetto_id);
        $seconda = Tesseramento::where('numero_tessera_csen', '27B2229050')->firstOrFail();
        $this->assertSame($prima->id, $seconda->precedente_id);   // catena dei rinnovi
        $this->assertNotNull($seconda->pacchetto_id);              // collegata al pacchetto Silver 2229040–2229060
        $gold = TesseraTipo::where('nome', 'Gold Base')->firstOrFail();
        $this->assertNull($gold->prezzo);
        $this->assertNull(Tesseramento::where('numero_tessera_csen', '27B2229058')->value('pacchetto_id'));   // tipo diverso dal pacchetto
        $this->assertStringContainsString('Istruttore', Tesseramento::where('numero_tessera_csen', '27B2229058')->value('note'));
        // Una sola riga di audit per il carico
        $this->assertSame(1, AuditLog::where('evento', 'TESSERE_CARICATE')->count());
        $this->assertSame(0, AuditLog::where('evento', 'TESSERAMENTO_CREATED')->count());

        // Lo stesso file di nuovo: tutto già presente
        $this->actingAs($this->tesoriere)->post(route('tesseramento.carica.file'), ['file' => UploadedFile::fake()->createWithContent('CSEN.csv', $csv)]);
        $this->actingAs($this->tesoriere)->get(route('tesseramento.carica'))->assertOk()->assertSee('Già nel gestionale')->assertDontSee('Carica 3');
        // Un file che non è l'elenco del CSEN
        $this->actingAs($this->tesoriere)->post(route('tesseramento.carica.file'), ['file' => UploadedFile::fake()->createWithContent('x.csv', "a;b\n1;2\n")]);
        $this->actingAs($this->tesoriere)->get(route('tesseramento.carica'))->assertSee('mancano le colonne');
    }

    public function test_correzione_ed_eliminazione(): void
    {
        $this->pacchetto();
        $p = TesseraPacchetto::firstOrFail();
        $s = $this->socio();
        $this->quota($s);
        $this->emetti($s, ['numero' => '1210']);

        // Correzione: l'intervallo deve contenere le tessere emesse, con motivazione
        $dati = ['acquistato_il' => '2027-01-10', 'prezzo_unitario' => '4,50', 'dal' => '1211', 'al' => '1230', 'motivo' => 'Numeri stampati diversi dalla ricevuta'];
        $this->actingAs($this->tesoriere)->put(route('tesseramento.pacchetti.update', $p), $dati)->assertSessionHas('error');
        $this->actingAs($this->tesoriere)->put(route('tesseramento.pacchetti.update', $p), ['dal' => '1201', 'motivo' => ''] + $dati)->assertSessionHasErrors('motivo');
        $this->actingAs($this->tesoriere)->put(route('tesseramento.pacchetti.update', $p), ['dal' => '1201', 'al' => '1220'] + $dati)->assertSessionHas('success');
        $this->assertSame('90.00', (string) $p->fresh()->totale);
        $this->assertSame('90.00', (string) PrimaNota::find($p->prima_nota_id)->importo);   // il movimento segue
        $this->assertSame(1, AuditLog::where('evento', 'TESSERE_PACCHETTO_CORRETTO')->count());

        // Con tessere emesse non si elimina
        $this->actingAs($this->tesoriere)->delete(route('tesseramento.pacchetti.destroy', $p))->assertSessionHas('error');

        // Tessera eliminata con motivazione: il numero torna libero
        $t = Tesseramento::firstOrFail();
        $this->actingAs($this->tesoriere)->delete(route('tesseramento.destroy', $t), ['motivo' => 'corto'])->assertSessionHasErrors('motivo', null, 'elimina' . $t->id);
        $this->actingAs($this->tesoriere)->delete(route('tesseramento.destroy', $t), ['motivo' => 'Registrata sul socio sbagliato'])->assertSessionHas('success');
        $this->assertSame(0, Tesseramento::count());
        $riga = AuditLog::where('evento', 'TESSERA_ELIMINATA')->firstOrFail();
        $this->assertStringNotContainsString('1210', $riga->dettaglio);   // il numero sta nei valori, fuori dall'hash

        // Ora il pacchetto si elimina, con la sua uscita
        $movimento = $p->prima_nota_id;
        $this->actingAs($this->tesoriere)->delete(route('tesseramento.pacchetti.destroy', $p))->assertSessionHas('success');
        $this->assertNull(PrimaNota::find($movimento));
        $this->assertSame(0, TesseraPacchetto::count());
    }

    public function test_quota_suggerisce_la_tessera(): void
    {
        $s = $this->socio();
        $this->actingAs($this->tesoriere)->post(route('rinnovi.store'), [
            'socio_id' => $s->id, 'anno' => 2027, 'importo' => '70,00', 'data_pagamento' => '2027-02-15', 'metodo' => 'bonifico',
        ])->assertSessionHas('tessera_suggerita', fn ($v) => $v['id'] === $s->id);
        $this->actingAs($this->tesoriere)->get(route('rinnovi.index', ['anno' => 2027]))->assertSee('Emetti la tessera')->assertSee('emetti=' . $s->id, false);
    }

    public function test_tipi_di_tessera(): void
    {
        $this->actingAs($this->tesoriere)->post(route('tesseramento.tipi.store'), ['nome' => 'Gold Base', 'prezzo' => '8,50'])->assertSessionHas('success');
        $this->actingAs($this->tesoriere)->post(route('tesseramento.tipi.store'), ['nome' => 'Gold Base', 'prezzo' => '8,50'])->assertSessionHasErrors('nome', null, 'tiponuovo');
        $gold = TesseraTipo::where('nome', 'Gold Base')->firstOrFail();
        $this->assertSame('8.50', (string) $gold->prezzo);
        $this->actingAs($this->tesoriere)->put(route('tesseramento.tipi.update', $gold), ['nome' => 'Gold Base', 'prezzo' => '9'])->assertSessionHas('success');
        $this->assertFalse($gold->fresh()->attivo);   // spunta «In uso» tolta
        $this->actingAs($this->revisore)->post(route('tesseramento.tipi.store'), ['nome' => 'X', 'prezzo' => '1'])->assertForbidden();
    }

    public function test_revisione_numeri_gia_usati_e_prefisso(): void
    {
        $this->pacchetto('27B1024418', '27B1024447', ['acquistato_il' => '2026-09-20', 'movimento' => null]);
        $s = $this->socio();
        $this->quota($s);
        // Tessera caricata con un numero del pacchetto ma di un altro tipo: non si propone più
        $gold = TesseraTipo::create(['nome' => 'Gold Base']);
        $t = new Tesseramento();
        $t->forceFill(['socio_id' => $this->socio()->id, 'stagione_sportiva' => 2026, 'numero_tessera_csen' => '27B1024418', 'progressivo' => 1024418,
            'data_rilascio' => '2026-09-21', 'scadenza' => '2027-09-20', 'tipo_id' => $gold->id, 'origine' => 'caricata'])->save();
        $this->assertSame('27B1024419', Tessere::primoLibero($this->silver->id)['numero']);
        $this->assertSame(29, Tessere::disponibili(TesseraPacchetto::firstOrFail()));
        // Stesso progressivo con un prefisso diverso o senza prefisso: già usato
        $this->emetti($s, ['numero' => '1024418'])->assertSessionHasErrors('numero', null, 'emissione');
        // Prefisso diverso da quello del pacchetto: rifiutato
        $this->emetti($s, ['numero' => '26B1024420'])->assertSessionHasErrors('numero', null, 'emissione');
        $this->assertStringContainsString('prefisso 27B', session('errors')->getBag('emissione')->first('numero'));
        // Solo le cifre: il prefisso arriva dal pacchetto
        $this->emetti($s, ['numero' => '1024420'])->assertSessionHas('success');
        $this->assertTrue(Tesseramento::where('numero_tessera_csen', '27B1024420')->exists());
        // A gennaio, nel termine per la quota: non è «senza quota»
        Carbon::setTestNow('2027-01-20 10:00');
        $g = $this->socio();
        $this->emetti($g, ['numero' => '1024421', 'data_rilascio' => '2027-01-20'])->assertSessionHas('success');
        $this->assertFalse(Tesseramento::where('socio_id', $g->id)->firstOrFail()->senza_quota);
    }
}
