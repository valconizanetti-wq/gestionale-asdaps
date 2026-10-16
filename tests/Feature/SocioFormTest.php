<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scheda del socio, campo del codice fiscale (collaudo O, 16/10/2026):
 * dopo un salvataggio con il codice sbagliato l'errore del server deve
 * stare in un solo posto, gestito da Alpine (erroreCf), così sparisce
 * appena si corregge il codice. Prima restava sotto il campo fino al
 * salvataggio successivo.
 */
class SocioFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_errore_del_codice_fiscale_in_un_solo_posto_e_gestito_da_alpine(): void
    {
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class]);
        $presidente = User::factory()->conRuoli('presidente')->create();

        $dati = [
            'tipo' => 'ordinario', 'data_prima_iscrizione' => '2026-10-01',
            'cognome' => 'Verdi', 'nome' => 'Luca', 'codice_fiscale' => 'VRDLCU90E10L117A',
            'indirizzo' => 'Via Roma 1', 'cap' => '05014', 'citta' => 'Castel Viscardo', 'provincia' => 'TR',
            'email' => 'luca@example.it', 'telefono' => '3331234567',
        ];
        $pagina = $this->actingAs($presidente)->from(route('soci.create'))->followingRedirects()
            ->post(route('soci.store'), $dati)->assertOk()->getContent();
        $messaggio = 'Il codice fiscale non è corretto: controlla di averlo scritto bene.';

        // L'errore come testo una sola volta (nel riquadro sotto la riga)...
        $this->assertSame(1, substr_count($pagina, e($messaggio)));
        // ...e come valore iniziale di Alpine, che lo toglie alla modifica
        $this->assertStringContainsString('erroreCf: ', $pagina);
        $this->assertStringContainsString('x-on:input="erroreCf = null"', $pagina);
        $this->assertStringContainsString('id="socio-cf-errore"', $pagina);
    }
}
