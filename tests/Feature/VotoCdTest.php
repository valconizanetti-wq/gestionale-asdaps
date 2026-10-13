<?php

namespace Tests\Feature;

use App\Mail\DomandaAccolta;
use App\Mail\DomandaDiniego;
use App\Mail\DomandaRitirataConsiglieri;
use App\Mail\PromemoriaDiniego;
use App\Mail\SocioIscritto;
use App\Mail\VotoCdConsiglieriSenzaEmail;
use App\Mail\VotoCdEsito;
use App\Mail\VotoCdInvito;
use App\Models\Domanda;
use App\Models\GovernanceCd;
use App\Models\Impostazione;
use App\Models\PrimaNota;
use App\Models\Rinnovo;
use App\Models\Socio;
use App\Models\User;
use App\Models\VotoCd;
use App\Services\VotazioneCd;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Voto del CD e area riservata delle domande (domande-iscrizione.md §3).
 * Gira su MySQL: le migration usano istruzioni MySQL (vedi docs/deploy.md,
 * "Test").
 */
class VotoCdTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private User $segretario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class]);
        \App\Models\Impostazione::set('iscrizioni_aperte', true, 'boolean');
        Mail::fake();

        $this->presidente = User::factory()->conRuoli('presidente')->create(['attivo' => true]);
        $this->segretario = User::factory()->conRuoli('segretario')->create(['attivo' => true]);
    }

    /** CD di prova: Presidente + consiglieri (email nulla = senza email). */
    private function consiglio(array $membri = null): void
    {
        $membri ??= [
            ['Andrea', 'Papini', 'Presidente', 'andrea@esempio.it'],
            ['Massimo', 'Papini', 'Consigliere', 'massimo@esempio.it'],
            ['Paolo', 'Zanetti', 'Segretario e Tesoriere', 'paolo@esempio.it'],
            ['Luigi', 'Tascio', 'Consigliere', 'luigi@esempio.it'],
        ];
        foreach ($membri as $i => [$nome, $cognome, $carica, $email]) {
            $socio = new Socio();
            $socio->forceFill([
                'nome' => $nome, 'cognome' => $cognome, 'email' => $email,
                'codice_fiscale' => sprintf('CDXCNS80A01L117%s', chr(65 + $i)),
                'data_prima_iscrizione' => '2026-01-01', 'tipo' => 'ordinario',
            ])->save();
            GovernanceCd::create(['socio_id' => $socio->id, 'carica' => $carica, 'in_carica' => true, 'data_inizio_mandato' => '2026-01-01']);
        }
    }

    private function domanda(array $extra = []): Domanda
    {
        $d = new Domanda();
        $d->forceFill($extra + [
            'token' => Domanda::hashToken(bin2hex(random_bytes(16))),
            'cognome' => 'Verdi', 'nome' => 'Luca', 'sesso' => 'M',
            'data_nascita' => '1990-05-10', 'luogo_nascita' => 'Terni (TR)',
            'codice_fiscale' => 'VRDLCU90E10L117B', 'email' => 'luca@esempio.it',
            'indirizzo' => 'Via Garibaldi 5', 'cap' => '05014', 'citta' => 'Castel Viscardo', 'provincia' => 'TR',
            'tipo_socio' => 'ordinario', 'documento_tipo' => 'carta_identita', 'documento_numero' => 'CA98765ZZ',
            'ip_address' => '127.0.0.1', 'informativa_versione' => '1.0', 'statuto_versione' => '1.0',
            'consenso_privacy' => true, 'consenso_arbitrato' => true, 'consenso_extra_ue' => false, 'consenso_foto_video' => false,
            'stato' => 'in_attesa', 'token_conferma' => Domanda::hashToken(str_repeat('b', 64)),
            'scadenza_cd' => now()->addDays(5), 'cartacea' => false, 'is_test' => false,
        ])->save();

        return $d;
    }

    /** Apre la votazione e restituisce i token in chiaro per consigliere (nome => token). */
    private function apri(Domanda $d): array
    {
        app(VotazioneCd::class)->apri($d);

        return $d->voti()->get()->mapWithKeys(fn (VotoCd $v) => [$v->consigliere_nome => $v->tokenInChiaro()])->all();
    }

    private function vota(string $token, string $voto, ?string $motivo = null)
    {
        return $this->post(route('voto-cd.vota', $token), ['voto' => $voto, 'motivo' => $motivo]);
    }

    // ── Regola dell'esito ───────────────────────────────────────────────

    public function test_regola_dell_esito(): void
    {
        $v = fn (...$voti) => collect($voti)->map(fn ($x) => ['voto' => $x[0], 'presidente' => $x[1] ?? false]);

        // sotto il quorum (2 su 4 votanti: servono 3)
        $this->assertSame(Domanda::ESITO_D_UFFICIO, Domanda::calcolaEsito($v(['contrario'], ['contrario'], [null], [null])));
        // maggioranza dei voti espressi
        $this->assertSame(Domanda::ESITO_APPROVATA, Domanda::calcolaEsito($v(['favorevole'], ['favorevole'], ['contrario'], [null])));
        $this->assertSame(Domanda::ESITO_RIFIUTATA, Domanda::calcolaEsito($v(['contrario'], ['contrario'], ['favorevole'], [null])));
        // parità: prevale il Presidente
        $this->assertSame(Domanda::ESITO_APPROVATA, Domanda::calcolaEsito($v(['favorevole', true], ['favorevole'], ['contrario'], ['contrario'])));
        $this->assertSame(Domanda::ESITO_RIFIUTATA, Domanda::calcolaEsito($v(['contrario', true], ['favorevole'], ['contrario'], ['favorevole'])));
        // parità senza il Presidente (Consiglio di 5): decide il Presidente
        $this->assertSame(Domanda::ESITO_PARITA, Domanda::calcolaEsito($v([null, true], ['favorevole'], ['contrario'], ['favorevole'], ['contrario'])));
        // nessun consigliere
        $this->assertSame(Domanda::ESITO_D_UFFICIO, Domanda::calcolaEsito(collect()));
    }

    public function test_carica_di_presidente(): void
    {
        $this->assertTrue(VotazioneCd::caricaDiPresidente('Presidente'));
        $this->assertTrue(VotazioneCd::caricaDiPresidente('presidente e tesoriere'));
        $this->assertFalse(VotazioneCd::caricaDiPresidente('Vicepresidente'));
        $this->assertFalse(VotazioneCd::caricaDiPresidente('Consigliere'));
    }

    // ── Presentazione dal modulo pubblico ───────────────────────────────

    public function test_presentazione_valida_con_l_invio_e_apre_la_votazione(): void
    {
        $this->consiglio();
        $this->post(route('iscrizione.dati.salva'), [
            'chi' => 'me', 'nome' => 'Luca', 'cognome' => 'Verdi', 'codice_fiscale' => 'VRDLCU90E10L117B',
            'email' => 'luca@esempio.it', 'telefono' => '3407654321',
            'documento_tipo' => 'carta_identita', 'documento_numero' => 'CA98765ZZ',
            'indirizzo' => 'Via Garibaldi 5', 'cap' => '05014', 'citta' => 'Castel Viscardo', 'provincia' => 'TR',
        ])->assertRedirect(route('iscrizione.riepilogo'));

        $this->post(route('iscrizione.invia'), ['impronta_testi' => '0|1.0|1.0', 'dichiarazione' => '1', 'informativa' => '1'])
            ->assertRedirect(route('iscrizione.inviata'));

        $d = Domanda::where('codice_fiscale', 'VRDLCU90E10L117B')->firstOrFail();
        $this->assertSame('in_attesa', $d->stato);
        $this->assertTrue($d->scadenza_cd->between(now()->addDays(5)->subMinute(), now()->addDays(5)->addMinute()));
        $this->assertNotNull($d->ricevuta_path);
        $this->assertSame(4, $d->voti()->count());
        Mail::assertSent(\App\Mail\IscrizioneRicevuta::class, fn ($m) => $m->hasTo('luca@esempio.it'));
        Mail::assertSent(VotoCdInvito::class, 4);
        $this->get(route('iscrizione.inviata'))->assertOk()->assertSee('Domanda inviata');

        // stessa persona: bloccata finché la domanda è all'esame
        $this->post(route('iscrizione.dati.salva'), [
            'chi' => 'me', 'nome' => 'Luca', 'cognome' => 'Verdi', 'codice_fiscale' => 'VRDLCU90E10L117B',
            'email' => 'luca@esempio.it', 'documento_tipo' => 'carta_identita', 'documento_numero' => 'CA98765ZZ',
            'indirizzo' => 'Via Garibaldi 5', 'cap' => '05014', 'citta' => 'Castel Viscardo', 'provincia' => 'TR',
        ])->assertSessionHasErrors('codice_fiscale');

        // un nome con sintassi markdown non passa: finirebbe come link nelle email ai consiglieri
        $this->post(route('iscrizione.dati.salva'), [
            'chi' => 'me', 'nome' => '[Vota qui](https://esempio.com)', 'cognome' => 'Rossi', 'codice_fiscale' => 'RSSMRA80A01L117D',
            'email' => 'm@esempio.it', 'documento_tipo' => 'carta_identita', 'documento_numero' => 'CA98765ZZ',
            'indirizzo' => 'Via Roma 1', 'cap' => '05014', 'citta' => 'Castel Viscardo', 'provincia' => 'TR',
        ])->assertSessionHasErrors('nome');
    }

    // ── Apertura e voto ─────────────────────────────────────────────────

    public function test_apertura_crea_i_voti_e_manda_i_link(): void
    {
        $this->consiglio([
            ['Andrea', 'Papini', 'Presidente', 'andrea@esempio.it'],
            ['Massimo', 'Papini', 'Consigliere', 'massimo@esempio.it'],
            ['Mario', 'Senzamail', 'Consigliere', null],
        ]);
        $d = $this->domanda();
        $token = $this->apri($d);

        $this->assertSame(3, $d->voti()->count());
        $this->assertTrue($d->voti()->where('consigliere_nome', 'Andrea Papini')->value('presidente'));
        $senza = $d->voti()->where('consigliere_nome', 'Mario Senzamail')->first();
        $this->assertNull($senza->consigliere_email);
        $this->assertNull($senza->link_inviato_il);
        Mail::assertSent(VotoCdInvito::class, 2);
        Mail::assertSent(VotoCdConsiglieriSenzaEmail::class, fn ($m) => $m->hasTo('andrea@esempio.it'));
        // nel database solo l'impronta del token
        $this->assertDatabaseMissing('gl_voti_cd', ['token_link' => $token['Andrea Papini']]);
        // stessa email per più consiglieri: un link per ciascuno
        $this->assertNotSame($token['Andrea Papini'], $token['Massimo Papini']);
        // seconda apertura: niente doppioni
        app(VotazioneCd::class)->apri($d);
        $this->assertSame(3, $d->voti()->count());
    }

    public function test_voto_dal_link_e_motivazione_obbligatoria(): void
    {
        $this->consiglio();
        $d = $this->domanda();
        $t = $this->apri($d);

        $this->get(route('voto-cd.mostra', $t['Massimo Papini']))->assertOk()->assertSee('Il tuo voto');
        $this->vota($t['Massimo Papini'], 'contrario')->assertSessionHasErrors('motivo');
        $this->vota($t['Massimo Papini'], 'contrario', 'troppo corta')->assertSessionHasErrors('motivo');
        $this->vota($t['Massimo Papini'], 'contrario', 'Nel 2024 non ha saldato il noleggio del corso.')
            ->assertRedirect(route('voto-cd.mostra', $t['Massimo Papini']));

        $voto = VotoCd::perToken($t['Massimo Papini']);
        $this->assertSame('contrario', $voto->voto);
        $this->assertNotNull($voto->votato_il);
        $this->get(route('voto-cd.mostra', $t['Massimo Papini']))->assertSee('Voto registrato');

        // secondo voto: ignorato
        $this->vota($t['Massimo Papini'], 'favorevole');
        $this->assertSame('contrario', $voto->fresh()->voto);
        // audit: solo id e voto
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'VOTO_CD_ESPRESSO', 'entita_id' => $d->id, 'dettaglio' => "Domanda {$d->id}, voto {$voto->id}: contrario."]);
        // link sconosciuto
        $this->get(route('voto-cd.mostra', str_repeat('z', 64)))->assertOk()->assertSee('Link non valido');
    }

    public function test_link_rimandato_resta_lo_stesso(): void
    {
        $this->consiglio();
        $d = $this->domanda();
        $t = $this->apri($d);
        $voto = VotoCd::perToken($t['Luigi Tascio']);

        $this->actingAs($this->segretario)->post(route('domande.reinvia', [$d, $voto]))->assertSessionHas('success');
        $this->assertNotNull(VotoCd::perToken($t['Luigi Tascio']));
    }

    // ── Chiusura ────────────────────────────────────────────────────────

    public function test_tutti_votano_si_chiude_e_si_accoglie(): void
    {
        $this->consiglio();
        $d = $this->domanda();
        $t = $this->apri($d);

        $this->vota($t['Andrea Papini'], 'favorevole');
        $this->vota($t['Massimo Papini'], 'contrario', 'Nel 2024 non ha saldato il noleggio del corso.');
        $this->vota($t['Paolo Zanetti'], 'favorevole');
        $this->assertSame('in_attesa', $d->fresh()->stato);
        $this->vota($t['Luigi Tascio'], 'favorevole');

        $d->refresh();
        $this->assertSame('approvata', $d->stato);
        $this->assertSame(Domanda::CHIUSURA_TUTTI, $d->votazione_chiusura);
        $this->assertEquals(70.0, (float) $d->quota_importo);
        $this->assertNotNull($d->esito_comunicato_il);
        $this->assertNotNull($d->verbale_cd_id);
        $this->assertStringContainsString('Nel 2024 non ha saldato', $d->verbaleCd->corpo_html);
        Mail::assertSent(DomandaAccolta::class, fn ($m) => $m->hasTo('luca@esempio.it'));
        Mail::assertSent(VotoCdEsito::class, 4);
        // la pagina di voto ora mostra il voto registrato, anche all'ultimo
        $this->get(route('voto-cd.mostra', $t['Luigi Tascio']))->assertSee('La votazione è chiusa');
    }

    public function test_quorum_raggiunto_si_aspetta_la_scadenza(): void
    {
        $this->consiglio();
        $d = $this->domanda();
        $t = $this->apri($d);
        foreach (['Andrea Papini', 'Massimo Papini', 'Paolo Zanetti'] as $nome) {
            $this->vota($t[$nome], 'favorevole');
        }
        // esito già certo, ma si chiude solo alla scadenza (o se vota anche Tascio)
        $this->assertSame('in_attesa', $d->fresh()->stato);
    }

    public function test_scadenza_senza_quorum_accolta_d_ufficio(): void
    {
        $this->consiglio();
        $d = $this->domanda();
        $t = $this->apri($d);
        $this->vota($t['Massimo Papini'], 'contrario', 'Nel 2024 non ha saldato il noleggio del corso.');

        $this->travel(6)->days();
        $this->artisan('domande:scadenze')->assertSuccessful();

        $d->refresh();
        $this->assertSame('approvata', $d->stato);
        $this->assertTrue($d->silenzio_assenso);
        $this->assertSame(Domanda::CHIUSURA_SCADENZA, $d->votazione_chiusura);
        // voto dopo la chiusura: respinto
        $this->vota($t['Luigi Tascio'], 'favorevole');
        $this->assertNull(VotoCd::perToken($t['Luigi Tascio'])->voto);
        $this->get(route('voto-cd.mostra', $t['Luigi Tascio']))->assertSee('Votazione chiusa');
    }

    public function test_maggioranza_contraria_diniego_del_presidente(): void
    {
        $this->consiglio();
        $d = $this->domanda();
        $t = $this->apri($d);
        $this->vota($t['Andrea Papini'], 'contrario', 'Non ha rispettato le regole di sicurezza in linea.');
        $this->vota($t['Massimo Papini'], 'contrario', 'Resta aperto il debito del corso 2024.');
        $this->vota($t['Paolo Zanetti'], 'contrario', 'Condivido le motivazioni del Presidente.');
        $this->vota($t['Luigi Tascio'], 'favorevole');

        $d->refresh();
        $this->assertSame('da_rifiutare', $d->stato);
        Mail::assertSent(VotoCdEsito::class, fn ($m) => $m->hasTo('andrea@esempio.it') && str_contains($m->envelope()->subject, 'serve il tuo intervento'));
        Mail::assertNotSent(DomandaDiniego::class);

        // il segretario non può inviarlo
        $this->actingAs($this->segretario)->post(route('domande.diniego', $d), ['motivo' => 'Motivazione sufficientemente lunga per il test.'])->assertForbidden();
        $this->actingAs($this->presidente)->post(route('domande.diniego', $d), ['motivo' => 'Il Consiglio ritiene che non sussistano le condizioni per l\'ammissione.'])
            ->assertRedirect(route('domande.show', $d));

        $d->refresh();
        $this->assertSame('rifiutata', $d->stato);
        $this->assertNotNull($d->esito_comunicato_il);
        Mail::assertSent(DomandaDiniego::class, fn ($m) => $m->hasTo('luca@esempio.it'));
        $this->assertStringContainsString('non sussistano le condizioni', $d->verbaleCd->corpo_html);
    }

    public function test_parita_senza_presidente_decide_lui(): void
    {
        $this->consiglio([
            ['Andrea', 'Papini', 'Presidente', 'andrea@esempio.it'],
            ['Massimo', 'Papini', 'Consigliere', 'massimo@esempio.it'],
            ['Paolo', 'Zanetti', 'Segretario', 'paolo@esempio.it'],
            ['Luigi', 'Tascio', 'Consigliere', 'luigi@esempio.it'],
            ['Giulia', 'Conti', 'Consigliera', 'giulia@esempio.it'],
        ]);
        $d = $this->domanda();
        $t = $this->apri($d);
        $this->vota($t['Massimo Papini'], 'contrario', 'Resta aperto il debito del corso 2025.');
        $this->vota($t['Paolo Zanetti'], 'favorevole');
        $this->vota($t['Luigi Tascio'], 'contrario', 'Non ha frequentato il corso base.');
        $this->vota($t['Giulia Conti'], 'favorevole');

        $this->travel(6)->days();
        $this->artisan('domande:scadenze');
        $this->assertSame('in_parita', $d->fresh()->stato);

        $this->actingAs($this->presidente)->post(route('domande.parita', $d), ['decisione' => 'respingi'])->assertSessionHasErrors('motivo');
        $this->actingAs($this->presidente)->post(route('domande.parita', $d), ['decisione' => 'accogli'])->assertSessionHas('success');

        $d->refresh();
        $this->assertSame('approvata', $d->stato);
        $this->assertNotNull($d->decisione_presidente_il);
        Mail::assertSent(DomandaAccolta::class);
    }

    public function test_nessuna_chiusura_manuale(): void
    {
        $this->consiglio();
        $d = $this->domanda();
        $t = $this->apri($d);
        foreach (['Andrea Papini', 'Massimo Papini', 'Paolo Zanetti'] as $nome) {
            $this->vota($t[$nome], 'favorevole');
        }
        // la rotta non esiste più e il pulsante non c'è, nemmeno per il Presidente
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('domande.chiudi'));
        $this->actingAs($this->presidente)->get(route('domande.show', $d))->assertDontSee('Chiudi la votazione');
        $this->assertSame('in_attesa', $d->fresh()->stato);
    }

    // ── Comando orario ──────────────────────────────────────────────────

    public function test_sollecito_una_volta_sola_e_promemoria(): void
    {
        $this->consiglio();
        $d = $this->domanda();
        $t = $this->apri($d);
        $this->vota($t['Andrea Papini'], 'favorevole');

        $this->travel(3)->days();
        $this->travel(1)->hours();
        $this->artisan('domande:scadenze');
        $this->artisan('domande:scadenze');
        Mail::assertSent(VotoCdInvito::class, 4 + 3); // 4 inviti + 3 solleciti

        // promemoria del diniego a 7 giorni dalla delibera
        $r = $this->domanda(['codice_fiscale' => 'NRENDR78E05L117X', 'stato' => 'da_rifiutare', 'esito_il' => now()->subDays(8)]);
        $this->artisan('domande:scadenze');
        $this->artisan('domande:scadenze');
        Mail::assertSent(PromemoriaDiniego::class, 1);
        $this->assertNotNull($r->fresh()->promemoria_diniego_il);
    }

    public function test_decadenza_e_cancellazione(): void
    {
        $accolta = $this->domanda(['stato' => 'approvata', 'esito_il' => now()->subDays(40), 'quota_scadenza' => now()->subDays(10)]);
        $vecchia = $this->domanda(['codice_fiscale' => 'NRENDR78E05L117X', 'stato' => 'rifiutata', 'esito_il' => now()->subMonths(13)]);

        $this->artisan('domande:scadenze')->assertSuccessful();

        $this->assertSame('decaduta', $accolta->fresh()->stato);
        $this->assertNull(Domanda::find($vecchia->id));
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'DOMANDA_CANCELLATA_FINE_CONSERVAZIONE', 'entita_id' => $vecchia->id]);
    }

    // ── Ritiro e iscrizione ─────────────────────────────────────────────

    public function test_ritiro_durante_la_votazione_avvisa_i_consiglieri(): void
    {
        $this->consiglio();
        $d = $this->domanda();
        $this->apri($d);

        $this->get(route('iscrizione.annulla', str_repeat('b', 64)))->assertOk()->assertSee('Annulla la domanda');
        $this->post(route('iscrizione.annulla.esegui', str_repeat('b', 64)))->assertOk()->assertSee('annullata');

        $this->assertNull(Domanda::find($d->id));
        $this->assertSame(0, VotoCd::count());
        Mail::assertSent(DomandaRitirataConsiglieri::class, 4);
    }

    public function test_iscrizione_nel_libro_soci_con_prima_nota_e_promozione(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
        // Contabilità avviata nel 2026: la quota entra in Prima Nota (prima
        // dell'avvio solo rinnovo e ricevuta, RinnoviQuotaTest).
        \App\Models\Impostazione::set('contabilita_anno_avvio', 2026, 'integer');
        $d = $this->domanda(['stato' => 'approvata', 'esito_il' => now()->subDay(), 'quota_importo' => 70, 'quota_anno' => 2027,
            'quota_promo' => true, 'quota_scadenza' => now()->addDays(29), 'consenso_foto_video' => true]);

        $this->actingAs($this->segretario)->post(route('domande.iscrivi', $d), [
            'quota_versata_il' => '2026-10-12', 'metodo' => 'bonifico', 'promo' => '1',
        ])->assertRedirect();

        $socio = Socio::where('codice_fiscale', 'VRDLCU90E10L117B')->firstOrFail();
        $this->assertSame('2027-12-31', $socio->quota_scadenza->toDateString());
        $this->assertSame('online', $socio->privacy_metodo);
        // v0.33.0: il consenso a foto e video della domanda passa nella scheda
        $this->assertTrue($socio->foto_video_consenso);
        $this->assertSame('domanda', $socio->foto_video_origine);
        $this->assertSame($d->created_at->toDateTimeString(), $socio->foto_video_il->toDateTimeString());
        $this->assertSame($socio->id, $d->fresh()->socio_id);
        $rinnovo = Rinnovo::where('socio_id', $socio->id)->firstOrFail();
        $this->assertSame(2027, (int) $rinnovo->anno);
        $movimento = PrimaNota::where('rinnovo_id', $rinnovo->id)->firstOrFail();
        $this->assertSame('entrata', $movimento->tipo);
        $this->assertSame('banca', $movimento->conto);
        $this->assertEquals(70.0, (float) $movimento->importo);
        $this->assertNotNull($movimento->categoria_id);
        Mail::assertSent(SocioIscritto::class);

        // seconda volta: rifiutata
        $this->actingAs($this->segretario)->post(route('domande.iscrivi', $d), [
            'quota_versata_il' => '2026-10-12', 'metodo' => 'bonifico',
        ])->assertSessionHas('error');
        $this->assertSame(1, Socio::where('codice_fiscale', 'VRDLCU90E10L117B')->count());
    }

    public function test_pagine_dell_area_riservata(): void
    {
        $this->consiglio();
        $d = $this->domanda();
        $this->apri($d);

        $this->actingAs($this->segretario)->get(route('domande.index'))->assertOk()->assertSee('Verdi');
        $this->actingAs($this->segretario)->get(route('domande.show', $d))->assertOk()->assertSee('Voto del Consiglio Direttivo');
    }

    public function test_domanda_di_prova_non_entra_nell_audit(): void
    {
        Impostazione::set('domande_modalita_test', true, 'boolean');
        $this->consiglio();
        $d = $this->domanda(['is_test' => true]);
        $t = $this->apri($d);
        $this->vota($t['Andrea Papini'], 'favorevole');

        $this->assertDatabaseMissing('gl_audit_log', ['entita_tipo' => 'Domanda', 'entita_id' => $d->id]);
    }
}
