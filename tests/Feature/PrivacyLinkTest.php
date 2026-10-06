<?php

namespace Tests\Feature;

use App\Mail\PrivacyLinkInvito;
use App\Models\PrivacyLink;
use App\Models\Socio;
use App\Models\User;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Link personale per l'informativa privacy e foto e video (v0.33.0,
 * privacy-link.md). Gira su MySQL come gli altri test.
 */
class PrivacyLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $segretario;

    private User $lettore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class]);
        Mail::fake();
        $this->segretario = User::factory()->create(['ruolo' => 'segretario', 'attivo' => true]);
        // Revisore con la sezione Soci in sola lettura: vede, non agisce.
        DB::table('gl_ruoli_sezioni')->updateOrInsert(
            ['ruolo_codice' => 'revisore', 'sezione_id' => DB::table('gl_sezioni')->where('codice', 'soci')->value('id')],
            ['sola_lettura' => true]
        );
        $this->lettore = User::factory()->create(['ruolo' => 'revisore', 'attivo' => true]);
    }

    private function socio(array $extra = []): Socio
    {
        static $n = 0;
        $n++;
        $socio = new Socio();
        $socio->forceFill($extra + [
            'nome' => 'Luca', 'cognome' => 'Verdi' . $n, 'sesso' => 'M', 'email' => "luca{$n}@esempio.it",
            'codice_fiscale' => sprintf('VRDLCU90E10L1%02dB', $n), 'data_nascita' => '1990-05-10',
            'data_prima_iscrizione' => '2025-01-01', 'tipo' => 'ordinario', 'stato' => 'attivo',
        ])->save();

        return $socio;
    }

    /** Invia il link dalla scheda e restituisce il token in chiaro catturato dall'email. */
    private function inviaLink(Socio $socio): string
    {
        $this->actingAs($this->segretario)->post(route('soci.privacy.link', $socio))->assertSessionHas('success');
        $token = null;
        Mail::assertSent(PrivacyLinkInvito::class, function (PrivacyLinkInvito $m) use (&$token, $socio) {
            if ($m->socio->id !== $socio->id) {
                return false;
            }
            $url = $m->content()->with['url'];
            $token = substr($url, strrpos($url, '/') + 1);

            return true;
        });
        auth()->logout();

        return $token;
    }

    public function test_invio_dalla_scheda_e_nuovo_invio_annulla_il_precedente(): void
    {
        $socio = $this->socio();
        $primo = $this->inviaLink($socio);
        $this->assertSame(1, PrivacyLink::where('socio_id', $socio->id)->aperti()->count());
        $link = PrivacyLink::firstOrFail();
        $this->assertSame('socio', $link->destinatario);
        $this->assertSame($socio->email, $link->email);
        $this->assertNotSame($primo, $link->token_hash, 'Nel database solo l\'impronta del token.');
        $this->assertDatabaseHas('gl_email_log', ['socio_id' => $socio->id, 'tipo_email' => 'privacy_link', 'esito' => 'inviata']);
        $this->assertDatabaseHas('gl_audit_log', ['evento' => 'PRIVACY_LINK_INVIATO', 'entita_id' => $socio->id]);
        $this->assertSame('in_attesa', $socio->fresh()->statoPrivacy());
        Mail::assertSent(PrivacyLinkInvito::class, fn ($m) => $m->envelope()->subject === 'Informativa privacy: conferma la lettura');

        $this->actingAs($this->segretario)->post(route('soci.privacy.link', $socio))->assertSessionHas('success');
        auth()->logout();
        $this->assertSame(2, PrivacyLink::where('socio_id', $socio->id)->count());
        $this->assertSame(1, PrivacyLink::where('socio_id', $socio->id)->aperti()->count());
        $this->get(route('privacy-link.mostra', $primo))->assertOk()->assertSee('Link non valido');
    }

    public function test_minore_al_tutore_e_senza_email_nessun_link(): void
    {
        $minore = $this->socio(['nome' => 'Giulia', 'sesso' => 'F', 'data_nascita' => now()->subYears(15)->toDateString(),
            'tipo' => 'minorenne', 'email' => null, 'tutore_nome' => 'Marco Rossi', 'tutore_email' => 'marco@esempio.it']);
        $token = $this->inviaLink($minore);
        $this->assertSame('tutore', PrivacyLink::firstOrFail()->destinatario);
        Mail::assertSent(PrivacyLinkInvito::class, fn ($m) => $m->hasTo('marco@esempio.it'));

        $this->get(route('privacy-link.mostra', $token))->assertOk()
            ->assertSee('Gentile Marco Rossi')
            ->assertSee('come genitore di Giulia')
            ->assertSee('dopo averne parlato con lei');

        $senza = $this->socio(['email' => null]);
        $this->actingAs($this->segretario)->post(route('soci.privacy.link', $senza))->assertSessionHas('error');
        $this->assertSame(0, PrivacyLink::where('socio_id', $senza->id)->count());
    }

    public function test_conferma_con_foto_e_riepilogo(): void
    {
        $socio = $this->socio();
        $token = $this->inviaLink($socio);

        $this->get(route('privacy-link.mostra', $token))->assertOk()->assertSee('Dichiaro di aver letto');

        // senza dichiarazione: errore e nulla di registrato
        $this->post(route('privacy-link.conferma', $token), ['foto_video' => '1'])->assertSessionHasErrors('dichiarazione');
        $this->assertNull($socio->fresh()->privacy_accettata_il);

        $this->post(route('privacy-link.conferma', $token), ['dichiarazione' => '1', 'foto_video' => '1'])
            ->assertRedirect(route('privacy-link.mostra', $token));

        $socio->refresh();
        $this->assertSame('link', $socio->privacy_metodo);
        $this->assertSame('1.0', $socio->privacy_versione);
        $this->assertNotNull($socio->privacy_ip);
        $this->assertTrue($socio->foto_video_consenso);
        $this->assertSame('link', $socio->foto_video_origine);
        $this->assertSame('presente', $socio->statoPrivacy());
        $link = PrivacyLink::firstOrFail();
        $this->assertNotNull($link->usato_il);
        $this->assertTrue($link->foto_video);

        $this->get(route('privacy-link.mostra', $token))->assertOk()->assertSee('Grazie, è registrato')->assertSee('Consenso dato');

        // il link vale una volta sola
        $prima = $socio->privacy_accettata_il;
        $this->travel(1)->hours();
        $this->post(route('privacy-link.conferma', $token), ['dichiarazione' => '1']);
        $this->assertEquals($prima, $socio->fresh()->privacy_accettata_il);
        $this->assertTrue($socio->fresh()->foto_video_consenso);
    }

    public function test_spunta_tolta_revoca_foto_e_video(): void
    {
        $socio = $this->socio(['foto_video_consenso' => true, 'foto_video_il' => '2026-01-10 10:00:00', 'foto_video_origine' => 'domanda']);
        $token = $this->inviaLink($socio);
        $this->get(route('privacy-link.mostra', $token))->assertSee('Oggi: consenso dato il 10/01/2026');

        $this->post(route('privacy-link.conferma', $token), ['dichiarazione' => '1']);
        $socio->refresh();
        $this->assertFalse($socio->foto_video_consenso);
        $this->assertSame('link', $socio->foto_video_origine);
        $this->assertTrue($socio->foto_video_il->isToday());
    }

    public function test_revoca_dalla_scheda_e_permessi(): void
    {
        $socio = $this->socio(['foto_video_consenso' => true, 'foto_video_il' => '2026-01-10 10:00:00', 'foto_video_origine' => 'link']);

        $this->actingAs($this->lettore)->post(route('soci.foto-video.revoca', $socio), ['revoca_il' => today()->toDateString(), 'revoca_modo' => 'revoca_email'])->assertForbidden();
        $this->actingAs($this->lettore)->post(route('soci.privacy.link', $socio))->assertForbidden();
        $this->actingAs($this->lettore)->get(route('soci.show', $socio))->assertOk()
            ->assertDontSee('Registra la revoca')->assertDontSee('Invia il link');

        $this->actingAs($this->segretario)->post(route('soci.foto-video.revoca', $socio), ['revoca_modo' => 'altro'])
            ->assertSessionHasErrors(['revoca_il', 'revoca_modo']);
        $this->actingAs($this->segretario)->post(route('soci.foto-video.revoca', $socio), ['revoca_il' => '2026-01-09', 'revoca_modo' => 'revoca_email'])
            ->assertSessionHasErrors('revoca_il');
        $this->actingAs($this->segretario)->post(route('soci.foto-video.revoca', $socio), ['revoca_il' => today()->toDateString(), 'revoca_modo' => 'revoca_email'])
            ->assertSessionHas('success');
        $socio->refresh();
        $this->assertFalse($socio->foto_video_consenso);
        $this->assertSame('revoca_email', $socio->foto_video_origine);
        $this->assertDatabaseHas('gl_audit_log', ['entita_tipo' => 'Socio', 'entita_id' => $socio->id, 'livello_rischio' => 'MEDIUM']);

        $this->actingAs($this->segretario)->get(route('soci.show', $socio))->assertOk()
            ->assertSee('Foto e video')->assertSee('Revocato il')->assertDontSee('Registra la revoca</button>', false);
    }

    public function test_versione_precedente_e_invio_in_blocco(): void
    {
        $senza = $this->socio();
        $vecchia = $this->socio(['privacy_accettata_il' => '2026-01-01 10:00:00', 'privacy_versione' => '0.9', 'privacy_metodo' => 'presenza']);
        $inRegola = $this->socio(['privacy_accettata_il' => '2026-01-01 10:00:00', 'privacy_versione' => '1.0', 'privacy_metodo' => 'presenza']);
        $senzaEmail = $this->socio(['email' => null]);
        $cessato = $this->socio(['stato' => 'cancellato']);

        $this->assertSame('da_aggiornare', $vecchia->statoPrivacy());
        $this->assertSame('presente', $inRegola->statoPrivacy());
        $this->assertSame('mancante', $senza->statoPrivacy());

        $this->actingAs($this->segretario)->get(route('soci.index'))->assertOk()->assertSee('Link privacy')->assertSee('Invia il link privacy');

        // un link già aperto: senza spunta non si rimanda
        $this->actingAs($this->segretario)->post(route('soci.privacy.link', $senza));
        $this->actingAs($this->segretario)->post(route('soci.privacy-link.tutti'))->assertSessionHas('success', 'Inviato 1 link privacy.');
        $this->assertSame(1, PrivacyLink::where('socio_id', $vecchia->id)->count());
        $this->assertSame(1, PrivacyLink::where('socio_id', $senza->id)->count());
        foreach ([$inRegola, $senzaEmail, $cessato] as $s) {
            $this->assertSame(0, PrivacyLink::where('socio_id', $s->id)->count());
        }
        // secondo clic: nessun doppione
        $this->actingAs($this->segretario)->post(route('soci.privacy-link.tutti'))->assertSessionHas('success', 'Nessun link da inviare.');

        // con la spunta: link nuovi a chi li aveva, i vecchi annullati
        $this->actingAs($this->segretario)->post(route('soci.privacy-link.tutti'), ['anche_in_attesa' => '1'])->assertSessionHas('success', 'Inviati 2 link privacy.');
        $this->assertSame(2, PrivacyLink::where('socio_id', $senza->id)->count());
        $this->assertSame(1, PrivacyLink::where('socio_id', $senza->id)->aperti()->count());

        $this->actingAs($this->lettore)->post(route('soci.privacy-link.tutti'))->assertForbidden();
        $this->actingAs($this->lettore)->get(route('soci.index'))->assertOk()->assertDontSee('Invia il link privacy');
    }

    public function test_scheda_mostra_gli_stati_della_privacy(): void
    {
        $socio = $this->socio();
        $this->actingAs($this->segretario)->get(route('soci.show', $socio))->assertOk()
            ->assertSee('Il link andrà a ' . $socio->email)->assertSee('Invia il link');
        $this->actingAs($this->segretario)->post(route('soci.privacy.link', $socio));
        $this->actingAs($this->segretario)->get(route('soci.show', $socio))->assertOk()
            ->assertSee('In attesa')->assertSee('non ancora usato')->assertSee('Invia di nuovo');

        $senza = $this->socio(['email' => null]);
        $this->actingAs($this->segretario)->get(route('soci.show', $senza))->assertOk()->assertSee('Nessuna email');
    }

    public function test_token_manomesso_e_socio_non_attivo(): void
    {
        $this->get(route('privacy-link.mostra', str_repeat('x', 64)))->assertOk()->assertSee('Link non valido');
        $this->get(route('privacy-link.mostra', 'corto'))->assertOk()->assertSee('Link non valido');

        $socio = $this->socio();
        $token = $this->inviaLink($socio);
        DB::table('gl_soci')->where('id', $socio->id)->update(['stato' => 'cancellato']);
        $this->get(route('privacy-link.mostra', $token))->assertSee('Link non valido');
        $this->post(route('privacy-link.conferma', $token), ['dichiarazione' => '1']);
        $this->assertNull($socio->fresh()->privacy_accettata_il);
    }
}
