<?php

namespace Tests\Feature;

use App\Models\Domanda;
use App\Models\User;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Assenso del minore che ha compiuto 14 anni al proprio tesseramento
 * (art. 16, c. 2, D.Lgs. 36/2021; statuto art. 6.3; v0.31.0,
 * domande-iscrizione.md §3). Gira su MySQL come VotoCdTest.
 */
class AssensoMinoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class]);
        \App\Models\Impostazione::set('iscrizioni_aperte', true, 'boolean');
        Mail::fake();
        Carbon::setTestNow('2026-10-04 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Codice fiscale valido di una ragazza nata a Terni nella data indicata ("RSSGLI" = Rossi Giulia). */
    private static function cfMinore(string $nascita): string
    {
        $d = Carbon::parse($nascita);
        $mesi = 'ABCDEHLMPRST';
        $base = 'RSSGLI' . $d->format('y') . $mesi[$d->month - 1] . str_pad((string) ($d->day + 40), 2, '0', STR_PAD_LEFT) . 'L117';

        $dispari = [1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23];
        $somma = 0;
        foreach (str_split($base) as $i => $c) {
            $v = ctype_digit($c) ? (int) $c : ord($c) - 65;
            $somma += $i % 2 === 0 ? $dispari[$v] : $v;
        }

        return $base . chr(65 + $somma % 26);
    }

    private function passo1(string $nascita): void
    {
        $this->post(route('iscrizione.dati.salva'), [
            'chi' => 'minore', 'nome' => 'Giulia', 'cognome' => 'Rossi', 'codice_fiscale' => self::cfMinore($nascita),
            'genitore_nome' => 'Luca', 'genitore_cognome' => 'Verdi', 'genitore_codice_fiscale' => 'VRDLCU90E10L117B',
            'genitore_email' => 'luca@esempio.it', 'genitore_telefono' => '3407654321',
            'documento_tipo' => 'carta_identita', 'documento_numero' => 'CA98765ZZ',
            'indirizzo' => 'Via Garibaldi 5', 'cap' => '05014', 'citta' => 'Castel Viscardo', 'provincia' => 'TR',
        ])->assertSessionHasNoErrors()->assertRedirect(route('iscrizione.riepilogo'));
    }

    public function test_regola_dei_14_anni(): void
    {
        $oggi = Carbon::parse('2026-10-04');
        $this->assertTrue(Domanda::assensoRichiesto('2012-10-04', $oggi), 'il giorno del 14° compleanno conta già');
        $this->assertFalse(Domanda::assensoRichiesto('2012-10-05', $oggi), 'il giorno prima no');
        $this->assertTrue(Domanda::assensoRichiesto('2008-10-05', $oggi), '17 anni');
        $this->assertFalse(Domanda::assensoRichiesto('2008-10-04', $oggi), 'maggiorenne: niente assenso del minore');
        $this->assertFalse(Domanda::assensoRichiesto(null, $oggi));
        $this->assertSame(15, Domanda::anniCompiuti('2011-02-05', $oggi));
        $this->assertSame(13, Domanda::anniCompiuti('2012-10-05', $oggi));
        $this->assertStringContainsString('foto e video', Domanda::testoAssenso('Giulia Rossi', true));
        $this->assertStringNotContainsString('foto e video', Domanda::testoAssenso('Giulia Rossi', false));
    }

    public function test_quindicenne_senza_assenso_non_parte_con_assenso_si(): void
    {
        $this->passo1('2011-02-05');

        $this->get(route('iscrizione.riepilogo'))->assertOk()
            ->assertSee('Dichiarazione del minore')
            ->assertSee('Giulia ha 15 anni')
            ->assertSee('Art. 16, c. 2, D.Lgs. 36/2021', false);

        // senza la dichiarazione del minore: errore, nessuna domanda
        $this->post(route('iscrizione.invia'), ['impronta_testi' => '0|1.0|1.0', 'dichiarazione' => '1', 'informativa' => '1', 'foto_video' => '1'])
            ->assertSessionHasErrors(['assenso_minore' => 'Serve la dichiarazione di Giulia: dai 14 anni il tesseramento richiede il suo assenso.']);
        $this->assertSame(0, Domanda::count());

        // con la dichiarazione: registrata con l'ora dell'invio
        $this->post(route('iscrizione.invia'), ['impronta_testi' => '0|1.0|1.0', 'dichiarazione' => '1', 'informativa' => '1', 'foto_video' => '1', 'assenso_minore' => '1'])
            ->assertRedirect(route('iscrizione.inviata'));

        $d = Domanda::firstOrFail();
        $this->assertTrue($d->assenso_minore_il->equalTo($d->created_at));
        $this->assertTrue($d->richiedeAssensoMinore());
        $this->assertSame(15, $d->etaAllaDomanda());
        $this->assertStringContainsString('foto e video', $d->testoAssensoMinore());
        $this->assertNotNull($d->ricevuta_path);

        // scheda della domanda e verbale
        $presidente = User::factory()->create(['ruolo' => 'presidente', 'attivo' => true]);
        $this->actingAs($presidente)->get(route('domande.show', $d))->assertOk()
            ->assertSee('Assenso del minore')->assertSee('Prestato il 04/10/2026, 12:00')->assertSee('compresi foto e video');
        $verbale = view('verbali.ammissione-telematica', ['domanda' => $d->load('voti')])->render();
        $this->assertStringContainsString('ha prestato personalmente il proprio assenso al tesseramento', $verbale);
        $this->assertStringContainsString('art. 16, c. 2, D.Lgs. 36/2021', $verbale);
    }

    public function test_tredicenne_non_serve_e_non_si_registra(): void
    {
        $this->passo1('2012-10-05');

        $this->get(route('iscrizione.riepilogo'))->assertOk()->assertDontSee('Dichiarazione del minore');

        // anche se arrivasse il campo, sotto i 14 anni non si registra
        $this->post(route('iscrizione.invia'), ['impronta_testi' => '0|1.0|1.0', 'dichiarazione' => '1', 'informativa' => '1', 'assenso_minore' => '1'])
            ->assertRedirect(route('iscrizione.inviata'));

        $d = Domanda::firstOrFail();
        $this->assertNull($d->assenso_minore_il);
        $this->assertFalse($d->richiedeAssensoMinore());

        $presidente = User::factory()->create(['ruolo' => 'presidente', 'attivo' => true]);
        $this->actingAs($presidente)->get(route('domande.show', $d))->assertOk()->assertSee('Non richiesto (meno di 14 anni)');
    }

    public function test_compie_14_anni_tra_il_passo_1_e_l_invio(): void
    {
        Carbon::setTestNow('2026-10-04 23:50:00');
        $this->passo1('2012-10-05');
        $this->get(route('iscrizione.riepilogo'))->assertDontSee('Dichiarazione del minore');

        // dopo la mezzanotte ha 14 anni: la dichiarazione diventa obbligatoria
        Carbon::setTestNow('2026-10-05 00:10:00');
        $this->post(route('iscrizione.invia'), ['impronta_testi' => '0|1.0|1.0', 'dichiarazione' => '1', 'informativa' => '1'])
            ->assertSessionHasErrors('assenso_minore');
        $this->get(route('iscrizione.riepilogo'))->assertSee('Dichiarazione del minore');
    }

    public function test_adulto_nessuna_dichiarazione_del_minore(): void
    {
        $this->post(route('iscrizione.dati.salva'), [
            'chi' => 'me', 'nome' => 'Luca', 'cognome' => 'Verdi', 'codice_fiscale' => 'VRDLCU90E10L117B',
            'email' => 'luca@esempio.it', 'telefono' => '3407654321',
            'documento_tipo' => 'carta_identita', 'documento_numero' => 'CA98765ZZ',
            'indirizzo' => 'Via Garibaldi 5', 'cap' => '05014', 'citta' => 'Castel Viscardo', 'provincia' => 'TR',
        ]);
        $this->get(route('iscrizione.riepilogo'))->assertOk()->assertDontSee('Dichiarazione del minore');
        $this->post(route('iscrizione.invia'), ['impronta_testi' => '0|1.0|1.0', 'dichiarazione' => '1', 'informativa' => '1', 'assenso_minore' => '1'])
            ->assertRedirect(route('iscrizione.inviata'));
        $this->assertNull(Domanda::firstOrFail()->assenso_minore_il);
    }
}
