<?php

namespace App\Services;

use App\Mail\PrivacyLinkInvito;
use App\Models\Domanda;
use App\Models\PrivacyLink;
use App\Models\Socio;
use App\Models\User;
use App\Support\InvioEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Link personale per l'informativa privacy e il consenso a foto e video
 * (privacy-link.md). Destinatario, invio dalla scheda e in blocco,
 * registrazione della conferma.
 */
class LinkPrivacy
{
    /**
     * Manda un link nuovo al socio (al tutore se minorenne) e annulla i link
     * ancora aperti. Se l'email non parte, anche il link nuovo è annullato:
     * la scheda non deve dire "in attesa" per un'email mai arrivata.
     *
     * @return array{inviato: bool, email: ?string, motivo: ?string}
     */
    public function invia(Socio $socio, User $utente): array
    {
        if ($socio->stato !== 'attivo' || $socio->pseudonimizzato) {
            return ['inviato' => false, 'email' => null, 'motivo' => 'Il socio non è attivo.'];
        }

        $dest = $socio->destinatarioPrivacy();
        if (! $dest) {
            return ['inviato' => false, 'email' => null, 'motivo' => $socio->isMinorenne()
                ? 'Manca l\'email del genitore o tutore.' : 'Manca l\'email del socio.'];
        }

        $token = Domanda::generaToken();
        // "Informativa aggiornata" solo per chi ha letto una versione precedente.
        $aggiornamento = $socio->privacy_accettata_il !== null
            && $socio->privacy_versione !== ProfiloEnte::versione('informativa');

        $link = DB::transaction(function () use ($socio, $utente, $dest, $token) {
            PrivacyLink::where('socio_id', $socio->id)->aperti()->update(['annullato_il' => now()]);

            return PrivacyLink::create([
                'socio_id' => $socio->id,
                'token_hash' => Domanda::hashToken($token),
                'destinatario' => $dest['destinatario'],
                'email' => $dest['email'],
                'inviato_da' => $utente->id,
                'inviato_il' => now(),
            ]);
        });

        $inviata = InvioEmail::invia(new PrivacyLinkInvito($socio, $link, $token, $aggiornamento),
            $dest['email'], $dest['nome'], 'privacy_link', null, $socio);

        if (! $inviata) {
            $link->forceFill(['annullato_il' => now()])->save();

            return ['inviato' => false, 'email' => $dest['email'], 'motivo' => 'L\'email non è partita.'];
        }

        AuditLogService::scrivi([
            'evento' => 'PRIVACY_LINK_INVIATO',
            'entita_tipo' => 'Socio',
            'entita_id' => $socio->id,
            'utente_nome' => $utente->name,
            'dettaglio' => "Link privacy n. {$link->id} inviato ({$dest['destinatario']}).",
        ]);

        return ['inviato' => true, 'email' => $dest['email'], 'motivo' => null];
    }

    /** Soci attivi che non hanno letto l'informativa in vigore (mai, o una versione precedente). */
    public function query(?string $versione = null): Builder
    {
        $versione ??= ProfiloEnte::versione('informativa');

        return Socio::query()
            ->where('stato', 'attivo')
            ->where('pseudonimizzato', false)
            ->where(fn ($q) => $q->whereNull('privacy_accettata_il')
                ->orWhereNull('privacy_versione')
                ->orWhere('privacy_versione', '!=', $versione));
    }

    /**
     * Riepilogo per la finestra dell'invio in blocco (tavola Privacy-5).
     *
     * @return array{versione: string, senza: int, precedente: int, in_attesa: int, senza_email: Collection, da_inviare: Collection, con_rinvio: Collection}
     */
    public function riepilogo(): array
    {
        $versione = ProfiloEnte::versione('informativa');
        $soci = $this->query($versione)->with('privacyLinkAperto')->orderBy('cognome')->orderBy('nome')->get();

        $senzaEmail = $soci->filter(fn (Socio $s) => $s->destinatarioPrivacy() === null)->values();
        $conEmail = $soci->filter(fn (Socio $s) => $s->destinatarioPrivacy() !== null)->values();

        return [
            'versione' => $versione,
            'senza' => $soci->whereNull('privacy_accettata_il')->count(),
            'precedente' => $soci->whereNotNull('privacy_accettata_il')->count(),
            'in_attesa' => $conEmail->filter(fn (Socio $s) => $s->privacyLinkAperto !== null)->count(),
            'senza_email' => $senzaEmail,
            'da_inviare' => $conEmail->filter(fn (Socio $s) => $s->privacyLinkAperto === null)->values(),
            'con_rinvio' => $conEmail,
        ];
    }

    /**
     * Invio in blocco: a chi non ha un link aperto, oppure anche a chi ce
     * l'ha (il link nuovo sostituisce il vecchio). Sincrono: pochi soci,
     * nessuna coda su cPanel.
     *
     * @return array{inviati: int, falliti: Collection}
     */
    public function inviaATutti(bool $ancheInAttesa, User $utente): array
    {
        $r = $this->riepilogo();
        $inviati = 0;
        $falliti = collect();

        foreach ($ancheInAttesa ? $r['con_rinvio'] : $r['da_inviare'] as $socio) {
            $esito = $this->invia($socio, $utente);
            $esito['inviato'] ? $inviati++ : $falliti->push("{$socio->cognome} {$socio->nome}");
        }

        return ['inviati' => $inviati, 'falliti' => $falliti];
    }

    /**
     * Conferma dalla pagina del link (privacy-link.md §3). Link bloccato in
     * lettura: due invii quasi simultanei non registrano due volte.
     * Restituisce false se il link non è più valido.
     */
    public function registra(PrivacyLink $link, bool $foto, Request $request): bool
    {
        return DB::transaction(function () use ($link, $foto, $request) {
            $link = PrivacyLink::whereKey($link->id)->lockForUpdate()->first();
            if (! $link || ! $link->isValido()) {
                return false;
            }

            $adesso = now();
            $versione = ProfiloEnte::versione('informativa');

            $link->forceFill([
                'usato_il' => $adesso,
                'ip' => $request->ip(),
                'informativa_versione' => $versione,
                'foto_video' => $foto,
            ])->save();

            $socio = $link->socio;
            $socio->privacy_accettata_il = $adesso;
            $socio->privacy_ip = $request->ip();
            $socio->privacy_versione = $versione;
            $socio->privacy_metodo = 'link';

            // Foto e video: cambia solo se la scelta è diversa dallo stato
            // attuale. Una spunta tolta è una revoca dal link.
            if ($foto !== (bool) $socio->foto_video_consenso) {
                $socio->foto_video_consenso = $foto;
                $socio->foto_video_il = $adesso;
                $socio->foto_video_origine = 'link';
            }

            $socio->save();

            return true;
        });
    }
}
