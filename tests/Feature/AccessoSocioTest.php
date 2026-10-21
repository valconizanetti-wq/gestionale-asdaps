<?php

namespace Tests\Feature;

use App\Mail\EmailAccessoCambiata;
use App\Models\GovernanceCd;
use App\Models\Socio;
use App\Models\User;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Soci e utenti: una sola anagrafica (v0.44.1, analisi-soci-utenti.md,
 * decisioni di Paolo del 21/10/2026).
 */
class AccessoSocioTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private Socio $papini;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class]);
        Mail::fake();
        $this->presidente = User::factory()->conRuoli('presidente')->create(['name' => 'Andrea Papini', 'email' => 'andrea@example.it']);
        $this->papini = $this->socio('Papini', 'Andrea', 'andrea@example.it', 'PPNNDR80A01E098Y');
        $this->aggiungi($this->papini, 'presidente')->assertSessionHasNoErrors();
    }

    private function socio(string $cognome, string $nome, ?string $email, string $cf): Socio
    {
        $s = new Socio();
        $s->forceFill(['cognome' => $cognome, 'nome' => $nome, 'email' => $email, 'codice_fiscale' => $cf,
            'data_prima_iscrizione' => '2024-07-24', 'tipo' => 'ordinario', 'stato' => 'attivo'])->save();

        return $s;
    }

    private function aggiungi(Socio $s, string $carica)
    {
        return $this->actingAs($this->presidente)->post(route('governance.store'), [
            'socio_id' => $s->id, 'carica' => $carica, 'dal' => '2026-10-01', 'nominato_da' => 'Assemblea', 'link' => '1',
        ]);
    }

    /** Dati validi per il modulo del socio. */
    private function modulo(Socio $s, array $altro = []): array
    {
        return [
            'tipo' => 'ordinario', 'data_prima_iscrizione' => '2024-07-24',
            'cognome' => $s->cognome, 'nome' => $s->nome, 'codice_fiscale' => $s->codice_fiscale,
            'indirizzo' => 'Via Roma 1', 'cap' => '05014', 'citta' => 'Castel Viscardo', 'provincia' => 'TR',
            'email' => $s->email, 'telefono' => '3331234567',
        ] + $altro;
    }

    private function tascioNelCd(): array
    {
        $tascio = $this->socio('Tascio', 'Luigi', 'luigi@example.it', 'TSCLGU80A01E098G');
        $this->aggiungi($tascio, 'consigliere')->assertSessionHasNoErrors();

        return [$tascio, User::where('socio_id', $tascio->id)->firstOrFail()];
    }

    public function test_cognome_nome_ed_email_dell_utente_vengono_dalla_scheda(): void
    {
        [$tascio, $u] = $this->tascioNelCd();

        $this->actingAs($this->presidente)->put(route('soci.update', $tascio), $this->modulo($tascio, ['nome' => 'Luigino', 'email' => 'Luigi.Nuovo@Example.it']))
            ->assertSessionHasNoErrors();
        $u->refresh();
        $this->assertSame(['Luigino', 'Tascio', 'Luigino Tascio', 'luigi.nuovo@example.it'], [$u->nome, $u->cognome, $u->name, $u->email]);
        // Avviso al vecchio e al nuovo indirizzo
        Mail::assertSent(EmailAccessoCambiata::class, fn ($m) => $m->hasTo('luigi@example.it') && $m->vecchio && $m->nuova === 'luigi.nuovo@example.it');
        Mail::assertSent(EmailAccessoCambiata::class, fn ($m) => $m->hasTo('luigi.nuovo@example.it') && ! $m->vecchio);
        $this->assertSame('lu…@example.it', EmailAccessoCambiata::maschera('luigi.nuovo@example.it'));
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'USER_UPDATED']);

        // Un'altra modifica senza cambio dell'email: nessun avviso nuovo
        Mail::fake();
        $this->actingAs($this->presidente)->put(route('soci.update', $tascio->fresh()), $this->modulo($tascio->fresh(), ['telefono' => '3339999999']))
            ->assertSessionHasNoErrors();
        Mail::assertNotSent(EmailAccessoCambiata::class);
    }

    public function test_l_email_di_chi_entra_non_puo_essere_di_un_altro_utente(): void
    {
        [$tascio] = $this->tascioNelCd();

        // L'email del Presidente sulla scheda di Tascio: errore nel modulo
        $this->actingAs($this->presidente)->put(route('soci.update', $tascio), $this->modulo($tascio, ['email' => 'ANDREA@example.it']))
            ->assertSessionHasErrors(['email' => 'Questa email è già quella con cui entra nel gestionale Andrea Papini: ognuno entra con la sua.']);
        $this->assertSame('luigi@example.it', User::where('socio_id', $tascio->id)->value('email'));

        // Un socio senza accesso può avere l'email di un altro (genitori, famiglie)
        $moglie = $this->socio('Rossi', 'Anna', 'luigi@example.it', 'RSSNNA80A41E098Y');
        $this->actingAs($this->presidente)->put(route('soci.update', $moglie), $this->modulo($moglie))->assertSessionHasNoErrors();
        // ...ma con quella email non entra nel CD
        $this->aggiungi($moglie, 'consigliere')->assertSessionHasErrors('socio_id');
        $this->assertSame(2, GovernanceCd::where('in_carica', true)->count());
    }

    public function test_pagina_utenti_l_email_del_socio_si_cambia_nella_scheda(): void
    {
        [$tascio, $u] = $this->tascioNelCd();

        $this->actingAs($this->presidente)->get(route('utenti.edit', $u))->assertOk()
            ->assertSee("È l'email della scheda del socio: si cambia lì", false)->assertDontSee('name="email"', false);
        // Un'email mandata a mano non conta: resta quella della scheda
        $this->actingAs($this->presidente)->put(route('utenti.update', $u), ['email' => 'altro@example.it', 'attivo' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('luigi@example.it', $u->fresh()->email);
        // L'utente esterno ha ancora il suo campo
        $rev = User::factory()->conRuoli('revisore')->create(['email' => 'rev@example.it']);
        $this->actingAs($this->presidente)->get(route('utenti.edit', $rev))->assertOk()->assertSee('name="email"', false);
    }

    public function test_uscita_dal_cd_elimina_l_utente_e_il_rientro_lo_riporta(): void
    {
        [$tascio, $u] = $this->tascioNelCd();
        $membro = GovernanceCd::where('socio_id', $tascio->id)->first();

        $this->actingAs($this->presidente)->put(route('governance.togli', $membro), ['al' => '2026-10-15', 'motivo' => 'dimissioni'])
            ->assertSessionHasNoErrors();
        $u->refresh();
        $this->assertTrue($u->eliminato());
        $this->assertFalse($u->attivo);
        $this->assertSame([], $u->codiciRuoli());
        $this->assertSame("utente-{$u->id}@eliminato.invalid", $u->email);
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'UTENTE_ELIMINATO', 'entita_id' => $u->id]);
        // Sparisce dalla pagina Utenti e dalla sua scheda
        $this->actingAs($this->presidente)->get(route('utenti.index'))->assertOk()
            ->assertViewHas('utenti', fn ($elenco) => ! $elenco->contains('id', $u->id) && $elenco->contains('id', $this->presidente->id));
        $this->actingAs($this->presidente)->get(route('utenti.edit', $u))->assertNotFound();
        // Non entra più
        $this->actingAs($u)->get(route('dashboard'))->assertRedirect(route('login'));
        // La sua email si può usare (è stata liberata)
        $this->actingAs($this->presidente)->put(route('soci.update', $tascio), $this->modulo($tascio, ['email' => 'luigi2@example.it']))->assertSessionHasNoErrors();

        // Rientra nel CD: torna lo stesso utente, con l'email della scheda
        $this->aggiungi($tascio->fresh(), 'consigliere')->assertSessionHasNoErrors();
        $u->refresh();
        $this->assertSame([false, true, 'luigi2@example.it', ['consigliere']], [$u->eliminato(), $u->attivo, $u->email, $u->codiciRuoli()]);
        $this->assertSame(1, User::where('socio_id', $tascio->id)->count());
    }

    public function test_chi_non_e_piu_socio_non_e_piu_utente(): void
    {
        [$tascio, $u] = $this->tascioNelCd();
        // Resta utente come Amministratore, fuori dal CD
        $u->assegnaRuoli(['consigliere', 'amministratore']);
        $membro = GovernanceCd::where('socio_id', $tascio->id)->first();
        $this->actingAs($this->presidente)->put(route('governance.togli', $membro), ['al' => '2026-10-15', 'motivo' => 'dimissioni'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['amministratore'], $u->fresh()->codiciRuoli());
        $this->assertFalse($u->fresh()->eliminato());

        // Cessazione: l'utente va via anche con l'Amministratore
        $this->actingAs($this->presidente)->post(route('soci.cessazione', $tascio), ['motivo_cancellazione' => 'Dimissioni volontarie', 'data_cancellazione' => '2026-10-20'])
            ->assertSessionHas('success', 'Cessazione registrata. Il suo accesso al gestionale è stato tolto.');
        $u->refresh();
        $this->assertTrue($u->eliminato());
        $this->assertSame([], $u->codiciRuoli());
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'UTENTE_ELIMINATO', 'entita_id' => $u->id]);
    }

    public function test_scheda_eliminata_toglie_l_accesso(): void
    {
        // Un utente collegato a un socio che non è mai stato nel CD (dati vecchi)
        $s = $this->socio('Verdi', 'Luca', 'luca@example.it', 'VRDLCU90E10L117B');
        $u = User::factory()->conRuoli('amministratore')->create(['email' => 'luca@example.it']);
        $u->forceFill(['socio_id' => $s->id])->save();

        $this->actingAs($this->presidente)->delete(route('soci.destroy', $s), ['motivo' => 'Inserito due volte per errore'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($s->fresh() === null || Socio::withTrashed()->find($s->id)->trashed());
        $this->assertTrue($u->fresh()->eliminato());
    }
}
