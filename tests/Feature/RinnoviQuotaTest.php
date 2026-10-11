<?php

namespace Tests\Feature;

use App\Mail\PromemoriaEmail;
use App\Mail\RicevutaQuotaEmail;
use App\Models\AnnoContabile;
use App\Models\AssociazionePeriodo;
use App\Models\GovernanceCd;
use App\Models\PrimaNota;
use App\Models\Promemoria;
use App\Models\PromemoriaInvio;
use App\Models\Rinnovo;
use App\Models\Socio;
use App\Models\User;
use App\Promemoria\Invii;
use App\Services\RicevuteQuota;
use App\Services\RinnoviQuota;
use App\Support\ImportoInLettere;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\PrimaNotaCategoriaSeeder;
use Database\Seeders\ProfiloEnteSeeder;
use Database\Seeders\PromemoriaSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Rinnovi quota e Promemoria v0.36.0 (rinnovi-quota.md, promemoria.md).
 */
class RinnoviQuotaTest extends TestCase
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
        Carbon::setTestNow('2027-02-15 10:00:00');
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class,
            PrimaNotaCategoriaSeeder::class, ProfiloEnteSeeder::class, PromemoriaSeeder::class]);
        Mail::fake();
        Storage::fake('local');
        $this->presidente = User::factory()->create(['name' => 'Utente Presidente', 'ruolo' => 'presidente', 'attivo' => true]);
        $this->tesoriere = User::factory()->create(['name' => 'Utente Tesoriere', 'ruolo' => 'tesoriere', 'attivo' => true, 'email' => 'tesoriere@example.it']);
        $this->revisore = User::factory()->create(['name' => 'Utente Revisore', 'ruolo' => 'revisore', 'attivo' => true]);
        $this->segretario = User::factory()->create(['name' => 'Utente Segretario', 'ruolo' => 'segretario', 'attivo' => true]);
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
            'nome' => 'Luca', 'cognome' => 'Marini' . $n, 'codice_fiscale' => sprintf('MRNLCU80A01E7%02dX', $n),
            'tipo' => 'ordinario', 'stato' => 'attivo', 'data_nascita' => '1980-01-01', 'luogo_nascita' => 'Orvieto (TR)',
            'email' => "socio{$n}@example.it", 'data_prima_iscrizione' => '2025-03-01',
        ])->save();

        return $s;
    }

    private function giovane(array $dati = []): Socio
    {
        return $this->socio($dati + [
            'nome' => 'Alessio', 'tipo' => 'minorenne', 'data_nascita' => '2010-10-12', 'email' => null,
            'tutore_nome' => 'Marco Ferri', 'tutore_codice_fiscale' => 'FRRMRC75A01E783T', 'tutore_email' => 'marco.ferri@example.it',
        ]);
    }

    private function registra(Socio $s, array $extra = [])
    {
        return $this->actingAs($this->tesoriere)->post(route('rinnovi.store'), $extra + [
            'socio_id' => $s->id, 'anno' => 2027, 'importo' => '70,00', 'data_pagamento' => '2027-02-15', 'metodo' => 'bonifico', 'invia' => '1',
        ]);
    }

    // ----------------------------------------------------------------

    public function test_regole_di_categoria_situazione_e_importo_in_lettere(): void
    {
        $g = $this->giovane(['data_nascita' => '2009-03-10']);   // 18 anni a marzo 2027
        $this->assertSame('giovane', RinnoviQuota::categoria($g, 2027));
        $this->assertSame('ordinario', RinnoviQuota::categoria($g, 2028));
        $this->assertSame(RinnoviQuota::IN_ATTESA, RinnoviQuota::situazione(false, 2027, Carbon::parse('2027-01-31 18:00')));
        $this->assertSame(RinnoviQuota::SOSPESO, RinnoviQuota::situazione(false, 2027, Carbon::parse('2027-02-01')));
        $this->assertSame(RinnoviQuota::SOSPESO, RinnoviQuota::situazione(false, 2027, Carbon::parse('2027-04-30 23:00')));
        $this->assertSame(RinnoviQuota::DA_DICHIARARE_DECADUTO, RinnoviQuota::situazione(false, 2027, Carbon::parse('2027-05-01')));
        $this->assertSame(RinnoviQuota::IN_REGOLA, RinnoviQuota::situazione(true, 2027, Carbon::parse('2027-06-01')));
        // Difetti del Gestionale APS da non ripetere
        $this->assertSame('dodici/50', ImportoInLettere::euro('12.50'));
        $this->assertSame('trentuno/00', ImportoInLettere::euro(31));
        $this->assertSame('trentotto/00', ImportoInLettere::euro(38));
        $this->assertSame('settanta/00', ImportoInLettere::euro(70));
        $this->assertSame('milleduecentoquarantasette/50', ImportoInLettere::euro(1247.5));
    }

    public function test_permessi(): void
    {
        $this->actingAs($this->revisore)->get(route('rinnovi.index'))->assertOk()->assertDontSee('Registra quota');
        $this->actingAs($this->revisore)->get(route('rinnovi.create'))->assertForbidden();
        $this->actingAs($this->segretario)->get(route('rinnovi.index'))->assertForbidden();
        // Il segretario non vede le quote (dalla v0.38.0 vede le sue funzioni: scadenze e tessere)
        $this->actingAs($this->segretario)->get(route('promemoria.index'))->assertOk()->assertDontSee('Quote non versate')->assertSee('Tessere in scadenza');
        $this->actingAs($this->tesoriere)->get(route('promemoria.index'))->assertOk()->assertSee('Quote non versate')->assertSee('Ultimo avviso prima della decadenza');
        $p = Promemoria::firstOrFail();
        $this->actingAs($this->revisore)->get(route('promemoria.edit', $p))->assertOk()->assertSee('Sola lettura');
        $this->actingAs($this->revisore)->put(route('promemoria.update', $p), [])->assertForbidden();
    }

    public function test_registra_quota_con_ricevuta_prima_nota_e_email(): void
    {
        $s = $this->socio();
        $this->actingAs($this->tesoriere)->get(route('rinnovi.create', ['socio' => $s->id]))->assertOk()->assertSee('1/2027')->assertSee('A socio');

        $this->registra($s)->assertRedirect(route('rinnovi.index', ['anno' => 2027]))->assertSessionHas('success');
        $r = Rinnovo::firstOrFail();
        $this->assertSame('1/2027', $r->numero_ricevuta);
        $this->assertSame([2027, 1], [(int) $r->anno_ricevuta, (int) $r->progressivo]);
        $this->assertSame('ordinario', $r->categoria);
        $this->assertSame('inviata', $r->ricevuta_stato);
        Storage::disk('local')->assertExists($r->pdf_path);
        Mail::assertSent(RicevutaQuotaEmail::class, fn ($m) => $m->hasTo($s->email) && count($m->attachments()) === 1);

        $m = PrimaNota::where('rinnovo_id', $r->id)->firstOrFail();
        $this->assertSame(['entrata', 'banca', '70.00', '1/2027'], [$m->tipo, $m->conto, $m->importo, $m->numero_documento]);
        $this->assertSame('QUOTE', $m->categoria->codice);
        $this->assertSame('2027-12-31', $s->fresh()->quota_scadenza->toDateString());
        $this->assertTrue(AnnoContabile::where('anno', 2027)->exists());
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'RINNOVO_CREATED')->exists());

        // Seconda quota dello stesso anno: rifiutata; contanti → cassa, numero successivo
        $this->registra($s)->assertSessionHas('error');
        $s2 = $this->socio();
        $this->registra($s2, ['metodo' => 'contante'])->assertRedirect();
        $r2 = Rinnovo::where('socio_id', $s2->id)->firstOrFail();
        $this->assertSame('2/2027', $r2->numero_ricevuta);
        $this->assertSame('cassa', $r2->movimentoPrimaNota->conto);

        // Elenco e scheda del socio
        $this->actingAs($this->revisore)->get(route('rinnovi.index'))->assertOk()->assertSee('1/2027')->assertSee('In regola');
        $this->actingAs($this->presidente)->get(route('soci.show', $s))->assertOk()->assertSee('Quote associative')->assertSee('1/2027');
        // Ricevuta PDF
        $this->actingAs($this->revisore)->get(route('rinnovi.ricevuta', $r))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_validazione_giovane_assenso_e_importo(): void
    {
        $g = $this->giovane();   // 16 anni
        $this->actingAs($this->tesoriere)->get(route('rinnovi.create', ['socio' => $g->id]))->assertOk()
            ->assertSee('ha dato il suo assenso')->assertSee('marco.ferri@example.it')->assertSee('Detrazione per lo sport dei ragazzi: non disponibile');

        $base = ['socio_id' => $g->id, 'anno' => 2027, 'importo' => '30,00', 'data_pagamento' => '2027-02-15', 'metodo' => 'bonifico'];
        $this->actingAs($this->tesoriere)->post(route('rinnovi.store'), $base)
            ->assertSessionHasErrors(['assenso', 'versato_da_nome', 'versato_da_cf']);
        $r0 = $this->actingAs($this->tesoriere)->post(route('rinnovi.store'), array_merge($base, ['assenso' => '1', 'versato_da_nome' => 'Marco Ferri', 'versato_da_cf' => 'FRRMRC75A01E783T', 'importo' => '25,00']));
        $r0->assertSessionHasErrors('note');   // importo diverso dalla quota senza nota
        $this->actingAs($this->tesoriere)->post(route('rinnovi.store'), $base + ['assenso' => '1', 'versato_da_nome' => 'Marco Ferri', 'versato_da_cf' => 'frrmrc75a01e783t', 'invia' => '1'])
            ->assertRedirect();
        $r = Rinnovo::firstOrFail();
        $this->assertSame(['giovane', 'FRRMRC75A01E783T'], [$r->categoria, $r->versato_da_cf]);
        $this->assertNotNull($r->assenso_minore_il);
        Mail::assertSent(RicevutaQuotaEmail::class, fn ($m) => $m->hasTo('marco.ferri@example.it'));
        $this->assertSame('Marco Ferri', $r->movimentoPrimaNota->soggetto);
        // 2027: B.A.S. non iscritta al RASD → niente detrazione
        $this->assertFalse(RinnoviQuota::detraibile($r));
    }

    public function test_detrazione_solo_con_asd_iscritta_e_pagamento_tracciabile(): void
    {
        Carbon::setTestNow('2028-02-15 10:00:00');
        AssociazionePeriodo::where('dal', '2028-01-01')->update(['rasd_numero' => '12345', 'rasd_data_iscrizione' => '2028-01-10']);
        \App\Services\ProfiloEnte::dimentica();
        GovernanceCd::create(['socio_id' => $this->socio(['nome' => 'Paolo', 'cognome' => 'Zanetti'])->id, 'carica' => 'Segretario e Tesoriere',
            'data_inizio_mandato' => '2027-01-01', 'in_carica' => true]);
        $g = $this->giovane(['data_nascita' => '2014-05-05']);
        $dati = ['socio_id' => $g->id, 'anno' => 2028, 'importo' => '30,00', 'data_pagamento' => '2028-02-15',
            'versato_da_nome' => 'Marco Ferri', 'versato_da_cf' => 'FRRMRC75A01E783T'];
        $this->actingAs($this->tesoriere)->post(route('rinnovi.store'), $dati + ['metodo' => 'bonifico'])->assertRedirect();
        $r = Rinnovo::firstOrFail();
        $this->assertTrue(RinnoviQuota::detraibile($r));
        $this->assertSame('Paolo Zanetti', RinnoviQuota::tesoriere());
        $html = view('pdf.ricevuta-quota', ['r' => $r, 'periodo' => \App\Services\ProfiloEnte::al($r->data_pagamento), 'detraibile' => true,
            'tesoriere' => 'Paolo Zanetti', 'cf' => '90022610555', 'dataDocumento' => $r->data_pagamento])->render();
        $this->assertStringContainsString('ragazzi tra 5 e 18 anni', $html);
        $this->assertStringContainsString('RASD n. 12345', $html);
        $this->assertStringContainsString('trenta/00', $html);
        $this->assertStringContainsString('Tiro con', $html);

        $r->forceFill(['metodo_pagamento' => 'contante'])->save();
        $this->assertFalse(RinnoviQuota::detraibile($r->fresh()));
    }

    public function test_annullamento(): void
    {
        $s = $this->socio();
        $this->registra($s);
        $r = Rinnovo::firstOrFail();

        // Il Tesoriere annulla dall'elenco (non accede alla scheda del socio)
        $this->actingAs($this->tesoriere)->get(route('rinnovi.index'))->assertSee('Annullare la ricevuta 1/2027?');
        $this->actingAs($this->tesoriere)->post(route('rinnovi.annulla', $r), ['motivo' => 'breve'])->assertSessionHasErrorsIn('annulla' . $r->id, 'motivo');
        $this->actingAs($this->revisore)->post(route('rinnovi.annulla', $r), ['motivo' => 'Registrata due volte per errore'])->assertForbidden();
        $this->actingAs($this->tesoriere)->post(route('rinnovi.annulla', $r), ['motivo' => 'Registrata due volte per errore', 'avvisa' => '1'])
            ->assertSessionHas('success');
        $r->refresh();
        $this->assertTrue($r->isAnnullato());
        $this->assertNull(PrimaNota::where('rinnovo_id', $r->id)->first());
        $this->assertNull($s->fresh()->quota_scadenza);
        $this->assertSame('HIGH', DB::table('gl_audit_log')->where('evento', 'RINNOVO_UPDATED')->orderByDesc('id')->value('livello_rischio'));
        Mail::assertSent(RicevutaQuotaEmail::class, fn ($m) => $m->r->isAnnullato());

        // Si registra di nuovo: numero nuovo, l'annullato resta
        $this->registra($s)->assertRedirect();
        $this->assertSame(['1/2027', '2/2027'], Rinnovo::orderBy('id')->pluck('numero_ricevuta')->all());
        $this->actingAs($this->presidente)->get(route('soci.show', $s))->assertSee('Annullata')->assertSee('Registrata due volte per errore');

        // Annullamento su anno contabile chiuso: correzione registrata e saldi rifotografati
        $r2 = Rinnovo::valido()->firstOrFail();
        AnnoContabile::where('anno', 2027)->update(['stato' => 'chiuso', 'saldo_chiusura_cassa' => 0, 'saldo_chiusura_banca' => 70]);
        $this->actingAs($this->tesoriere)->post(route('rinnovi.annulla', $r2), ['motivo' => 'Bonifico restituito dalla banca']);
        $this->assertTrue(DB::table('gl_audit_log')->where('evento', 'PRIMA_NOTA_CORREZIONE_ANNO_CHIUSO')->exists());
        $this->assertSame('0.00', AnnoContabile::where('anno', 2027)->value('saldo_chiusura_banca'));
    }

    public function test_iscrizione_da_domanda_ha_la_ricevuta_numerata(): void
    {
        $this->socio();
        $r = app(RinnoviQuota::class)->registra($this->socio(), ['anno' => 2027, 'importo' => 70, 'data_pagamento' => '2027-01-10', 'metodo' => 'bonifico'], $this->tesoriere);
        $this->assertSame('1/2027', $r->numero_ricevuta);
        // La prima quota della domanda online usa la stessa serie (IscrizioneSocio → RinnoviQuota::registra)
        $sorgente = file_get_contents(app_path('Services/IscrizioneSocio.php'));
        $this->assertStringContainsString('RinnoviQuota::class)->registra', $sorgente);
        $this->assertStringContainsString('RicevuteQuota::class)->genera', $sorgente);
    }

    public function test_elenco_filtri_e_csv(): void
    {
        $ok = $this->socio(['cognome' => 'Rossi']);
        $this->registra($ok);
        $this->socio(['cognome' => 'Vitali']);
        $this->giovane(['cognome' => 'Ferri']);
        $this->socio(['cognome' => 'Nuovo', 'data_prima_iscrizione' => '2028-01-05']);   // non deve la 2027

        $this->actingAs($this->tesoriere)->get(route('rinnovi.index'))->assertOk()
            ->assertSee('Termine del 31 gennaio passato.')->assertSee('Ferri')->assertDontSee('Nuovo');
        $this->actingAs($this->tesoriere)->get(route('rinnovi.index', ['situazione' => 'non_versata']))->assertOk()
            ->assertSee('Vitali')->assertDontSee('Rossi');
        $this->actingAs($this->tesoriere)->get(route('rinnovi.index', ['categoria' => 'giovane']))->assertOk()
            ->assertSee('Ferri')->assertDontSee('Vitali');
        $csv = $this->actingAs($this->revisore)->get(route('rinnovi.export-csv'))->streamedContent();
        $this->assertStringContainsString('Rossi', $csv);
        $this->assertStringContainsString('In ritardo', $csv);

        Carbon::setTestNow('2027-05-02 10:00:00');
        $this->actingAs($this->tesoriere)->get(route('rinnovi.index'))->assertSee('Da dichiarare decaduti.');
    }

    public function test_promemoria_invio_una_volta_per_anno(): void
    {
        $paga = $this->socio(['cognome' => 'Rossi']);
        $this->registra($paga);
        $a = $this->socio(['cognome' => 'Vitali']);
        $g = $this->giovane(['cognome' => 'Ferri']);
        $senza = $this->socio(['cognome' => 'Bianchi', 'email' => null]);
        Mail::fake();   // azzera le email della registrazione

        $p = Promemoria::where('nome', 'Ultimo avviso prima della decadenza')->firstOrFail();
        $this->assertSame('Ogni anno il 1° aprile, alle 9:00', $p->quandoTesto());
        $this->assertFalse(Invii::eIlMomento($p, Carbon::parse('2027-04-01 08:59')));
        $this->assertTrue(Invii::eIlMomento($p, Carbon::parse('2027-04-01 09:00')));
        $this->assertTrue(Invii::eIlMomento($p, Carbon::parse('2027-04-07 23:00')));   // recupero
        $this->assertFalse(Invii::eIlMomento($p, Carbon::parse('2027-04-08 09:00')));

        Carbon::setTestNow('2027-04-01 09:05:00');
        $this->artisan('promemoria:invia')->assertSuccessful();
        Mail::assertSent(PromemoriaEmail::class, 2);
        Mail::assertSent(PromemoriaEmail::class, fn ($m) => $m->hasTo('marco.ferri@example.it'));
        Mail::assertNotSent(PromemoriaEmail::class, fn ($m) => $m->hasTo($paga->email));
        $this->assertSame(1, PromemoriaInvio::where('esito', 'senza_email')->count());
        $this->assertSame(3, PromemoriaInvio::where('promemoria_id', $p->id)->where('periodo', '2027')->count());

        // Testo composto: segnaposti sostituiti, saluto al genitore
        $mail = null;
        Mail::assertSent(PromemoriaEmail::class, function ($m) use (&$mail) {
            if ($m->hasTo('marco.ferri@example.it')) {
                $mail = $m;
            }

            return true;
        });
        $html = $mail->render();
        $this->assertStringContainsString('Ciao Marco', $html);
        $this->assertStringContainsString('30,00 €', $html);
        $this->assertStringContainsString('30/04/2027', $html);
        foreach (['{saluto}', '{nome}', '{anno}', '{importo}', '{iban}', '{firma}'] as $segnaposto) {
            $this->assertStringNotContainsString($segnaposto, $html);
        }

        // Secondo giro: nessun doppione
        Mail::fake();
        Carbon::setTestNow('2027-04-01 09:20:00');
        $this->artisan('promemoria:invia')->assertSuccessful();
        Mail::assertNothingSent();

        // Pagina di modifica: destinatari, anteprima, storico; prova a sé
        $this->actingAs($this->tesoriere)->get(route('promemoria.edit', $p))->assertOk()->assertSee('Storico')->assertSee('Bianchi');
        $this->actingAs($this->tesoriere)->post(route('promemoria.prova', $p))->assertSessionHas('success');
        Mail::assertSent(PromemoriaEmail::class, fn ($m) => $m->hasTo('tesoriere@example.it'));
        // Un promemoria già inviato non si elimina
        $this->actingAs($this->tesoriere)->delete(route('promemoria.destroy', $p))->assertSessionHas('error');
    }

    public function test_promemoria_modifica_e_validazione(): void
    {
        $p = Promemoria::where('nome', 'Sollecito: voto sospeso')->firstOrFail();
        $dati = ['nome' => 'Sollecito', 'attivo' => '1', 'quando' => 'data_fissa', 'giorno' => 31, 'mese' => 2, 'ora' => '10:00',
            'parametri' => ['anno_sociale' => 'corrente'], 'oggetto' => 'Quota {anno}', 'testo' => "Ciao {saluto},\nversa la quota."];
        $this->actingAs($this->tesoriere)->put(route('promemoria.update', $p), $dati)->assertSessionHasErrors('giorno');
        $this->actingAs($this->tesoriere)->put(route('promemoria.update', $p), ['quando' => 'relativa'] + $dati)->assertSessionHasErrors('quando');
        $this->actingAs($this->tesoriere)->put(route('promemoria.update', $p), ['giorno' => 29] + $dati)->assertRedirect();
        $p->refresh();
        $this->assertSame(['Sollecito', 29, 2, '10:00'], [$p->nome, $p->giorno, $p->mese, $p->oraBreve()]);
        // 29 febbraio in un anno non bisestile: il 28
        $this->assertSame('2027-02-28 10:00', Invii::previsto($p, Carbon::parse('2027-01-01'))->format('Y-m-d H:i'));
        $this->assertSame('MEDIUM', DB::table('gl_audit_log')->where('evento', 'PROMEMORIA_UPDATED')->value('livello_rischio'));

        // Nuovo promemoria della funzione
        $this->actingAs($this->tesoriere)->get(route('promemoria.create', ['funzione' => 'quote_non_versate']))->assertOk();
        $this->actingAs($this->tesoriere)->post(route('promemoria.store'), ['funzione' => 'quote_non_versate'] + $dati + ['giorno' => 15, 'mese' => 3])
            ->assertRedirect();
        $nuovo = Promemoria::latest('id')->firstOrFail();
        $this->actingAs($this->tesoriere)->delete(route('promemoria.destroy', $nuovo))->assertRedirect(route('promemoria.index'));
        $this->assertNull($nuovo->fresh());
    }

    /** Revisione, giro 1: una quota incassata prima dell'avvio non crea un anno contabile (né un movimento). */
    public function test_revisione_giro_1_quota_prima_dell_avvio(): void
    {
        $s = $this->socio();
        $this->registra($s, ['data_pagamento' => '2026-12-20'])->assertSessionHas('success');
        $r = Rinnovo::firstOrFail();
        $this->assertSame('1/2026', $r->numero_ricevuta);   // numerata per anno di pagamento
        $this->assertNull($r->movimentoPrimaNota);
        $this->assertStringContainsString('vecchio gestionale', $r->note);
        $this->assertFalse(AnnoContabile::where('anno', 2026)->exists());
        $this->assertSame('2027-12-31', $s->fresh()->quota_scadenza->toDateString());
        // L'annullamento funziona anche senza movimento
        $this->actingAs($this->tesoriere)->post(route('rinnovi.annulla', $r), ['motivo' => 'Prova di annullamento senza movimento'])->assertSessionHas('success');
        // Con la Prima Nota aperta nel 2026 (collaudo) il movimento c'è
        AnnoContabile::create(['anno' => 2026, 'stato' => 'aperto']);
        $this->registra($s, ['data_pagamento' => '2026-12-21'])->assertSessionHas('success');
        $this->assertNotNull(Rinnovo::valido()->firstOrFail()->movimentoPrimaNota);
    }
}
