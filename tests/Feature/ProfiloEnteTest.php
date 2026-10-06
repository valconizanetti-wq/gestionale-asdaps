<?php

namespace Tests\Feature;

use App\Models\AssociazionePeriodo;
use App\Models\Impostazione;
use App\Models\User;
use App\Services\ProfiloEnte;
use Database\Seeders\AssociazioneSeeder;
use Database\Seeders\ImpostazioneSeeder;
use Database\Seeders\ProfiloEnteSeeder;
use Database\Seeders\RuoloSeeder;
use Database\Seeders\SezioneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Profilo dell'ente per periodi e chiusura delle iscrizioni online
 * (v0.32.0, profilo-ente.md). Gira su MySQL come gli altri test.
 */
class ProfiloEnteTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private User $segretario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RuoloSeeder::class, SezioneSeeder::class, ImpostazioneSeeder::class, AssociazioneSeeder::class]);
        Mail::fake();
        Carbon::setTestNow('2026-10-05 10:00:00');
        ProfiloEnte::dimentica();

        $this->presidente = User::factory()->create(['ruolo' => 'presidente', 'attivo' => true]);
        $this->segretario = User::factory()->create(['ruolo' => 'segretario', 'attivo' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function conPeriodi(): void
    {
        $this->seed(ProfiloEnteSeeder::class);
        ProfiloEnte::dimentica();
    }

    /** @return array<string, mixed> */
    private static function datiPeriodo(array $altri = []): array
    {
        return array_merge([
            'dal' => '2029-01-01', 'denominazione' => 'Ente di prova A.S.D.', 'sigla' => 'A.S.D.',
            'forma_giuridica' => 'asd', 'sede_legale' => 'Via Roma 1, Castel Viscardo (TR)',
            'regime_fiscale' => 'l398', 'ente_affiliazione' => 'CSEN',
            'statuto_versione' => '1.0', 'informativa_versione' => '1.0',
        ], $altri);
    }

    public function test_senza_periodi_valgono_i_valori_di_prima(): void
    {
        $p = ProfiloEnte::al();
        $this->assertFalse($p->exists);
        $this->assertSame(config('sito.piede.intestazione'), $p->denominazione);
        $this->assertSame('1.0', ProfiloEnte::versione('statuto'));
        $this->assertNull(ProfiloEnte::inVigoreOggi());
    }

    public function test_periodo_alla_data(): void
    {
        $this->conPeriodi();

        // Prima del primo periodo vale il primo.
        $this->assertStringContainsString('B.A.S.', ProfiloEnte::denominazione('2026-10-05'));
        $this->assertNull(ProfiloEnte::inVigoreOggi());
        $this->assertStringContainsString('B.A.S.', ProfiloEnte::denominazione('2027-12-31'));
        $this->assertStringContainsString('A.S.D.', ProfiloEnte::denominazione('2028-01-01'));
        $this->assertSame('1.0', ProfiloEnte::versione('informativa', '2027-06-01'));
        $this->assertSame('2.0', ProfiloEnte::versione('informativa', '2028-06-01'));
        $this->assertSame('2027-12-31', ProfiloEnte::fine(ProfiloEnte::al('2027-01-01'))->toDateString());
        $this->assertNull(ProfiloEnte::fine(ProfiloEnte::al('2028-01-01')));

        $this->assertFalse(ProfiloEnte::haModulo('rasd', '2027-06-01'));
        $this->assertTrue(ProfiloEnte::haModulo('rasd', '2028-06-01'));
        $this->assertTrue(ProfiloEnte::haModulo('l398', '2028-06-01'));

        // Nel 2028 la B.A.S. lascia il posto all'A.S.D.
        Carbon::setTestNow('2028-02-01 10:00:00');
        $this->assertStringContainsString('A.S.D.', ProfiloEnte::inVigoreOggi()->denominazione);
    }

    public function test_voce_rasd_del_menu_segue_la_forma(): void
    {
        $this->conPeriodi();
        // La sezione Compliance RASD non ha ancora le sue pagine: una rotta
        // finta basta a far comparire la voce quando la forma la prevede.
        \Illuminate\Support\Facades\Route::get('/compliance-rasd-prova', fn () => 'ok')->name('compliance-rasd.index');
        app('router')->getRoutes()->refreshNameLookups();

        $this->actingAs($this->presidente)->get(route('impostazioni.index'))
            ->assertOk()->assertDontSee('Compliance RASD');

        Carbon::setTestNow('2028-02-01 10:00:00');
        ProfiloEnte::dimentica();
        $this->actingAs($this->presidente)->get(route('impostazioni.index'))
            ->assertOk()->assertSee('Compliance RASD');
    }

    public function test_iscrizioni_chiuse_per_chi_visita_e_di_prova_per_chi_e_collegato(): void
    {
        $this->assertFalse((bool) Impostazione::get('iscrizioni_aperte', false), 'chiuse per impostazione predefinita');

        $this->get(route('iscrizione.dati'))->assertOk()->assertSee('non ancora aperte')->assertDontSee('name="codice_fiscale"', false);
        $this->post(route('iscrizione.dati.salva'), [])->assertRedirect(route('iscrizione.dati'));
        $this->get(route('iscrizione.riepilogo'))->assertRedirect(route('iscrizione.dati'));

        $this->actingAs($this->segretario)->get(route('iscrizione.dati'))
            ->assertOk()->assertSee('Iscrizioni chiuse al pubblico.')->assertSee('name="codice_fiscale"', false);

        auth()->logout();
        Impostazione::set('iscrizioni_aperte', true, 'boolean');
        $this->get(route('iscrizione.dati'))->assertOk()->assertDontSee('Iscrizioni chiuse al pubblico.')->assertSee('name="codice_fiscale"', false);
    }

    public function test_solo_il_presidente_modifica(): void
    {
        $this->conPeriodi();
        $this->actingAs($this->segretario)->get(route('impostazioni.index'))->assertForbidden();
        $this->actingAs($this->segretario)->post(route('impostazioni.iscrizioni'), ['aperte' => 1])->assertForbidden();
        $this->actingAs($this->segretario)->post(route('impostazioni.periodi.store'), self::datiPeriodo())->assertForbidden();
        $this->assertFalse((bool) Impostazione::get('iscrizioni_aperte', false));

        $this->actingAs($this->presidente)->post(route('impostazioni.iscrizioni'), ['aperte' => 1])->assertRedirect();
        $this->assertTrue((bool) Impostazione::get('iscrizioni_aperte', false));
        $this->actingAs($this->presidente)->post(route('impostazioni.iscrizioni'), ['aperte' => 0]);
        $this->assertFalse((bool) Impostazione::get('iscrizioni_aperte', false));

        $this->actingAs($this->presidente)->post(route('impostazioni.periodi.store'), self::datiPeriodo())
            ->assertSessionHasNoErrors()->assertRedirect(route('impostazioni.index'));
        $this->assertDatabaseHas('gl_associazione_periodi', ['denominazione' => 'Ente di prova A.S.D.']);
        $this->assertStringContainsString('Ente di prova', ProfiloEnte::denominazione('2029-05-01'));

        // Stessa data: rifiutata. Forma non A.S.D.: niente RASD.
        $this->actingAs($this->presidente)->post(route('impostazioni.periodi.store'), self::datiPeriodo())
            ->assertSessionHasErrors('dal');
        $this->actingAs($this->presidente)->post(route('impostazioni.periodi.store'), self::datiPeriodo([
            'dal' => '2030-01-01', 'forma_giuridica' => 'associazione', 'rasd_numero' => '123',
        ]))->assertSessionHasNoErrors();
        $this->assertNull(AssociazionePeriodo::whereDate('dal', '2030-01-01')->value('rasd_numero'));

        // Un periodo che comincia oggi o prima riscriverebbe documenti già emessi.
        $this->actingAs($this->presidente)->post(route('impostazioni.periodi.store'), self::datiPeriodo(['dal' => '2026-10-05']))
            ->assertSessionHasErrors('dal');

        // Dati fissi: IBAN normalizzato.
        $this->actingAs($this->presidente)->put(route('impostazioni.ente.dati.update'), [
            'codice_fiscale' => '90012345678', 'iban' => 'it60 x054 2811 1010 0000 0123 456',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('gl_associazione', ['iban' => 'IT60X0542811101000000123456']);
    }

    public function test_periodo_cominciato_cambia_solo_rasd_e_atto(): void
    {
        $this->conPeriodi();
        Carbon::setTestNow('2028-02-01 10:00:00');
        $asd = AssociazionePeriodo::whereDate('dal', '2028-01-01')->firstOrFail();
        $bas = AssociazionePeriodo::whereDate('dal', '2027-01-01')->firstOrFail();

        $this->actingAs($this->presidente)->put(route('impostazioni.periodi.update', $asd), [
            'denominazione' => 'Altro nome', 'rasd_numero' => 'R-42', 'rasd_data_iscrizione' => '2028-01-20', 'atto' => 'Assemblea del 10/01/2028',
        ])->assertSessionHasNoErrors();
        $asd->refresh();
        $this->assertNotSame('Altro nome', $asd->denominazione);
        $this->assertSame('R-42', $asd->rasd_numero);
        $this->assertTrue($asd->iscrittoRasd());

        // B.A.S.: nessun RASD anche se arriva.
        $this->actingAs($this->presidente)->put(route('impostazioni.periodi.update', $bas), ['rasd_numero' => 'X'])->assertSessionHasNoErrors();
        $this->assertNull($bas->refresh()->rasd_numero);

        $this->actingAs($this->presidente)->delete(route('impostazioni.periodi.destroy', $asd))->assertForbidden();
        $this->assertDatabaseHas('gl_associazione_periodi', ['id' => $asd->id]);
    }

    public function test_lista_di_controllo_del_passaggio(): void
    {
        $this->conPeriodi();
        $asd = AssociazionePeriodo::whereDate('dal', '2028-01-01')->firstOrFail();

        $voci = collect(ProfiloEnte::listaPassaggio($asd))->keyBy('codice');
        $this->assertTrue($voci['statuto']['fatta']);
        $this->assertFalse($voci['informativa']['fatta'], "l'informativa 2.0 non è ancora pubblicata");
        $this->assertFalse($voci['rasd']['fatta']);
        $this->assertSame('manuale', $voci['conto_bancario']['stato']);

        $bas = AssociazionePeriodo::whereDate('dal', '2027-01-01')->firstOrFail();
        $this->assertArrayNotHasKey('rasd', collect(ProfiloEnte::listaPassaggio($bas))->keyBy('codice')->all());

        $this->actingAs($this->presidente)->post(route('impostazioni.periodi.spunta', $asd), ['codice' => 'conto_bancario', 'fatta' => 1])
            ->assertRedirect(route('impostazioni.periodi.passaggio', $asd));
        $this->assertArrayHasKey('conto_bancario', $asd->refresh()->spunte);
        $this->actingAs($this->presidente)->post(route('impostazioni.periodi.spunta', $asd), ['codice' => 'inventata', 'fatta' => 1])
            ->assertSessionHasErrors('codice');
        $this->actingAs($this->presidente)->post(route('impostazioni.periodi.spunta', $asd), ['codice' => 'conto_bancario', 'fatta' => 0]);
        $this->assertNull($asd->refresh()->spunte);

        $this->actingAs($this->presidente)->get(route('impostazioni.periodi.passaggio', $asd))
            ->assertOk()->assertSee('Iscrizione al RASD')->assertSee('D.Lgs. 39/2021');
        $this->actingAs($this->presidente)->get(route('impostazioni.periodi.edit', $asd))->assertOk()->assertSee('Salva il periodo');
        $this->actingAs($this->presidente)->get(route('impostazioni.periodi.create'))->assertOk();
        $this->actingAs($this->presidente)->get(route('impostazioni.ente.dati'))->assertOk();
    }

    private function passo1Adulto(): void
    {
        $this->post(route('iscrizione.dati.salva'), [
            'chi' => 'me', 'nome' => 'Luca', 'cognome' => 'Verdi', 'codice_fiscale' => 'VRDLCU90E10L117B',
            'email' => 'luca@esempio.it', 'telefono' => '3407654321',
            'documento_tipo' => 'carta_identita', 'documento_numero' => 'CA98765ZZ',
            'indirizzo' => 'Via Garibaldi 5', 'cap' => '05014', 'citta' => 'Castel Viscardo', 'provincia' => 'TR',
        ])->assertSessionHasNoErrors()->assertRedirect(route('iscrizione.riepilogo'));
    }

    public function test_domanda_di_prova_con_iscrizioni_chiuse(): void
    {
        $this->actingAs($this->segretario);
        $this->passo1Adulto();
        $this->post(route('iscrizione.invia'), ['impronta_testi' => '0|1.0|1.0', 'dichiarazione' => '1', 'informativa' => '1'])
            ->assertRedirect(route('iscrizione.inviata'));
        $this->assertTrue(\App\Models\Domanda::where('codice_fiscale', 'VRDLCU90E10L117B')->firstOrFail()->is_test);
    }

    public function test_passaggio_di_periodo_tra_riepilogo_e_invio(): void
    {
        $this->conPeriodi();
        Impostazione::set('iscrizioni_aperte', true, 'boolean');
        // Testi pubblicati anche per il periodo A.S.D., per isolare il cambio di periodo.
        AssociazionePeriodo::whereDate('dal', '2028-01-01')->update(['informativa_versione' => '1.0']);
        ProfiloEnte::dimentica();

        Carbon::setTestNow('2027-12-31 23:58:00');
        $this->passo1Adulto();
        $html = $this->get(route('iscrizione.riepilogo'))->assertOk()->getContent();
        preg_match('/name="impronta_testi" value="([^"]+)"/', $html, $m);
        $impronta = html_entity_decode($m[1]);

        Carbon::setTestNow('2028-01-01 00:01:00');
        ProfiloEnte::dimentica();
        $this->post(route('iscrizione.invia'), ['impronta_testi' => $impronta, 'dichiarazione' => '1', 'informativa' => '1'])
            ->assertRedirect(route('iscrizione.riepilogo'))->assertSessionHasErrors('impronta_testi');
        $this->assertSame(0, \App\Models\Domanda::count());

        $this->get(route('iscrizione.riepilogo'))->assertSee('Testi aggiornati.');
    }
}
