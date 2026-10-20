<?php

namespace Tests\Feature;

use App\Mail\ConsultazioneEsito;
use App\Mail\ConsultazioneInvito;
use App\Mail\ConvocazioneSeduta;
use App\Models\Consultazione;
use App\Models\ConsultazioneVoto;
use App\Models\GovernanceCd;
use App\Models\Seduta;
use App\Models\Socio;
use App\Models\User;
use App\Models\Verbale;
use App\Services\Votazioni;
use App\Support\HtmlSanitizer;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
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
 * Verbali e Consiglio Direttivo, v0.44.0 (specifiche/verbali.md,
 * analisi-verbali-assemblee.md, tavole VB-1…VB-5, CD-5, CT-1…CT-3).
 */
class VerbaliTest extends TestCase
{
    use RefreshDatabase;

    /** PDF con una firma PAdES (un dizionario /Sig con /ByteRange). */
    private const UNA_FIRMA = "%PDF-1.7\n1 0 obj << /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Paolo Zanetti) /ByteRange [0 120 2000 300] /Contents <3082> >> endobj\n%%EOF";

    /** PDF con due firme PAdES. */
    private const DUE_FIRME = "%PDF-1.7\n1 0 obj << /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Andrea Papini) /ByteRange [0 120 2000 300] /Contents <3082> >> endobj\n"
        . "2 0 obj << /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /Name (Paolo Zanetti) /ByteRange [0 220 3000 300] /Contents <3082> >> endobj\n%%EOF";

    private User $presidente;

    private User $segretario;

    private User $vice;

    private User $consigliere;

    private User $revisore;

    /** @var array<string, Socio> */
    private array $soci = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2027-03-15 10:00:00');
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class, ProfiloEnteSeeder::class]);
        Mail::fake();
        Storage::fake('local');

        foreach ([
            ['Papini', 'Andrea', 'PPNNDR70A01C100X', 'presidente', 'Presidente'],
            ['Zanetti', 'Paolo', 'ZNTPLA60A01C100X', 'segretario_tesoriere', 'Segretario e Tesoriere'],
            ['Rossi', 'Mario', 'RSSMRA70A01C100X', 'vicepresidente', 'Vicepresidente'],
            ['Tascio', 'Luigi', 'TSCLGU70A01C100X', 'consigliere', 'Consigliere'],
        ] as [$cognome, $nome, $cf, $codice, $etichetta]) {
            $s = new Socio();
            $s->forceFill(['cognome' => $cognome, 'nome' => $nome, 'codice_fiscale' => $cf, 'tipo' => 'ordinario', 'email' => strtolower($cognome) . '@example.it',
                'stato' => 'attivo', 'data_nascita' => '1970-01-01', 'data_prima_iscrizione' => '2024-07-24'])->save();
            (new GovernanceCd())->forceFill(['socio_id' => $s->id, 'carica' => $etichetta, 'carica_codice' => $codice,
                'data_inizio_mandato' => '2026-11-16', 'in_carica' => true])->save();
            $this->soci[$cognome] = $s;
        }
        $this->presidente = User::factory()->conRuoli('presidente')->create(['name' => 'Andrea Papini', 'email' => 'presidente@example.it', 'attivo' => true, 'socio_id' => $this->soci['Papini']->id]);
        $this->segretario = User::factory()->conRuoli('segretario', 'tesoriere')->create(['name' => 'Paolo Zanetti', 'email' => 'segretario@example.it', 'attivo' => true, 'socio_id' => $this->soci['Zanetti']->id]);
        $this->vice = User::factory()->conRuoli('vicepresidente')->create(['name' => 'Mario Rossi', 'email' => 'vice@example.it', 'attivo' => true, 'socio_id' => $this->soci['Rossi']->id]);
        $this->consigliere = User::factory()->conRuoli('consigliere')->create(['name' => 'Luigi Tascio', 'email' => 'consigliere@example.it', 'attivo' => true, 'socio_id' => $this->soci['Tascio']->id]);
        $this->revisore = User::factory()->conRuoli('revisore')->create(['name' => 'Rita Revisore', 'email' => 'revisore@example.it', 'attivo' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ----------------------------------------------------------------
    // Aiuti
    // ----------------------------------------------------------------

    private function datiRiunione(array $extra = []): array
    {
        return $extra + [
            'data' => '2027-03-19', 'ora_inizio' => '21:00', 'luogo' => 'Sede della Compagnia', 'videoconferenza' => '0',
            'punti' => ['Acquisto dei paglioni', 'Varie ed eventuali'], 'urgente' => '0',
        ];
    }

    private function riunioneSvolta(): Verbale
    {
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.store'), $this->datiRiunione())->assertRedirect();
        $s = Seduta::firstOrFail();
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.convoca', $s))->assertRedirect();
        Carbon::setTestNow('2027-03-19 21:00:00');
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.presenze', $s), [
            'presenze' => [
                $this->soci['Papini']->id => ['presenza' => 'sede'],
                $this->soci['Zanetti']->id => ['presenza' => 'sede'],
                $this->soci['Rossi']->id => ['presenza' => 'video'],
                $this->soci['Tascio']->id => ['presenza' => 'sede'],
            ],
            'presidente_socio_id' => $this->soci['Papini']->id,
            'segretario_socio_id' => $this->soci['Zanetti']->id,
        ])->assertRedirect();

        return Verbale::where('tipo', 'cd_riunione')->firstOrFail();
    }

    /** @param array<string, string> $voti cognome => voto */
    private function salvaPunto(Verbale $v, array $voti, string $tipo = 'ordinaria'): void
    {
        $p = $v->punti()->first();
        $altri = $v->punti()->where('id', '!=', $p->id)->pluck('id')->mapWithKeys(fn ($id) => [$id => ['discussione_html' => '<p>Nessun intervento.</p>']])->all();
        $this->actingAs($this->segretario)->put(route('verbali.salva', $v), [
            'punti' => $altri + [$p->id => [
                'discussione_html' => '<p>Il Presidente illustra il preventivo.</p><script>alert(1)</script>',
                'ha_delibera' => '1', 'tipo_delibera' => $tipo,
                'delibera_html' => '<p>Il Consiglio Direttivo delibera di acquistare sei paglioni.</p>',
                'voti' => collect($voti)->mapWithKeys(fn ($voto, $cognome) => [$this->soci[$cognome]->id => $voto])->all(),
            ]],
            'ora_chiusura' => '22:30',
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function file(string $nome, string $contenuto): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, $contenuto);
    }

    // ----------------------------------------------------------------
    // Regola delle votazioni
    // ----------------------------------------------------------------

    public function test_regola_delle_votazioni(): void
    {
        // Più favorevoli che contrari; gli astenuti non entrano nel conto (V17)
        $this->assertSame('approvata', Votazioni::esito('ordinaria', ['favorevole' => 2, 'contrario' => 1, 'astenuto' => 2], null, 5)['esito']);
        // Parità: prevale il Presidente se ha votato
        $this->assertSame('approvata', Votazioni::esito('ordinaria', ['favorevole' => 2, 'contrario' => 2], 'favorevole', 5)['esito']);
        $this->assertSame('respinta', Votazioni::esito('ordinaria', ['favorevole' => 2, 'contrario' => 2], 'contrario', 5)['esito']);
        $this->assertSame('respinta', Votazioni::esito('ordinaria', ['favorevole' => 1, 'contrario' => 1, 'astenuto' => 1], 'astenuto', 3)['esito']);
        // Nessun favorevole o contrario: non passa
        $this->assertSame('respinta', Votazioni::esito('ordinaria', ['astenuto' => 3], null, 3)['esito']);
        // Esclusione: maggioranza assoluta dei componenti (art. 9.1 c)
        $this->assertSame('respinta', Votazioni::esito('esclusione', ['favorevole' => 2, 'astenuto' => 2], 'favorevole', 5)['esito']);
        $r = Votazioni::esito('esclusione', ['favorevole' => 3, 'contrario' => 2], null, 5);
        $this->assertSame('approvata', $r['esito']);
        $this->assertStringContainsString('art. 9.1 c', $r['testo']);
    }

    // ----------------------------------------------------------------
    // Riunione del CD (CD-5)
    // ----------------------------------------------------------------

    public function test_convocazione_con_i_termini(): void
    {
        // Meno di 3 giorni: solo urgente, con il motivo; meno di 24 ore: mai
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.store'), $this->datiRiunione(['data' => '2027-03-16']))->assertRedirect();
        $s = Seduta::firstOrFail();
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.convoca', $s))->assertSessionHasErrors('convoca');
        $this->actingAs($this->segretario)->put(route('verbali.riunioni.update', $s), $this->datiRiunione(['data' => '2027-03-16', 'urgente' => '1']))->assertSessionHasErrors('motivo_urgenza');
        $this->actingAs($this->segretario)->put(route('verbali.riunioni.update', $s), $this->datiRiunione(['data' => '2027-03-16', 'urgente' => '1', 'motivo_urgenza' => 'Scadenza del bando regionale']))->assertRedirect();
        $this->actingAs($this->segretario)->put(route('verbali.riunioni.update', $s), $this->datiRiunione(['data' => '2027-03-15', 'ora_inizio' => '21:00', 'urgente' => '1', 'motivo_urgenza' => 'Scadenza del bando']))->assertRedirect();
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.convoca', $s))->assertSessionHasErrors('convoca');
        $this->actingAs($this->segretario)->put(route('verbali.riunioni.update', $s), $this->datiRiunione(['data' => '2027-03-16', 'ora_inizio' => '21:00', 'urgente' => '1', 'motivo_urgenza' => 'Scadenza del bando']))->assertRedirect();

        // La prova va solo a chi la manda
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.prova', $s))->assertRedirect();
        Mail::assertSent(ConvocazioneSeduta::class, 1);
        $this->assertSame('bozza', $s->fresh()->stato);

        $this->actingAs($this->segretario)->post(route('verbali.riunioni.convoca', $s))->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertSent(ConvocazioneSeduta::class, 5);
        $s->refresh();
        $this->assertSame('convocata', $s->stato);
        $this->assertSame(4, (int) $s->convocati);

        // Dopo la convocazione non si modifica: si rinvia e si riconvoca
        $this->actingAs($this->segretario)->put(route('verbali.riunioni.update', $s), $this->datiRiunione())->assertSessionHasErrors('data');
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.rinvia', $s), ['motivo_rinvio' => 'Manca il preventivo', 'avvisa' => '1'])->assertRedirect();
        $this->assertSame('rinviata', $s->fresh()->stato);
        Mail::assertSent(ConvocazioneSeduta::class, 9);
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.riconvoca', $s))->assertRedirect(route('verbali.riunioni.edit', $s));
        $this->assertSame('bozza', $s->fresh()->stato);

        // Il consigliere consulta, non scrive
        $this->actingAs($this->consigliere)->get(route('verbali.riunioni.show', $s))->assertOk()->assertSee('Acquisto dei paglioni');
        $this->actingAs($this->consigliere)->post(route('verbali.riunioni.convoca', $s))->assertForbidden();
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'SEDUTA_RINVIATA')->exists());
    }

    public function test_riunione_presenze_verbale_e_definitivo(): void
    {
        $v = $this->riunioneSvolta();
        $this->assertSame('bozza', $v->stato);
        $this->assertSame(['Acquisto dei paglioni', 'Varie ed eventuali'], $v->punti()->pluck('titolo')->all());
        $this->assertSame('Andrea Papini', $v->presidente_assemblea);

        // Mancano i voti: non si rende definitivo
        $this->actingAs($this->segretario)->post(route('verbali.definitivo', $v))->assertSessionHasErrors('verbale');

        // Parità con il voto del Presidente; l'astenuto non entra nel conto
        $this->salvaPunto($v, ['Papini' => 'favorevole', 'Zanetti' => 'contrario', 'Rossi' => 'astenuto', 'Tascio' => 'non_partecipa']);
        $p = $v->punti()->first();
        $this->assertSame('approvata', $p->esito);
        $this->assertStringContainsString('voto del Presidente', $p->esito_testo);
        $this->assertStringNotContainsString('<script', (string) $p->discussione_html);
        $this->actingAs($this->consigliere)->get(route('verbali.show', $v))->assertOk()->assertSee('Approvata con il voto del Presidente');

        // Definitivo: numero 2027/1, PDF salvato con l'impronta
        $this->actingAs($this->segretario)->post(route('verbali.definitivo', $v))->assertRedirect(route('verbali.show', $v));
        $v->refresh();
        $this->assertSame('definitivo', $v->stato);
        $this->assertSame('2027/1', $v->numeroTesto());
        Storage::disk('local')->assertExists($v->pdf_path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($v->pdf_path)), $v->pdf_impronta);
        $this->assertSame('HIGH', DB::table('gl_audit_log')->where('evento', 'VERBALE_DEFINITIVO')->value('livello_rischio'));

        // Da qui non cambia più
        $this->actingAs($this->segretario)->put(route('verbali.salva', $v), ['punti' => []])->assertSessionHasErrors('verbale');
        $this->actingAs($this->segretario)->get(route('verbali.scrivi', $v))->assertRedirect(route('verbali.show', $v));
        $this->actingAs($this->revisore)->get(route('verbali.pdf', $v))->assertOk();
    }

    public function test_cd_non_costituito_non_delibera(): void
    {
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.store'), $this->datiRiunione())->assertRedirect();
        $s = Seduta::firstOrFail();
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.convoca', $s));
        // Prima del giorno della riunione le presenze non si registrano
        $presenze = [
            $this->soci['Papini']->id => ['presenza' => 'sede'],
            $this->soci['Zanetti']->id => ['presenza' => 'sede'],
            $this->soci['Rossi']->id => ['presenza' => 'assente', 'giustificato' => '1'],
            $this->soci['Tascio']->id => ['presenza' => 'assente'],
        ];
        $dati = ['presenze' => $presenze, 'presidente_socio_id' => $this->soci['Papini']->id, 'segretario_socio_id' => $this->soci['Zanetti']->id];
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.presenze', $s), $dati)->assertSessionHasErrors('presenze');
        Carbon::setTestNow('2027-03-19 21:00:00');
        // Il segretario dev'essere presente
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.presenze', $s), ['segretario_socio_id' => $this->soci['Tascio']->id] + $dati)->assertSessionHasErrors('segretario_socio_id');
        $this->actingAs($this->segretario)->post(route('verbali.riunioni.presenze', $s), $dati)->assertRedirect();

        // 2 presenti su 4: non costituito (servono 3), non si delibera
        $v = Verbale::firstOrFail();
        $p = $v->punti()->first();
        $this->actingAs($this->segretario)->put(route('verbali.salva', $v), ['punti' => [$p->id => ['ha_delibera' => '1', 'delibera_html' => '<p>Delibera</p>']]])
            ->assertSessionHasErrors("punti.{$p->id}.ha_delibera");
        $this->actingAs($this->segretario)->get(route('verbali.scrivi', $v))->assertOk()->assertSee('Il Consiglio non è validamente costituito.');
    }

    public function test_esclusione_maggioranza_dei_componenti(): void
    {
        $v = $this->riunioneSvolta();
        // 2 favorevoli su 4 componenti: non basta (servono 3)
        $this->salvaPunto($v, ['Papini' => 'favorevole', 'Zanetti' => 'favorevole', 'Rossi' => 'astenuto', 'Tascio' => 'astenuto'], 'esclusione');
        $this->assertSame('respinta', $v->punti()->first()->esito);
        $this->salvaPunto($v, ['Papini' => 'favorevole', 'Zanetti' => 'favorevole', 'Rossi' => 'favorevole', 'Tascio' => 'contrario'], 'esclusione');
        $this->assertSame('approvata', $v->punti()->first()->esito);
    }

    // ----------------------------------------------------------------
    // Copia firmata e rettifica (VB-3)
    // ----------------------------------------------------------------

    public function test_copia_firmata_e_rettifica(): void
    {
        $v = $this->riunioneSvolta();
        $this->salvaPunto($v, ['Papini' => 'favorevole', 'Zanetti' => 'favorevole', 'Rossi' => 'favorevole', 'Tascio' => 'favorevole']);
        $this->actingAs($this->segretario)->post(route('verbali.definitivo', $v));

        // Senza firma digitale: rifiutato (V2)
        $this->actingAs($this->segretario)->post(route('verbali.firmato', $v), ['firmato' => $this->file('scansione.pdf', "%PDF-1.4\nscansione\n%%EOF")])
            ->assertSessionHasErrorsIn('firmato', 'firmato');
        // Una firma sola: solo se del verbalizzante, dichiarato (V14)
        $this->actingAs($this->segretario)->post(route('verbali.firmato', $v), ['firmato' => $this->file('firmato.pdf', self::UNA_FIRMA)])
            ->assertSessionHasErrorsIn('firmato', 'dichiarazione');
        $this->actingAs($this->segretario)->post(route('verbali.firmato', $v), ['firmato' => $this->file('firmato.pdf', self::UNA_FIRMA), 'dichiarazione' => 'entrambi'])
            ->assertSessionHasErrorsIn('firmato', 'dichiarazione');
        $this->actingAs($this->segretario)->post(route('verbali.firmato', $v), ['firmato' => $this->file('firmato.pdf', self::UNA_FIRMA), 'dichiarazione' => 'segretario'])
            ->assertRedirect(route('verbali.show', $v));
        $v->refresh();
        $this->assertSame('firmato', $v->stato);
        $this->assertTrue($v->mancaFirmaPresidente());
        $this->assertSame('Paolo Zanetti', $v->firmatari);
        $this->actingAs($this->consigliere)->get(route('verbali.show', $v))->assertSee('Manca la firma del presidente della seduta.');

        // Sostituzione con due firme: serve il motivo
        $this->actingAs($this->segretario)->post(route('verbali.firmato', $v), ['firmato' => $this->file('firmato2.pdf', self::DUE_FIRME)])
            ->assertSessionHasErrorsIn('firmato', 'motivo');
        $this->actingAs($this->segretario)->post(route('verbali.firmato', $v), ['firmato' => $this->file('firmato2.pdf', self::DUE_FIRME), 'motivo' => 'Aggiunta la firma del Presidente'])
            ->assertRedirect();
        $v->refresh();
        $this->assertFalse($v->mancaFirmaPresidente());
        $this->assertSame(2, (int) $v->firme_numero);
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'VERBALE_FIRMATO_SOSTITUITO')->where('livello_rischio', 'HIGH')->exists());

        // Rettifica: in fondo, con il suo PDF; il PDF del verbale non cambia
        $impronta = $v->pdf_impronta;
        $this->actingAs($this->segretario)->post(route('verbali.rettifiche.store', $v), ['testo_html' => '<p>Al punto 1 leggasi «sette paglioni».</p>', 'motivo' => 'breve'])
            ->assertSessionHasErrorsIn('rettifica', 'motivo');
        $this->actingAs($this->segretario)->post(route('verbali.rettifiche.store', $v), ['testo_html' => '<p>Al punto 1 leggasi «sette paglioni».</p>', 'motivo' => 'Errore di trascrizione del numero'])
            ->assertRedirect();
        $r = $v->rettifiche()->firstOrFail();
        $this->assertSame(1, (int) $r->numero);
        Storage::disk('local')->assertExists($r->pdf_path);
        $this->assertSame($impronta, $v->fresh()->pdf_impronta);
        $this->actingAs($this->segretario)->post(route('verbali.rettifiche.firmata', [$v, $r]), ['firmato_rettifica' => $this->file('rett.pdf', self::UNA_FIRMA)])->assertRedirect();
        $this->assertNotNull($r->fresh()->firmato_path);
    }

    // ----------------------------------------------------------------
    // Consultazione telematica (CT-1…CT-3)
    // ----------------------------------------------------------------

    private function bozzaConsultazione(string $termine = '2027-03-19 21:00'): Consultazione
    {
        $this->actingAs($this->segretario)->post(route('verbali.consultazioni.store'), [
            'oggetto' => 'Acquisto di 6 paglioni', 'testo_html' => '<p>Il Consiglio delibera di acquistare 6 paglioni.</p>', 'termine' => $termine,
            'documenti' => [UploadedFile::fake()->create('preventivo.pdf', 100, 'application/pdf')],
        ])->assertRedirect();

        return Consultazione::latest('id')->firstOrFail();
    }

    /** @return array<string, string> cognome => token */
    private function avvia(Consultazione $c, ?User $chi = null, array $dati = []): array
    {
        $token = [];
        Mail::assertNothingSent();
        $this->actingAs($chi ?? $this->presidente)->post(route('verbali.consultazioni.avvia', $c), $dati)->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertSent(ConsultazioneInvito::class, function (ConsultazioneInvito $m) use (&$token) {
            $html = $m->render();
            preg_match('#/consultazione/([A-Za-z0-9]{64})#', $html, $x);
            $token[$m->voto->socio->cognome] = $x[1];

            return true;
        });

        return $token;
    }

    public function test_consultazione_avvio_voto_e_verbale(): void
    {
        $c = $this->bozzaConsultazione();
        $this->assertSame('bozza', $c->stato);
        $this->assertCount(1, $c->documenti());

        // La avvia solo il Presidente; il Vicepresidente solo in sostituzione (V16)
        $this->actingAs($this->segretario)->post(route('verbali.consultazioni.avvia', $c))->assertSessionHasErrors('avvia');
        $this->actingAs($this->vice)->post(route('verbali.consultazioni.avvia', $c))->assertSessionHasErrors('avvia');
        $token = $this->avvia($c, $this->vice, ['in_sostituzione' => '1']);
        $c->refresh();
        $this->assertSame('in_corso', $c->stato);
        $this->assertTrue($c->in_sostituzione);
        $this->assertSame(4, (int) $c->componenti);
        $this->assertCount(4, $token);
        $this->assertNull(ConsultazioneVoto::first()->getAttributes()['voto']);
        $this->assertSame(64, strlen(ConsultazioneVoto::first()->token_hash));

        // Pagina pubblica: proposta e documenti dal link
        $this->get(route('consultazione.mostra', $token['Papini']))->assertOk()->assertSee('Acquisto di 6 paglioni')->assertSee('preventivo.pdf');
        $doc = $c->documenti()->first();
        $this->get(route('consultazione.documento', [$token['Papini'], $doc->id]))->assertOk();
        $this->get(route('consultazione.mostra', str_repeat('x', 64)))->assertOk()->assertSee('Link non valido');

        // Voti: parità con il Presidente favorevole; l'astenuto conta per la validità
        $this->post(route('consultazione.vota', $token['Papini']), ['voto' => 'favorevole'])->assertRedirect(route('consultazione.mostra', $token['Papini']));
        $this->post(route('consultazione.vota', $token['Papini']), ['voto' => 'contrario'])->assertRedirect();
        $this->assertSame('favorevole', ConsultazioneVoto::where('socio_id', $this->soci['Papini']->id)->value('voto'));
        $this->post(route('consultazione.vota', $token['Zanetti']), ['voto' => 'contrario', 'nota' => 'Costano troppo']);
        $this->post(route('consultazione.vota', $token['Rossi']), ['voto' => 'astenuto']);
        $this->actingAs($this->consigliere)->get(route('verbali.consultazioni.show', $c))->assertOk()->assertSee('Se si chiudesse adesso:');
        $this->post(route('consultazione.vota', $token['Tascio']), ['voto' => 'astenuto']);

        // Hanno votato tutti: si chiude, nasce il verbale in bozza nel libro del CD
        $c->refresh();
        $this->assertSame('chiusa', $c->stato);
        $this->assertSame('approvata', $c->esito);
        $this->assertStringContainsString('voto del Presidente', $c->esito_testo);
        $v = $c->verbale;
        $this->assertSame('cd_consultazione', $v->tipo);
        $this->assertSame('cd', $v->libro);
        $this->assertSame('Andrea Papini', $v->presidente_assemblea);
        $this->assertCount(1, $v->documenti());
        Mail::assertSent(ConsultazioneEsito::class, 4);
        $this->get(route('consultazione.mostra', $token['Tascio']))->assertSee('Voto registrato');

        $this->actingAs($this->segretario)->post(route('verbali.definitivo', $v))->assertRedirect();
        $this->assertSame('2027/1', $v->fresh()->numeroTesto());
        $this->actingAs($this->segretario)->get(route('verbali.show', $v))->assertOk()->assertSee('link personale');
    }

    public function test_consultazione_termine_riunione_e_scadenza(): void
    {
        // Termine meno di 3 giorni dopo l'avvio: non parte
        $c = $this->bozzaConsultazione('2027-03-17 21:00');
        $this->actingAs($this->presidente)->post(route('verbali.consultazioni.avvia', $c))->assertSessionHasErrors('avvia');

        // Un terzo dei componenti chiede la riunione: si ferma
        $c2 = $this->bozzaConsultazione();
        $token = $this->avvia($c2);
        $this->post(route('consultazione.vota', $token['Tascio']), ['voto' => 'riunione']);
        $this->assertSame('in_corso', $c2->fresh()->stato);   // 1 su 4: ne servono 2
        $this->post(route('consultazione.vota', $token['Rossi']), ['voto' => 'riunione']);
        $this->assertSame('riunione', $c2->fresh()->esito);

        // Alla scadenza con pochi votanti: non valida
        Mail::fake();
        $c3 = $this->bozzaConsultazione();
        $token = $this->avvia($c3);
        $this->post(route('consultazione.vota', $token['Papini']), ['voto' => 'favorevole']);
        Carbon::setTestNow('2027-03-19 21:05:00');
        $this->artisan('consultazioni:scadenze')->assertSuccessful();
        $c3->refresh();
        $this->assertSame('chiusa', $c3->stato);
        $this->assertSame('non_valida', $c3->esito);
        $this->get(route('consultazione.mostra', $token['Zanetti']))->assertSee('Consultazione chiusa');
        $this->post(route('consultazione.vota', $token['Zanetti']), ['voto' => 'favorevole']);
        $this->assertNull(ConsultazioneVoto::where('consultazione_id', $c3->id)->where('socio_id', $this->soci['Zanetti']->id)->value('voto'));
    }

    public function test_consultazione_ritiro_e_sollecito(): void
    {
        $c = $this->bozzaConsultazione();
        $this->avvia($c);
        $this->actingAs($this->segretario)->post(route('verbali.consultazioni.sollecita', $c))->assertRedirect();
        Mail::assertSent(ConsultazioneInvito::class, 8);
        $this->actingAs($this->segretario)->post(route('verbali.consultazioni.ritira', $c), ['motivo_ritiro' => 'Il fornitore ha ritirato il preventivo'])
            ->assertSessionHasErrorsIn('ritiro', 'motivo_ritiro');
        $this->actingAs($this->presidente)->post(route('verbali.consultazioni.ritira', $c), ['motivo_ritiro' => 'Il fornitore ha ritirato il preventivo'])->assertRedirect();
        $c->refresh();
        $this->assertSame('ritirata', $c->stato);
        $this->assertNull($c->verbale_id);
        $this->assertSame('HIGH', DB::table('gl_audit_log')->where('evento', 'CONSULTAZIONE_RITIRATA')->value('livello_rischio'));
    }

    // ----------------------------------------------------------------
    // Registrato da fuori (VB-5) e chiusura dell'anno
    // ----------------------------------------------------------------

    public function test_verbale_registrato_da_fuori(): void
    {
        $dati = [
            'libro' => 'assemblee', 'organo' => 'ordinaria', 'data' => '2027-03-10', 'presidente' => 'Andrea Papini', 'segretario' => 'Paolo Zanetti',
            'presenti' => 18, 'titolo' => 'Assemblea ordinaria del 10 marzo 2027', 'delibere_html' => '<ul><li>Approvato il rendiconto 2026</li></ul>',
            'approva_rendiconto' => '1', 'approva_rendiconto_anno' => 2026,
        ];
        $this->actingAs($this->consigliere)->get(route('verbali.registra'))->assertForbidden();
        $this->actingAs($this->segretario)->get(route('verbali.registra', ['libro' => 'assemblee']))->assertOk();
        // Senza firma digitale no (V15)
        $this->actingAs($this->segretario)->post(route('verbali.registra.store'), $dati + ['firmato' => $this->file('v.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHasErrors('firmato');
        $this->actingAs($this->segretario)->post(route('verbali.registra.store'), $dati + ['firmato' => $this->file('v.pdf', self::DUE_FIRME), 'numero' => 3])->assertRedirect();
        $v = Verbale::firstOrFail();
        $this->assertSame('firmato', $v->stato);
        $this->assertTrue($v->da_fuori);
        $this->assertSame('2027/3', $v->numeroTesto());
        $this->assertSame(2026, (int) $v->approva_rendiconto_anno);
        // Lo stesso numero non si riusa
        $this->actingAs($this->segretario)->post(route('verbali.registra.store'), $dati + ['firmato' => $this->file('v.pdf', self::DUE_FIRME), 'numero' => 3])->assertSessionHasErrors('numero');
        // Proposto il primo libero dopo il più alto
        $this->assertSame(4, \App\Services\Verbali::prossimoNumero('assemblee', 2027));

        // La chiusura dell'anno 2026 lo propone
        $this->assertTrue(app(\App\Services\AnniContabili::class)->verbaliApprovazione(2026)->contains('id', $v->id));
        $this->assertFalse(app(\App\Services\AnniContabili::class)->verbaliApprovazione(2025)->contains('id', $v->id));
        $this->actingAs($this->revisore)->get(route('verbali.index', ['libro' => 'assemblee']))->assertOk()->assertSee('Assemblea ordinaria del 10 marzo 2027');
    }

    private function domandaChiusa(string $cognome, string $cf, string $chiusa, bool $prova = false): \App\Models\Domanda
    {
        $d = new \App\Models\Domanda();
        $d->forceFill([
            'token' => \App\Models\Domanda::hashToken(bin2hex(random_bytes(16))),
            'cognome' => $cognome, 'nome' => 'Luca', 'sesso' => 'M', 'data_nascita' => '1990-05-10', 'luogo_nascita' => 'Terni (TR)',
            'codice_fiscale' => $cf, 'email' => strtolower($cognome) . '@esempio.it',
            'indirizzo' => 'Via Garibaldi 5', 'cap' => '05014', 'citta' => 'Castel Viscardo', 'provincia' => 'TR',
            'tipo_socio' => 'ordinario', 'documento_tipo' => 'carta_identita', 'documento_numero' => 'CA98765ZZ',
            'ip_address' => '127.0.0.1', 'informativa_versione' => '1.0', 'statuto_versione' => '1.0',
            'consenso_privacy' => true, 'consenso_arbitrato' => true, 'consenso_extra_ue' => false, 'consenso_foto_video' => false,
            'stato' => 'approvata', 'token_conferma' => \App\Models\Domanda::hashToken(bin2hex(random_bytes(32))),
            'scadenza_cd' => $chiusa, 'votazione_chiusa_il' => $chiusa, 'cartacea' => false, 'is_test' => $prova,
        ])->save();
        // Verbale nato prima della v0.44.0: senza i nomi di chi firma
        $v = new Verbale(['tipo' => 'cd_ammissione', 'domanda_id' => $d->id, 'titolo' => 'Domanda di ' . $cognome, 'data_seduta' => substr($chiusa, 0, 10), 'corpo_html' => '<p>Esito</p>']);
        $v->forceFill(['stato' => 'bozza'])->save();

        return $d;
    }

    public function test_verbali_delle_domande_definitivi_insieme(): void
    {
        $this->domandaChiusa('Verdi', 'VRDLCU90E10L117B', '2027-03-02 10:00:00');
        $this->domandaChiusa('Neri', 'NRELCU90E10L117B', '2027-03-01 10:00:00');
        $this->domandaChiusa('Prova', 'PRVLCU90E10L117B', '2027-03-03 10:00:00', true);
        $this->actingAs($this->segretario)->get(route('verbali.index'))->assertOk()->assertSee('2 verbali di domande sono chiusi.')->assertDontSee('Domanda di Prova');
        $this->actingAs($this->segretario)->post(route('verbali.ammissioni.definitivi'))->assertRedirect();
        // Nell'ordine di chiusura; la domanda di prova resta fuori dal libro
        $this->assertSame('2027/1', Verbale::where('titolo', 'Domanda di Neri')->first()->numeroTesto());
        $v = Verbale::where('titolo', 'Domanda di Verdi')->first();
        $this->assertSame('2027/2', $v->numeroTesto());
        $this->assertSame('Andrea Papini', $v->presidente_assemblea);
        $this->assertSame('Paolo Zanetti', $v->segretario_verbalizzante);
        $this->assertSame('bozza', Verbale::where('titolo', 'Domanda di Prova')->first()->stato);
    }

    public function test_html_ripulito(): void
    {
        $sporco = '<p onclick="x()">Testo <a href="javascript:alert(1)">link</a> <a href="https://example.it">buono</a></p><iframe src="x"></iframe><script>alert(1)</script><table><tr><td colspan="2">cella</td></tr></table>';
        $pulito = HtmlSanitizer::pulisci($sporco);
        $this->assertStringNotContainsString('onclick', $pulito);
        $this->assertStringNotContainsString('javascript:', $pulito);
        $this->assertStringNotContainsString('<iframe', $pulito);
        $this->assertStringNotContainsString('<script', $pulito);
        $this->assertStringContainsString('https://example.it', $pulito);
        $this->assertStringContainsString('colspan="2"', $pulito);
        $this->assertTrue(HtmlSanitizer::vuoto('<p><br></p>'));
    }

    public function test_menu_e_pagine(): void
    {
        $this->actingAs($this->consigliere)->get(route('verbali.index'))->assertOk()->assertSee('Libri dei')->assertDontSee('Riunione del CD</a>', false);
        $this->actingAs($this->segretario)->get(route('verbali.index'))->assertOk()->assertSee('Riunione del CD');
        $this->actingAs($this->segretario)->get(route('verbali.riunioni.create'))->assertOk();
        $this->actingAs($this->segretario)->get(route('verbali.consultazioni.create'))->assertOk();
        $this->actingAs($this->consigliere)->get(route('verbali.riunioni.create'))->assertForbidden();
        // Anticipi nel menu, senza accendere anche Prima Nota
        $html = $this->actingAs($this->segretario)->get(route('prima-nota.anticipi.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#aria-current="page"\s*>Anticipi</a>#', $html);
        $this->assertDoesNotMatchRegularExpression('#aria-current="page"\s*>Prima Nota</a>#', $html);
        $html = $this->actingAs($this->segretario)->get(route('prima-nota.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#aria-current="page"\s*>Prima Nota</a>#', $html);
        $this->assertDoesNotMatchRegularExpression('#aria-current="page"\s*>Anticipi</a>#', $html);
        $html = $this->actingAs($this->segretario)->get(route('verbali.riunioni.create'))->getContent();
        $this->assertMatchesRegularExpression('#aria-current="page"\s*>Verbali</a>#', $html);
        $this->assertDoesNotMatchRegularExpression('#aria-current="page"\s*>Anticipi</a>#', $html);
    }
}
