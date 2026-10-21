<?php

namespace Tests\Feature;

use App\Mail\LinkPassword;
use App\Mail\OperazioneAvviso;
use App\Models\GovernanceCd;
use App\Models\Socio;
use App\Models\User;
use App\Services\RinnoviQuota;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Consiglio Direttivo e utenti che ne nascono (v0.42.0, tavole CD-1…CD-4,
 * analisi-collaudo-O.md §15), più le correzioni piccole del collaudo O
 * (O3.4, O3.5, O9.1).
 */
class ConsiglioDirettivoTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class]);
        Mail::fake();
        // Come sul server: un Presidente di prova, non ancora collegato a un socio
        $this->presidente = User::factory()->conRuoli('presidente')->create(['name' => 'Andrea Papini', 'email' => 'andrea@example.it']);
    }

    private function socio(string $cognome, string $nome, ?string $email, string $cf, string $stato = 'attivo'): Socio
    {
        $s = new Socio();
        $s->forceFill(['cognome' => $cognome, 'nome' => $nome, 'email' => $email, 'codice_fiscale' => $cf,
            'data_prima_iscrizione' => '2024-07-24', 'tipo' => 'ordinario', 'stato' => $stato])->save();

        return $s;
    }

    private function aggiungi(User $chi, Socio $s, string $carica, array $altro = [])
    {
        return $this->actingAs($chi)->post(route('governance.store'), [
            'socio_id' => $s->id, 'carica' => $carica, 'dal' => '2026-11-16', 'nominato_da' => 'Assemblea del 16/11/2026', 'link' => '1',
        ] + $altro);
    }

    /** CD completo: Papini Presidente (utente esistente), Zanetti Segretario e Tesoriere, Tascio Consigliere. */
    private function cdCompleto(): array
    {
        $papini = $this->socio('Papini', 'Andrea', 'Andrea@Example.it', 'PPNNDR80A01E098A');
        $zanetti = $this->socio('Zanetti', 'Paolo', 'paolo@example.it', 'ZNTPLA70A01E098B');
        $tascio = $this->socio('Tascio', 'Luigi', 'luigi@example.it', 'TSCLGU80A01E098C');
        $this->aggiungi($this->presidente, $papini, 'presidente')->assertSessionHasNoErrors();
        $this->aggiungi($this->presidente, $zanetti, 'segretario_tesoriere')->assertSessionHasNoErrors();
        $this->aggiungi($this->presidente, $tascio, 'consigliere')->assertSessionHasNoErrors();

        return [$papini, $zanetti, $tascio];
    }

    public function test_aggiungere_i_membri_crea_o_collega_gli_utenti(): void
    {
        [$papini, $zanetti, $tascio] = $this->cdCompleto();

        // Papini: utente esistente collegato per email, senza doppioni
        $this->assertSame(3, User::count()); // il Presidente di prova + 2 nuovi
        $p = $this->presidente->fresh();
        $this->assertSame($papini->id, $p->socio_id);
        $this->assertSame(['presidente'], $p->codiciRuoli());
        // Zanetti: utente nuovo dalla scheda, due ruoli, link inviato
        $z = User::where('socio_id', $zanetti->id)->firstOrFail();
        $this->assertSame(['Paolo', 'Zanetti', 'paolo@example.it', true], [$z->nome, $z->cognome, $z->email, $z->attivo]);
        $this->assertSame(['segretario', 'tesoriere'], $z->codiciRuoli());
        Mail::assertSent(LinkPassword::class, fn ($m) => $m->hasTo('paolo@example.it'));
        Mail::assertSent(LinkPassword::class, fn ($m) => $m->hasTo('luigi@example.it'));
        // Carica come codice ed etichetta (voto del CD, nome del Tesoriere sulle ricevute)
        $this->assertDatabaseHas('gl_governance_cd', ['socio_id' => $zanetti->id, 'carica_codice' => 'segretario_tesoriere', 'carica' => 'Segretario e Tesoriere', 'in_carica' => 1]);
        $this->assertSame('Paolo Zanetti', RinnoviQuota::tesoriere());
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'CD_MEMBRO_AGGIUNTO', 'livello_rischio' => 'HIGH']);
        // Avviso a Zanetti (Segretario, vede il CD) per l'aggiunta di Tascio fatta dal Presidente
        Mail::assertSent(OperazioneAvviso::class, fn ($m) => $m->hasTo('paolo@example.it') && $m->righe[0]['evento'] === 'CD_MEMBRO_AGGIUNTO');

        // Pagina
        $this->actingAs($this->presidente)->get(route('governance.index'))->assertOk()
            ->assertSee('Consiglio')->assertSee('Segretario e Tesoriere')->assertSee('Tascio Luigi')->assertSee('Aggiungi un membro');
        // Il Consigliere legge, senza pulsanti
        $t = User::where('socio_id', $tascio->id)->first();
        $this->actingAs($t)->get(route('governance.index'))->assertOk()->assertSee('Sola lettura')->assertDontSee('Aggiungi un membro');
        $this->actingAs($t)->post(route('governance.store'), [])->assertRedirect();
        $this->assertSame(3, GovernanceCd::where('in_carica', true)->count());
        // Voce di menu
        $this->actingAs($t)->get(route('dashboard'))->assertSee(route('governance.index'));
    }

    public function test_regole_dell_aggiunta(): void
    {
        [$papini, $zanetti] = $this->cdCompleto();
        $nuovo = $this->socio('Verdi', 'Luca', 'luca@example.it', 'VRDLCU90E10L117B');

        // Carica occupata
        $this->aggiungi($this->presidente, $nuovo, 'tesoriere')->assertSessionHasErrors('carica')->assertSessionHas('modale', 'aggiungi');
        // Già nel CD
        $this->aggiungi($this->presidente, $zanetti, 'consigliere')->assertSessionHasErrors('socio_id');
        // Senza email
        $senza = $this->socio('Bianchi', 'Anna', null, 'BNCNNA75B43E098D');
        $this->aggiungi($this->presidente, $senza, 'consigliere')->assertSessionHasErrors(['socio_id' => "Bianchi Anna non ha l'email nella scheda: aggiungila prima, serve per entrare nel gestionale e per i voti del CD."]);
        // Non attivo
        $cessato = $this->socio('Rossi', 'Mario', 'mario@example.it', 'RSSMRA80A01E098E', 'cancellato');
        $this->aggiungi($this->presidente, $cessato, 'consigliere')->assertSessionHasErrors('socio_id');
        // Il Segretario non dà la carica di Presidente, ma aggiunge un Consigliere
        $z = User::where('socio_id', $zanetti->id)->first();
        $this->aggiungi($z, $nuovo, 'vicepresidente')->assertSessionHasNoErrors();
        $this->assertSame(['vicepresidente'], User::where('socio_id', $nuovo->id)->first()->codiciRuoli());
        // Cinque al massimo
        $quinto = $this->socio('Neri', 'Sofia', 'sofia@example.it', 'NRESFO90A41E098F');
        $this->aggiungi($this->presidente, $quinto, 'consigliere')->assertSessionHasNoErrors();
        $sesto = $this->socio('Gialli', 'Ugo', 'ugo@example.it', 'GLLGUO90A01E098G');
        $this->aggiungi($this->presidente, $sesto, 'consigliere')->assertSessionHasErrors(['socio_id' => 'Il Consiglio Direttivo ha già cinque membri, il massimo dello statuto (art. 15.1).']);
        // Un Revisore non entra nel CD
        $rev = User::factory()->conRuoli('revisore')->create(['email' => 'ugo@example.it']);
        GovernanceCd::where('socio_id', $quinto->id)->update(['in_carica' => false]);
        $this->aggiungi($this->presidente, $sesto, 'consigliere')->assertSessionHasErrors('socio_id');
        $this->assertSame(['revisore'], $rev->fresh()->codiciRuoli());
    }

    public function test_presidente_di_prova_sostituito_esce(): void
    {
        // R1.1: il Presidente di prova dà la carica a un'altra persona, perde il ruolo e va all'accesso
        $s = $this->socio('Rossi', 'Mario', 'mario@example.it', 'RSSMRA80A01E098E');
        $this->aggiungi($this->presidente, $s, 'presidente')->assertRedirect(route('login'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'è stato disattivato'));
        $this->assertGuest();
        $this->assertFalse($this->presidente->fresh()->attivo);
        $this->assertSame(['presidente'], User::where('socio_id', $s->id)->first()->codiciRuoli());
    }

    public function test_il_segretario_non_cede_la_sua_carica(): void
    {
        [$papini, $zanetti, $tascio] = $this->cdCompleto();
        $z = User::where('socio_id', $zanetti->id)->first();
        // R1.2: dare il Segretario a Tascio cambierebbe anche la carica di chi lo fa
        $this->actingAs($z)->put(route('governance.carica', GovernanceCd::where('socio_id', $tascio->id)->first()), [
            'nuova_carica' => 'segretario', 'dal_carica' => '2027-01-10', 'motivazione' => 'Divisione delle cariche',
        ])->assertSessionHasErrors(['carica' => 'La tua carica la cambia il Presidente.']);
        $this->assertSame(['segretario', 'tesoriere'], $z->fresh()->codiciRuoli());
    }

    public function test_ruolo_unico_tolto_agli_utenti_di_prova(): void
    {
        // Dopo l'azzeramento restano utenti di prova con ruoli: il nuovo Presidente li toglie al vecchio
        $vecchio = User::factory()->conRuoli('segretario', 'tesoriere')->create(['email' => 'vecchio@example.it']);
        $s = $this->socio('Zanetti', 'Paolo', 'paolo@example.it', 'ZNTPLA70A01E098B');
        $this->aggiungi($this->presidente, $s, 'tesoriere')->assertSessionHasNoErrors();
        $this->assertSame(['segretario'], $vecchio->fresh()->codiciRuoli());
        $this->assertTrue($vecchio->fresh()->attivo);
        $s2 = $this->socio('Tascio', 'Luigi', 'luigi@example.it', 'TSCLGU80A01E098C');
        $this->aggiungi($this->presidente, $s2, 'segretario')->assertSessionHasNoErrors();
        $this->assertSame([], $vecchio->fresh()->codiciRuoli());
        $this->assertFalse($vecchio->fresh()->attivo);
    }

    public function test_passaggio_della_presidenza(): void
    {
        [$papini, $zanetti, $tascio] = $this->cdCompleto();
        $membroTascio = GovernanceCd::where('socio_id', $tascio->id)->first();
        $z = User::where('socio_id', $zanetti->id)->first();

        // Il Segretario non passa la Presidenza
        $this->actingAs($z)->put(route('governance.carica', $membroTascio), [
            'nuova_carica' => 'presidente', 'dal_carica' => '2027-01-10', 'motivazione' => 'Nuovo Presidente eletto', 'altri' => 'consigliere',
        ])->assertSessionHasErrors('carica')->assertSessionHas('modale', "carica-{$membroTascio->id}");
        // Il Presidente non cambia carica da solo
        $membroPapini = GovernanceCd::where('socio_id', $papini->id)->first();
        $this->actingAs($this->presidente)->put(route('governance.carica', $membroPapini), [
            'nuova_carica' => 'consigliere', 'dal_carica' => '2027-01-10', 'motivazione' => 'Lascia la presidenza',
        ])->assertSessionHasErrors('carica');
        // Il Presidente la passa a Tascio e resta Consigliere
        $this->actingAs($this->presidente)->put(route('governance.carica', $membroTascio), [
            'nuova_carica' => 'presidente', 'dal_carica' => '2027-01-10', 'motivazione' => 'Elezione del nuovo Presidente', 'altri' => 'consigliere',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('presidente', $membroTascio->fresh()->carica_codice);
        $this->assertSame('consigliere', $membroPapini->fresh()->carica_codice);
        $this->assertSame(['consigliere'], $this->presidente->fresh()->codiciRuoli());
        $this->assertSame(['presidente'], User::where('socio_id', $tascio->id)->first()->codiciRuoli());
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'CD_CARICA', 'livello_rischio' => 'HIGH']);

        // Segretario e Tesoriere che cede il Tesoriere resta Segretario
        $membroZ = GovernanceCd::where('socio_id', $zanetti->id)->first();
        $this->actingAs(User::where('socio_id', $tascio->id)->first())->put(route('governance.carica', $membroPapini), [
            'nuova_carica' => 'tesoriere', 'dal_carica' => '2027-01-10', 'motivazione' => 'Divisione delle cariche',
        ])->assertSessionHasNoErrors();
        $this->assertSame('segretario', $membroZ->fresh()->carica_codice);
        $this->assertSame(['segretario'], $z->fresh()->codiciRuoli());
        $this->assertSame(['tesoriere'], $this->presidente->fresh()->codiciRuoli());
    }

    public function test_l_amministratore_passa_la_presidenza(): void
    {
        [$papini, $zanetti, $tascio] = $this->cdCompleto();
        $z = User::where('socio_id', $zanetti->id)->first();
        $z->assegnaRuoli(['segretario', 'tesoriere', 'amministratore']);
        $membroTascio = GovernanceCd::where('socio_id', $tascio->id)->first();
        $this->actingAs($z->fresh())->put(route('governance.carica', $membroTascio), [
            'nuova_carica' => 'presidente', 'dal_carica' => '2027-01-10', 'motivazione' => 'Il Presidente si è dimesso', 'altri' => 'esce',
        ])->assertSessionHasNoErrors();
        $this->assertFalse(GovernanceCd::where('socio_id', $papini->id)->first()->in_carica);
        $this->assertSame([], $this->presidente->fresh()->codiciRuoli());
        $this->assertFalse($this->presidente->fresh()->attivo);
        $this->assertSame(['presidente'], User::where('socio_id', $tascio->id)->first()->codiciRuoli());
    }

    public function test_togliere_un_membro(): void
    {
        [$papini, $zanetti, $tascio] = $this->cdCompleto();
        $membroTascio = GovernanceCd::where('socio_id', $tascio->id)->first();
        $membroPapini = GovernanceCd::where('socio_id', $papini->id)->first();
        $membroZ = GovernanceCd::where('socio_id', $zanetti->id)->first();
        $z = User::where('socio_id', $zanetti->id)->first();

        // Il Presidente non si toglie; nessuno toglie se stesso
        $this->actingAs($this->presidente)->put(route('governance.togli', $membroPapini), ['al' => '2027-03-10', 'motivo' => 'dimissioni'])->assertSessionHasErrors('al');
        $this->actingAs($z)->put(route('governance.togli', $membroZ), ['al' => '2027-03-10', 'motivo' => 'dimissioni'])->assertSessionHasErrors('al');
        // Data prima dell'ingresso
        $this->actingAs($this->presidente)->put(route('governance.togli', $membroTascio), ['al' => '2026-01-01', 'motivo' => 'dimissioni'])->assertSessionHasErrors('al');
        // Tascio esce: utente eliminato (logicamente, v0.44.1), storico
        $this->actingAs($z)->put(route('governance.togli', $membroTascio), ['al' => '2027-03-10', 'motivo' => 'dimissioni', 'nota' => 'Lettera dell\'8 marzo'])
            ->assertSessionHasNoErrors();
        $t = User::where('socio_id', $tascio->id)->first();
        $this->assertSame([], $t->codiciRuoli());
        $this->assertFalse($t->attivo);
        $this->assertTrue($t->eliminato());
        $this->assertDatabaseHas('gl_governance_cd', ['id' => $membroTascio->id, 'in_carica' => 0, 'motivo_fine' => 'dimissioni', 'data_fine_mandato' => '2027-03-10']);
        $this->actingAs($this->presidente)->get(route('governance.index'))->assertSee('Usciti dal CD')->assertSee('Tascio Luigi · Consigliere');
        // Con l'Amministratore resta attivo
        $z->assegnaRuoli(['segretario', 'tesoriere', 'amministratore']);
        $this->actingAs($this->presidente)->put(route('governance.togli', $membroZ), ['al' => '2027-03-10', 'motivo' => 'altro', 'nota' => 'Resta solo Amministratore'])->assertSessionHasNoErrors();
        $this->assertSame(['amministratore'], $z->fresh()->codiciRuoli());
        $this->assertTrue($z->fresh()->attivo);
        // Il socio nel CD non si cessa né si elimina
        $this->actingAs($this->presidente)->post(route('soci.cessazione', $papini), ['motivo_cancellazione' => 'Dimissioni volontarie', 'data_cancellazione' => now()->toDateString()])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'toglilo prima dal CD'));
        $this->actingAs($this->presidente)->delete(route('soci.destroy', $tascio), ['motivo' => 'Inserito due volte per errore'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Consiglio Direttivo'));
    }

    public function test_correzioni_del_collaudo_ob(): void
    {
        [$papini, $zanetti, $tascio] = $this->cdCompleto();
        $membroTascio = GovernanceCd::where('socio_id', $tascio->id)->first();

        // OB4: «Altro» chiede la nota
        $this->actingAs($this->presidente)->put(route('governance.togli', $membroTascio), ['al' => '2027-03-10', 'motivo' => 'altro'])
            ->assertSessionHasErrors(['nota' => 'Con il motivo «Altro» scrivi nella nota di che cosa si tratta.']);
        $this->assertTrue($membroTascio->fresh()->in_carica);

        // OB4: membro con la scheda del socio eliminata (prove K7): niente errore 500
        $tascio->delete();
        $this->actingAs($this->presidente)->get(route('governance.index'))->assertOk()->assertSee('Tascio Luigi')->assertSee('Scheda eliminata');
        $this->actingAs($this->presidente)->put(route('governance.carica', $membroTascio), [
            'nuova_carica' => 'vicepresidente', 'dal_carica' => '2027-01-10', 'motivazione' => 'Prova su scheda eliminata',
        ])->assertSessionHasErrors(['carica' => 'La scheda del socio è stata eliminata: toglilo dal CD.']);
        $this->actingAs($this->presidente)->put(route('governance.togli', $membroTascio), ['al' => '2027-03-10', 'motivo' => 'altro', 'nota' => 'Scheda eliminata per errore'])
            ->assertRedirect(route('governance.index'))->assertSessionHas('success', 'Tascio Luigi non è più nel Consiglio Direttivo.');

        // OB5: nel CD solo maggiorenni (e il menu non li propone)
        $minore = $this->socio('Neri', 'Sofia', 'sofia@example.it', 'NRESFO12A41E098F');
        $minore->forceFill(['data_nascita' => now()->subYears(15)->toDateString()])->save();
        $this->actingAs($this->presidente)->get(route('governance.index'))->assertDontSee('Neri Sofia');
        $this->aggiungi($this->presidente, $minore, 'consigliere')
            ->assertSessionHasErrors(['socio_id' => 'Neri Sofia è minorenne: nel Consiglio Direttivo entrano solo soci maggiorenni.']);

        // OB4: membro senza utente (email del socio diversa): si collega o si crea dopo aver corretto l'email
        $verdi = $this->socio('Verdi', 'Luca', 'luca@example.it', 'VRDLCU90E10L117B');
        $m = GovernanceCd::create(['socio_id' => $verdi->id, 'carica' => 'Consigliere', 'data_inizio_mandato' => '2026-09-29', 'in_carica' => true]);
        $m->forceFill(['carica_codice' => 'consigliere'])->save();
        $esistente = User::factory()->conRuoli('consigliere')->create(['email' => 'luca@example.it', 'name' => 'Luca V']);
        $this->actingAs($this->presidente)->get(route('governance.index'))->assertSee('Crea o collega l');
        $this->actingAs($this->presidente)->post(route('governance.utente', $m))
            ->assertSessionHas('success', 'Verdi Luca: collegato il suo utente.');
        $this->assertSame($verdi->id, $esistente->fresh()->socio_id);
        $this->assertSame(['Luca', 'Verdi'], [$esistente->fresh()->nome, $esistente->fresh()->cognome]);
        $this->actingAs($this->presidente)->post(route('governance.utente', $m))->assertSessionHas('error', 'Verdi Luca ha già il suo utente.');
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'CD_UTENTE']);
    }

    public function test_utenti_esterni_e_accesso_disattivato(): void
    {
        // O9.1: con email e password giuste un utente disattivato lo sa; con la password sbagliata no
        $u = User::factory()->conRuoli('consigliere')->create(['email' => 'off@example.it', 'password' => Hash::make('una frase lunga'), 'attivo' => false]);
        $this->post(route('login'), ['email' => 'off@example.it', 'password' => 'una frase lunga'])
            ->assertSessionHasErrors(['email' => 'Utente non attivo nel gestionale: rivolgiti al Presidente.']);
        $this->post(route('login'), ['email' => 'off@example.it', 'password' => 'sbagliata'])
            ->assertSessionHasErrors(['email' => 'Email o password non corrette.']);
        $this->assertGuest();

        // O3.4: link già usato o sbagliato, detto subito all'apertura
        $this->get(route('password.scegli', ['token' => 'falso', 'email' => 'andrea@example.it']))->assertOk()
            ->assertSee('Il link non è più valido')->assertDontSee('Salva e accedi');
        $token = Password::broker()->createToken($this->presidente);
        $this->get(route('password.scegli', ['token' => $token, 'email' => 'andrea@example.it']))->assertOk()
            ->assertSee('Salva e accedi')->assertSee('aria-controls="password_confirmation"', false); // O3.5: occhio su «Ripeti»
    }
}
