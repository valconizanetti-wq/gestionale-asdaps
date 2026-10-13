<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Promemoria;
use App\Models\Socio;
use App\Models\User;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\PrimaNotaCategoriaSeeder;
use Database\Seeders\ProfiloEnteSeeder;
use Database\Seeders\PromemoriaSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Correzioni dal collaudo K (v0.37.1): conto della quota, eliminazione della
 * scheda con motivazione e blocchi, schede eliminate e ripristino, ordine dei
 * promemoria.
 */
class CollaudoKTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

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
        $this->presidente = User::factory()->conRuoli('presidente')->create(['name' => 'Utente Presidente', 'attivo' => true]);
        $this->segretario = User::factory()->conRuoli('segretario')->create(['name' => 'Utente Segretario', 'attivo' => true]);
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
            'nome' => 'Luca', 'cognome' => 'Conti' . $n, 'codice_fiscale' => sprintf('CNTLCU80A01E7%02dX', $n),
            'tipo' => 'ordinario', 'stato' => 'attivo', 'data_nascita' => '1980-01-01', 'luogo_nascita' => 'Orvieto (TR)',
            'email' => "socio{$n}@example.it", 'data_prima_iscrizione' => '2025-03-01',
        ])->save();

        return $s;
    }

    private function quota(Socio $s, string $metodo = 'bonifico')
    {
        return $this->actingAs($this->presidente)->post(route('rinnovi.store'), [
            'socio_id' => $s->id, 'anno' => 2027, 'importo' => '70,00', 'data_pagamento' => '2027-02-15', 'metodo' => $metodo,
        ]);
    }

    public function test_k3_conto_della_quota(): void
    {
        $s = $this->socio();
        $this->quota($s);
        $c = $this->socio();
        $this->quota($c, 'contante');
        $this->actingAs($this->presidente)->get(route('soci.show', $s))->assertOk()->assertSee('Bonifico · Banca');
        $this->actingAs($this->presidente)->get(route('soci.show', $c))->assertOk()->assertSee('Contanti · Cassa');
        $this->actingAs($this->presidente)->get(route('rinnovi.index', ['anno' => 2027]))->assertSee('Bonifico · Banca')->assertSee('Contanti · Cassa');
    }

    public function test_k7_eliminazione_con_motivazione_e_blocchi(): void
    {
        $s = $this->socio();
        $this->actingAs($this->segretario)->delete(route('soci.destroy', $s), ['motivo' => 'Inserito due volte per errore'])->assertForbidden();
        $this->actingAs($this->presidente)->delete(route('soci.destroy', $s), [])->assertSessionHasErrors('motivo', null, 'elimina');
        $this->actingAs($this->presidente)->delete(route('soci.destroy', $s), ['motivo' => 'corto'])->assertSessionHasErrors('motivo', null, 'elimina');
        $this->assertNotSoftDeleted($s);

        // Con una quota valida (ricevuta e movimento) non si elimina
        $q = $this->socio();
        $this->quota($q);
        $this->actingAs($this->presidente)->delete(route('soci.destroy', $q), ['motivo' => 'Inserito due volte per errore'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, '1/2027') && str_contains($m, 'Registra cessazione'));
        $this->assertNotSoftDeleted($q);

        $this->actingAs($this->presidente)->delete(route('soci.destroy', $s), ['motivo' => 'Inserito due volte per errore'])
            ->assertRedirect(route('soci.index'))->assertSessionHas('success', fn ($m) => str_contains($m, 'Schede eliminate'));
        $this->assertSoftDeleted($s);
        $riga = AuditLog::where('evento', 'SOCIO_DELETED')->firstOrFail();
        // Motivazione e dati personali nei valori, fuori dall'hash
        $this->assertSame('Inserito due volte per errore', $riga->new_values['motivazione']);
        $this->assertStringNotContainsString('Inserito due volte', $riga->dettaglio);
        $this->assertStringNotContainsString($s->codice_fiscale, $riga->dettaglio);
        $this->assertSame(1, AuditLog::where('entita_tipo', 'Socio')->where('entita_id', $s->id)->where('operazione', 'deleted')->count());
    }

    public function test_schede_eliminate_e_ripristino(): void
    {
        $s = $this->socio(['cognome' => 'Eliminato']);
        $s->delete();

        // Solo il Presidente vede la voce; ricerca per codice fiscale
        $this->actingAs($this->presidente)->get(route('soci.index'))->assertSee('Schede eliminate')->assertDontSee('Eliminato');
        $this->actingAs($this->presidente)->get(route('soci.index', ['stato' => 'eliminate', 'q' => $s->codice_fiscale]))->assertOk()
            ->assertSee('Eliminato')->assertSee('Eliminata il 15/02/2027')->assertSee(route('soci.ripristina', $s->id), false);
        $this->actingAs($this->segretario)->get(route('soci.index', ['stato' => 'eliminate']))->assertOk()
            ->assertDontSee('Schede eliminate')->assertDontSee('Eliminato');   // per gli altri ruoli torna «attivi»

        // CSV e libro soci non escono mai con le schede eliminate
        $csv = $this->actingAs($this->presidente)->get(route('soci.export', ['stato' => 'eliminate']))->streamedContent();
        $this->assertStringNotContainsString('Eliminato', $csv);

        $this->actingAs($this->presidente)->post(route('soci.ripristina', $s->id))->assertRedirect(route('soci.show', $s));
        $this->assertNotSoftDeleted($s);

    }

    public function test_k9_promemoria_nell_ordine_dell_anno_sociale(): void
    {
        $risposta = $this->actingAs($this->presidente)->get(route('promemoria.index'))->assertOk();
        $nomi = Promemoria::where('funzione', 'quote_non_versate')->pluck('nome', 'mese');
        $risposta->assertSeeInOrder([$nomi[12], $nomi[2], $nomi[4]]);
        $risposta->assertSee('destinatari, anteprima e prova', false);
    }
}
