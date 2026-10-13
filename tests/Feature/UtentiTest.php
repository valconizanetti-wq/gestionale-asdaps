<?php

namespace Tests\Feature;

use App\Mail\LinkPassword;
use App\Mail\OperazioneAvviso;
use App\Models\Promemoria;
use App\Models\User;
use App\Promemoria\Interni;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Più ruoli per utente, pagina Utenti, link per la password, avvisi delle
 * operazioni (v0.40.0, analisi-ruoli-utenti.md).
 */
class UtentiTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private User $paolo; // Segretario e Tesoriere

    private User $consigliere;

    private User $revisore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class]);
        Mail::fake();
        $this->presidente = User::factory()->conRuoli('presidente')->create(['name' => 'Andrea Presidente', 'email' => 'presidente@example.it']);
        $this->paolo = User::factory()->conRuoli('segretario', 'tesoriere')->create(['name' => 'Paolo Doppio', 'email' => 'paolo@example.it']);
        $this->consigliere = User::factory()->conRuoli('consigliere')->create(['name' => 'Massimo Consigliere', 'email' => 'cons@example.it']);
        $this->revisore = User::factory()->conRuoli('revisore')->create(['name' => 'Rita Revisore', 'email' => 'rev@example.it']);
    }

    public function test_permessi_unione_dei_ruoli_e_la_scrittura_prevale(): void
    {
        $p = $this->paolo->fresh();
        // Segretario: soci; Tesoriere: rinnovi quota
        $this->assertTrue($p->puoModificare('soci'));
        $this->assertTrue($p->puoModificare('rinnovi_quota'));
        // Prima Nota: Segretario in lettura, Tesoriere in scrittura → scrive
        $this->assertTrue($p->puoModificare('prima_nota'));
        $this->assertFalse($p->soloLettura('prima_nota'));
        // Impostazioni in lettura per entrambi → lettura
        $this->assertTrue($p->soloLettura('impostazioni'));
        $this->assertFalse($p->puoAccedere('audit_log'));
        $this->assertSame(['segretario', 'tesoriere'], $p->codiciRuoli());
        $this->assertSame('Segretario e Tesoriere', $p->etichettaRuoli());
        $this->assertSame('Segretario · Tesoriere', $p->ruoliInBreve());

        // Un ruolo solo: come prima
        $s = User::factory()->create(); // predefinito Segretario
        $this->assertSame(['segretario'], $s->codiciRuoli());
        $this->assertTrue($s->soloLettura('prima_nota'));

        $this->actingAs($this->paolo)->get(route('rinnovi.index'))->assertOk();
        $this->actingAs($this->paolo)->get(route('soci.index'))->assertOk()->assertSee('Segretario · Tesoriere');
    }

    public function test_email_al_ruolo_una_per_persona(): void
    {
        $u = Interni::utenti('tutti');
        $this->assertSame([$this->presidente->id, $this->paolo->id], $u->pluck('id')->all());
        $this->assertSame([$this->paolo->id], Interni::utenti('tesoriere')->pluck('id')->all());
        $this->assertSame([$this->presidente->id, $this->revisore->id], User::conRuolo('presidente', 'revisore')->orderBy('id')->pluck('id')->all());
    }

    public function test_pagina_utenti_permessi(): void
    {
        $this->actingAs($this->presidente)->get(route('utenti.index'))->assertOk()
            ->assertSee('Paolo Doppio')->assertSee('Nuovo utente');
        $this->actingAs($this->paolo)->get(route('utenti.index'))->assertOk()->assertSee('Nuovo utente');
        // Il resto del CD legge
        $this->actingAs($this->consigliere)->get(route('utenti.index'))->assertOk()->assertDontSee('Nuovo utente')->assertSee('Sola lettura');
        $this->actingAs($this->consigliere)->get(route('utenti.create'))->assertForbidden();
        $this->actingAs($this->consigliere)->put(route('utenti.update', $this->paolo), [])->assertForbidden();
        // Il Revisore no
        $this->actingAs($this->revisore)->get(route('utenti.index'))->assertForbidden();
        // Menu
        $this->actingAs($this->consigliere)->get(route('utenti.index'))->assertSee(route('utenti.index'));
    }

    public function test_presidente_crea_utente_con_due_ruoli_e_link(): void
    {
        $this->actingAs($this->presidente)->post(route('utenti.store'), [
            'name' => 'Luigi Nuovo', 'email' => 'Luigi@Example.it', 'ruoli' => ['tesoriere', 'segretario'], 'attivo' => '1', 'invia_link' => '1',
        ])->assertRedirect(route('utenti.index'))->assertSessionHas('success', fn ($m) => str_contains($m, 'link'));

        $u = User::where('email', 'luigi@example.it')->firstOrFail();
        $this->assertSame(['segretario', 'tesoriere'], $u->codiciRuoli());
        Mail::assertSent(LinkPassword::class, fn ($m) => $m->hasTo('luigi@example.it') && str_contains($m->url, '/password/scegli/'));
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'UTENTE_RUOLI', 'entita_id' => $u->id, 'livello_rischio' => 'HIGH']);
        // Avviso a chi non l'ha fatto e vede gli utenti: Paolo (Segretario), non il Presidente che l'ha fatto
        Mail::assertSent(OperazioneAvviso::class, fn ($m) => $m->hasTo('paolo@example.it'));
        Mail::assertNotSent(OperazioneAvviso::class, fn ($m) => $m->hasTo('presidente@example.it'));
        Mail::assertNotSent(OperazioneAvviso::class, fn ($m) => $m->hasTo('cons@example.it'));
    }

    public function test_combinazioni_non_ammesse(): void
    {
        $this->actingAs($this->presidente)->post(route('utenti.store'), [
            'name' => 'X', 'email' => 'x@example.it', 'ruoli' => ['revisore', 'tesoriere'], 'attivo' => '1',
        ])->assertSessionHasErrors(['ruoli' => "Il Revisore non può avere altri ruoli: l'Organo di controllo è fuori dal Consiglio Direttivo (statuto, art. 20.1)."]);
        $this->actingAs($this->presidente)->post(route('utenti.store'), [
            'name' => 'X', 'email' => 'x@example.it', 'ruoli' => ['consigliere', 'segretario'], 'attivo' => '1',
        ])->assertSessionHasErrors('ruoli');
        $this->actingAs($this->presidente)->post(route('utenti.store'), [
            'name' => 'X', 'email' => 'x@example.it', 'ruoli' => [], 'attivo' => '1',
        ])->assertSessionHasErrors('ruoli');
        $this->actingAs($this->presidente)->post(route('utenti.store'), [
            'name' => 'X', 'email' => 'paolo@example.it', 'ruoli' => ['segretario'], 'attivo' => '1',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('gl_users', ['email' => 'x@example.it']);
    }

    public function test_ultimo_presidente_e_se_stessi(): void
    {
        // Togliersi il ruolo di Presidente: non resta nessuno
        $this->actingAs($this->presidente)->put(route('utenti.update', $this->presidente), [
            'name' => 'Andrea Presidente', 'email' => 'presidente@example.it', 'ruoli' => ['segretario'], 'attivo' => '1',
        ])->assertSessionHasErrors(['ruoli' => 'Deve restare almeno un Presidente attivo: prima dai il ruolo a un altro utente.']);
        $this->assertSame(['presidente'], $this->presidente->fresh()->codiciRuoli());
        // Disattivarsi
        $this->actingAs($this->presidente)->put(route('utenti.update', $this->presidente), [
            'name' => 'Andrea Presidente', 'email' => 'presidente@example.it', 'ruoli' => ['presidente'], 'attivo' => '0',
        ])->assertSessionHasErrors('attivo');
        $this->assertTrue($this->presidente->fresh()->attivo);
        // Con un secondo Presidente si può
        $altro = User::factory()->conRuoli('presidente')->create();
        $this->actingAs($this->presidente)->put(route('utenti.update', $altro), [
            'name' => $altro->name, 'email' => $altro->email, 'ruoli' => ['presidente'], 'attivo' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($altro->fresh()->attivo);
    }

    public function test_limiti_del_segretario(): void
    {
        // Non dà il ruolo di Presidente
        $this->actingAs($this->paolo)->put(route('utenti.update', $this->consigliere), [
            'name' => 'Massimo Consigliere', 'email' => 'cons@example.it', 'ruoli' => ['presidente'], 'attivo' => '1',
        ])->assertSessionHasErrors(['ruoli' => 'Il ruolo di Presidente lo dà o lo toglie solo il Presidente.']);
        // Non modifica l'utente del Presidente (né il link)
        $this->actingAs($this->paolo)->get(route('utenti.edit', $this->presidente))->assertRedirect(route('utenti.index'));
        $this->actingAs($this->paolo)->put(route('utenti.update', $this->presidente), [
            'name' => 'Altro', 'email' => 'presidente@example.it', 'ruoli' => ['presidente'], 'attivo' => '1',
        ])->assertSessionHasErrors('ruoli');
        $this->actingAs($this->paolo)->post(route('utenti.link-password', $this->presidente))->assertSessionHas('error');
        $this->assertSame('Andrea Presidente', $this->presidente->fresh()->name);
        // Non cambia i propri ruoli, ma nome ed email sì
        $this->actingAs($this->paolo)->put(route('utenti.update', $this->paolo), [
            'name' => 'Paolo Doppio', 'email' => 'paolo@example.it', 'ruoli' => ['segretario', 'tesoriere', 'consigliere'], 'attivo' => '1',
        ])->assertSessionHasErrors('ruoli');
        $this->actingAs($this->paolo)->put(route('utenti.update', $this->paolo), [
            'name' => 'Paolo D.', 'email' => 'paolo@example.it', 'ruoli' => ['segretario', 'tesoriere'], 'attivo' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Paolo D.', $this->paolo->fresh()->name);
        // Cambia i ruoli di un consigliere: il Presidente riceve l'avviso
        $this->actingAs($this->paolo)->put(route('utenti.update', $this->consigliere), [
            'name' => 'Massimo Consigliere', 'email' => 'cons@example.it', 'ruoli' => ['tesoriere'], 'attivo' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['tesoriere'], $this->consigliere->fresh()->codiciRuoli());
        Mail::assertSent(OperazioneAvviso::class, fn ($m) => $m->hasTo('presidente@example.it') && str_starts_with($m->autore, 'Paolo')
            && str_contains($m->render(), 'Consigliere → Tesoriere'));
        Mail::assertNotSent(OperazioneAvviso::class, fn ($m) => $m->hasTo('paolo@example.it'));
        // La pagina del form gli mostra il ruolo di Presidente bloccato
        $this->actingAs($this->paolo)->get(route('utenti.create'))->assertOk()->assertSee('Il ruolo di Presidente lo dà o lo toglie solo il Presidente.');
    }

    public function test_password_dimenticata_e_scelta(): void
    {
        // Risposta sempre uguale; link solo agli utenti attivi
        $this->post(route('password.invia'), ['email' => 'nessuno@example.it'])->assertSessionHas('success');
        Mail::assertNothingSent();
        $this->consigliere->update(['attivo' => false]);
        $this->post(route('password.invia'), ['email' => 'cons@example.it'])->assertSessionHas('success');
        Mail::assertNotSent(LinkPassword::class);

        $this->post(route('password.invia'), ['email' => 'paolo@example.it'])->assertSessionHas('success');
        $url = null;
        Mail::assertSent(LinkPassword::class, function ($m) use (&$url) {
            $url = $m->url;

            return $m->hasTo('paolo@example.it');
        });
        preg_match('#/password/scegli/([^?]+)\?email=#', $url, $t);
        $token = $t[1];
        $this->get($url)->assertOk()->assertSee('Scegli la password');

        // Troppo corta
        $this->post(route('password.salva'), ['token' => $token, 'email' => 'paolo@example.it', 'password' => 'corta', 'password_confirmation' => 'corta'])
            ->assertSessionHasErrors('password');
        // Giusta: entra
        $this->post(route('password.salva'), ['token' => $token, 'email' => 'paolo@example.it', 'password' => 'una frase lunga', 'password_confirmation' => 'una frase lunga'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->paolo);
        $this->assertTrue(Hash::check('una frase lunga', $this->paolo->fresh()->password));
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'UTENTE_PASSWORD', 'entita_id' => $this->paolo->id]);
        // Monouso
        auth()->logout();
        $this->post(route('password.salva'), ['token' => $token, 'email' => 'paolo@example.it', 'password' => 'altra frase lunga', 'password_confirmation' => 'altra frase lunga'])
            ->assertSessionHasErrors('email');
        // Pagina di accesso con il collegamento
        $this->get(route('login'))->assertSee(route('password.dimenticata'));
    }

    public function test_migration_copia_il_ruolo(): void
    {
        // Le righe della tabella ponte coincidono con i ruoli degli utenti
        $this->assertSame(4 + 0, DB::table('gl_users')->count());
        $this->assertSame(5, DB::table('gl_users_ruoli')->count());
        $this->assertDatabaseHas('gl_ruoli_sezioni', ['ruolo_codice' => 'consigliere', 'sola_lettura' => 1]);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('gl_users', 'ruolo'));
    }

    public function test_avviso_operazione_delicata_una_email_con_tutte_le_righe(): void
    {
        // Eliminazione di una scheda fatta dal Presidente: avviso a Paolo
        // (Segretario vede i soci), non al Presidente né al consigliere
        $s = new \App\Models\Socio();
        $s->forceFill(['nome' => 'Luca', 'cognome' => 'Verdi', 'codice_fiscale' => 'VRDLCU90E10L117B',
            'data_prima_iscrizione' => '2026-01-01', 'tipo' => 'ordinario', 'stato' => 'attivo'])->save();
        $this->actingAs($this->presidente)->delete(route('soci.destroy', $s), ['motivo' => 'Inserito due volte per errore'])->assertRedirect();
        Mail::assertSent(OperazioneAvviso::class, 1);
        Mail::assertSent(OperazioneAvviso::class, fn ($m) => $m->hasTo('paolo@example.it') && $m->righe[0]['evento'] === 'SOCIO_DELETED');
    }
}
