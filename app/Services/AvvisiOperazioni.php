<?php

namespace App\Services;

use App\Mail\OperazioneAvviso;
use App\Models\User;
use App\Support\InvioEmail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Avviso delle operazioni delicate a chi non le ha eseguite (v0.40.0,
 * analisi-ruoli-utenti.md §3.7, decisione di Paolo del 13/10/2026).
 *
 * Parte da ogni riga HIGH dell'Audit Log scritta da un utente collegato
 * (AuditLogService::scrivi): importazione dei soci, ruoli e stato degli
 * utenti, eliminazione e ripristino delle schede, annullamento delle
 * ricevute, correzioni e riapertura degli anni chiusi, eliminazione dei
 * movimenti, saldi di apertura cambiati. Prende il posto della vecchia
 * notifica «al Presidente» dell'observer, che arrivava anche a chi aveva
 * fatto l'operazione.
 *
 * Destinatari: Presidente, Vicepresidente (v0.42.0), Segretario e Tesoriere attivi che vedono la
 * sezione dell'operazione, escluso chi l'ha eseguita; una email per persona.
 * Operazioni di sistema (comandi, pagine pubbliche) e sezione sconosciuta:
 * solo al Presidente, come prima. Nessun dato personale oltre al dettaglio
 * dell'Audit Log, che per regola non ne contiene (entra nell'hash).
 */
class AvvisiOperazioni
{
    public const RUOLI = ['presidente', 'vicepresidente', 'segretario', 'tesoriere'];

    /** Modello o entità dell'Audit Log => sezione dei permessi e pagina. */
    private const SEZIONI = [
        'User' => ['utenti', 'utenti.index'],
        'Utente' => ['utenti', 'utenti.index'],
        'Socio' => ['soci', 'soci.index'],
        'ImportazioneSoci' => ['soci', 'soci.index'],
        'Rinnovo' => ['rinnovi_quota', 'rinnovi.index'],
        'PrimaNota' => ['prima_nota', 'prima-nota.index'],
        'AnnoContabile' => ['prima_nota', 'prima-nota.anni.index'],
        'PrimaNotaScontrino' => ['prima_nota', 'scontrini.index'],
        'GovernanceCd' => ['governance_cd', 'governance.index'],
    ];

    /** Eventi del registro che hanno già un loro avviso (verifica mensile). */
    private const ESCLUSI = ['AUDIT_INTEGRITY_FAIL'];

    /**
     * Righe in attesa d'invio, raccolte fino alla fine della richiesta: una
     * sola operazione può scrivere più righe HIGH (es. annullare una ricevuta
     * elimina anche il movimento, magari su un anno chiuso) e ognuno deve
     * ricevere una sola email con tutte le righe.
     *
     * @var list<array{logId: int, riga: array, autore: ?User}>
     */
    private static array $coda = [];

    /** App su cui è registrato lo svuotamento (nei test l'app cambia a ogni prova). */
    private static ?int $registrato = null;

    /** Chiamato da AuditLogService dopo una riga HIGH: in coda dopo il commit. */
    public static function dopoAudit(int $logId, array $riga): void
    {
        if (in_array($riga['evento'] ?? '', self::ESCLUSI, true)) {
            return;
        }
        $autore = auth()->user();

        DB::afterCommit(function () use ($logId, $riga, $autore) {
            self::$coda[] = ['logId' => $logId, 'riga' => $riga, 'autore' => $autore];
            if (self::$registrato !== spl_object_id(app())) {
                self::$registrato = spl_object_id(app());
                app()->terminating(fn () => self::svuota());
            }
        });
    }

    /** Invia le email in coda: una per destinatario, con tutte le sue righe. */
    public static function svuota(): void
    {
        $coda = self::$coda;
        self::$coda = [];
        self::$registrato = null;

        $perUtente = [];
        foreach ($coda as $voce) {
            $chiave = $voce['riga']['entita_tipo'] ?? $voce['riga']['modello'] ?? null;
            [$sezione, $rotta] = self::SEZIONI[$chiave] ?? [null, null];
            foreach (self::destinatari($sezione, $voce['autore']) as $u) {
                $perUtente[$u->id] ??= ['utente' => $u, 'autore' => $voce['autore'], 'righe' => [], 'url' => null];
                $perUtente[$u->id]['righe'][] = [
                    'logId' => $voce['logId'],
                    'evento' => (string) ($voce['riga']['evento'] ?? ''),
                    'dettaglio' => (string) ($voce['riga']['dettaglio'] ?? ''),
                ];
                if (! $perUtente[$u->id]['url'] && $rotta && Route::has($rotta) && $u->puoAccedere($sezione)) {
                    $perUtente[$u->id]['url'] = route($rotta);
                }
            }
        }

        foreach ($perUtente as $p) {
            try {
                InvioEmail::invia(new OperazioneAvviso(
                    destinatario: $p['utente']->name,
                    autore: $p['autore']?->name ?? 'sistema',
                    righe: $p['righe'],
                    quando: now(),
                    url: $p['url'] ?? route('dashboard'),
                ), $p['utente']->email, $p['utente']->name, 'avviso_operazione');
            } catch (\Throwable $e) {
                Log::warning('Avviso delle operazioni non inviato: ' . $e->getMessage());
            }
        }
    }

    /** @return Collection<int, User> */
    public static function destinatari(?string $sezione, ?User $autore): Collection
    {
        $ruoli = $sezione && $autore ? self::RUOLI : ['presidente'];

        return User::conRuolo(...$ruoli)->where('attivo', true)->whereNotNull('email')->orderBy('id')->get()
            ->reject(fn (User $u) => $autore && $u->is($autore))
            ->filter(fn (User $u) => ! $sezione || ! $autore || $u->puoAccedere($sezione))
            ->values();
    }
}
