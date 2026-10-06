<?php

namespace App\Services;

use App\Mail\DomandaAccolta;
use App\Mail\DomandaDiniego;
use App\Mail\DomandaRitirataConsiglieri;
use App\Mail\PromemoriaDiniego;
use App\Mail\VotoCdConsiglieriSenzaEmail;
use App\Mail\VotoCdEsito;
use App\Mail\VotoCdInvito;
use App\Models\Associazione;
use App\Models\Domanda;
use App\Models\GovernanceCd;
use App\Models\User;
use App\Models\VotoCd;
use App\Support\InvioEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Votazione del Consiglio Direttivo sulle domande di adesione
 * (statuto v1.0 artt. 6 e 16.4, domande-iscrizione.md §3).
 *
 * Unico punto che cambia lo stato di una domanda dopo la presentazione:
 * apertura, voto, chiusura, esiti, decisione del Presidente, diniego.
 * Controller e comando orario chiamano solo questi metodi.
 *
 * Regole di scrittura:
 * - ogni cambio di stato avviene in una transazione con la riga della
 *   domanda bloccata (lockForUpdate) e ricontrollata: due richieste
 *   simultanee (due consiglieri che votano insieme, il comando orario e un
 *   voto) non possono chiudere due volte né votare dopo la chiusura;
 * - le email partono dopo il commit: un invio fallito non annulla il voto
 *   né l'esito (resta in gl_email_log);
 * - audit: solo id, voti ed esiti, mai nomi o motivazioni (il dettaglio
 *   entra nell'hash della catena). Le domande di prova non vi entrano.
 */
class VotazioneCd
{
    public function __construct(private VerbaleAmmissione $verbale)
    {
    }

    // ── Apertura ─────────────────────────────────────────────────────────

    /**
     * Una riga di voto per ogni consigliere in carica, con il suo link
     * personale. Il flag "presidente" si fissa ora: le cariche possono
     * cambiare durante la votazione. Chi non ha email ha la riga (conta nel
     * quorum) ma nessun link, e il Presidente ne è avvisato.
     *
     * @return int consiglieri convocati
     */
    public function apri(Domanda $domanda): int
    {
        /** @var array<int, array{voto: VotoCd, token: string}> $inviti */
        $inviti = [];

        $creati = DB::transaction(function () use ($domanda, &$inviti) {
            $bloccata = Domanda::whereKey($domanda->id)->lockForUpdate()->first();
            if (! $bloccata || $bloccata->stato !== 'in_attesa' || $bloccata->voti()->exists()) {
                return 0;
            }

            $membri = GovernanceCd::with('socio')->where('in_carica', true)->orderBy('id')->get()
                ->filter(fn ($membro) => $membro->socio !== null);
            foreach ($membri as $membro) {
                $socio = $membro->socio;
                if (! $socio) {
                    continue;
                }
                $token = VotoCd::generaToken();
                $email = trim((string) $socio->email) ?: null;

                $voto = new VotoCd();
                $voto->assegnaToken($token);
                $voto->forceFill([
                    'domanda_id' => $domanda->id,
                    'governance_cd_id' => $membro->id,
                    'consigliere_nome' => trim($socio->nome . ' ' . $socio->cognome),
                    'consigliere_email' => $email,
                    'presidente' => self::caricaDiPresidente($membro->carica),
                ])->save();

                if ($email) {
                    $inviti[] = ['voto' => $voto, 'token' => $token];
                }
            }

            return $membri->count();
        });

        if ($creati === 0) {
            $this->segnalaSenzaConsiglieri($domanda);

            return 0;
        }

        $domanda->load('voti');
        $this->audit($domanda, 'VOTAZIONE_CD_APERTA', "Domanda {$domanda->id}: votazione aperta, {$creati} consiglieri convocati.");

        foreach ($inviti as $invito) {
            $this->inviaLink($invito['voto'], $invito['token'], false);
        }

        $senzaEmail = $domanda->voti->whereNull('consigliere_email');
        if ($senzaEmail->isNotEmpty()) {
            $this->avvisaPresidente(new VotoCdConsiglieriSenzaEmail($domanda, $senzaEmail->pluck('consigliere_nome')->all()), $domanda, 'consiglieri_senza_email');
        }

        return $creati;
    }

    /**
     * Rimanda il link a un consigliere (email persa, finita nello spam). È
     * lo stesso link: quello già ricevuto continua a funzionare. Se il
     * token non si può ricostruire (chiave dell'app cambiata) se ne crea
     * uno nuovo.
     */
    public function reinviaLink(VotoCd $voto): bool
    {
        if ($voto->haVotato() || ! $voto->consigliere_email || ! $voto->domanda->isInAttesa()) {
            return false;
        }

        return $this->inviaLink($voto, $this->tokenPer($voto), false);
    }

    // ── Voto ─────────────────────────────────────────────────────────────

    /**
     * Registra il voto dal link personale. Restituisce false se non era più
     * possibile (votazione chiusa, voto già espresso): il chiamante mostra
     * la pagina dello stato attuale.
     */
    public function vota(VotoCd $voto, string $scelta, ?string $motivo, Request $request): bool
    {
        $registrato = DB::transaction(function () use ($voto, $scelta, $motivo, $request) {
            $domanda = Domanda::whereKey($voto->domanda_id)->lockForUpdate()->first();
            $riga = VotoCd::whereKey($voto->id)->lockForUpdate()->first();

            if (! $domanda || ! $riga || $domanda->stato !== 'in_attesa' || $riga->voto !== null
                || $domanda->terminiCdScaduti()) {
                return false;
            }

            $riga->forceFill([
                'voto' => $scelta,
                'motivo_rifiuto' => $scelta === 'contrario' ? $motivo : null,
                'votato_il' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ])->save();

            return true;
        });

        if (! $registrato) {
            return false;
        }

        $voto->refresh();
        $domanda = $voto->domanda()->first();

        // Controllo di is_test prima di scrivere: le domande di prova non
        // entrano mai nella catena dell'audit.
        $this->audit($domanda, 'VOTO_CD_ESPRESSO', "Domanda {$domanda->id}, voto {$voto->id}: {$scelta}.", null, 'Consigliere (link personale)');

        // Letto dopo il salvataggio, dal database: con un dato vecchio la
        // votazione non si chiuderebbe all'ultimo voto.
        if ($domanda->tuttiHannoVotato()) {
            $this->chiudi($domanda, Domanda::CHIUSURA_TUTTI);
        }

        return true;
    }

    // ── Chiusura ─────────────────────────────────────────────────────────

    /**
     * Chiude la votazione e ne applica l'esito:
     * - accolta (anche d'ufficio) → email con la quota al richiedente;
     * - maggioranza contraria → da_rifiutare, il Presidente scrive il diniego;
     * - parità senza il Presidente → in_parita, decide il Presidente.
     * In tutti i casi: bozza di verbale ed email dell'esito ai consiglieri.
     *
     * @return string|null l'esito, o null se la votazione era già chiusa
     */
    public function chiudi(Domanda $domanda, string $modo): ?string
    {
        $esito = DB::transaction(function () use ($domanda, $modo) {
            $bloccata = Domanda::whereKey($domanda->id)->lockForUpdate()->first();
            if (! $bloccata || $bloccata->stato !== 'in_attesa') {
                return null;
            }

            $esito = $bloccata->esitoAttuale();
            $ora = now();

            $campi = [
                'votazione_chiusa_il' => $ora,
                'votazione_chiusura' => $modo,
                'esito_il' => $ora,
                'silenzio_assenso' => $esito === Domanda::ESITO_D_UFFICIO,
                'stato' => match ($esito) {
                    Domanda::ESITO_APPROVATA, Domanda::ESITO_D_UFFICIO => 'approvata',
                    Domanda::ESITO_RIFIUTATA => 'da_rifiutare',
                    Domanda::ESITO_PARITA => 'in_parita',
                },
            ];
            $bloccata->forceFill($campi)->save();

            return $esito;
        });

        if ($esito === null) {
            return null;
        }

        $domanda->refresh()->load('voti');
        $this->audit($domanda, 'VOTAZIONE_CD_CHIUSA',
            "Domanda {$domanda->id}: votazione chiusa ({$modo}), esito {$esito}, "
            . "{$domanda->riepilogoVoti()} su {$domanda->voti->count()} componenti.");

        $this->verbale->aggiorna($domanda);

        if ($domanda->isApprovata()) {
            $this->comunicaAccoglimento($domanda);
        }

        $this->inviaEsitoAiConsiglieri($domanda);

        return $esito;
    }

    // ── Decisioni del Presidente ─────────────────────────────────────────

    /** Voti pari senza il voto del Presidente: accoglie o respinge (con motivazione). */
    public function decidiParita(Domanda $domanda, bool $accogli, ?string $motivo, User $presidente): bool
    {
        $fatto = DB::transaction(function () use ($domanda, $accogli, $motivo) {
            $bloccata = Domanda::whereKey($domanda->id)->lockForUpdate()->first();
            if (! $bloccata || $bloccata->stato !== 'in_parita') {
                return false;
            }

            $bloccata->forceFill([
                'stato' => $accogli ? 'approvata' : 'rifiutata',
                'decisione_presidente_il' => now(),
                'motivo_rifiuto' => $accogli ? null : $motivo,
            ])->save();

            return true;
        });

        if (! $fatto) {
            return false;
        }

        $domanda->refresh()->load('voti');
        $this->audit($domanda, 'DOMANDA_DECISIONE_PRESIDENTE',
            "Domanda {$domanda->id}: voti pari, il Presidente ha deciso: " . ($accogli ? 'accolta' : 'non accolta') . '.', $presidente);

        if ($accogli) {
            $this->comunicaAccoglimento($domanda);
        } else {
            $this->comunicaDiniego($domanda);
        }
        $this->verbale->aggiorna($domanda);

        return true;
    }

    /** Maggioranza contraria: il Presidente invia il diniego motivato (art. 6.5). */
    public function inviaDiniego(Domanda $domanda, string $motivo, User $utente): bool
    {
        $fatto = DB::transaction(function () use ($domanda, $motivo) {
            $bloccata = Domanda::whereKey($domanda->id)->lockForUpdate()->first();
            if (! $bloccata || $bloccata->stato !== 'da_rifiutare') {
                return false;
            }
            $bloccata->forceFill(['stato' => 'rifiutata', 'motivo_rifiuto' => $motivo])->save();

            return true;
        });

        if (! $fatto) {
            return false;
        }

        $domanda->refresh()->load('voti');
        $this->audit($domanda, 'DOMANDA_DINIEGO_INVIATO', "Domanda {$domanda->id}: diniego motivato inviato.", $utente);
        $this->comunicaDiniego($domanda);
        $this->verbale->aggiorna($domanda);

        return true;
    }

    // ── Comando orario ───────────────────────────────────────────────────

    /** Sollecito al terzo giorno a chi non ha votato (una volta sola). */
    public function sollecita(Domanda $domanda): int
    {
        $marcata = Domanda::whereKey($domanda->id)
            ->where('stato', 'in_attesa')
            ->whereNull('sollecito_inviato_il')
            ->update(['sollecito_inviato_il' => now()]);
        if ($marcata === 0) {
            return 0;
        }

        $inviati = 0;
        foreach ($domanda->voti()->whereNull('voto')->whereNotNull('consigliere_email')->get() as $voto) {
            $inviati += (int) $this->inviaLink($voto, $this->tokenPer($voto), true);
        }

        return $inviati;
    }

    /** Promemoria al Presidente: diniego non ancora inviato dopo 7 giorni. */
    public function ricordaDiniego(Domanda $domanda): bool
    {
        $marcata = Domanda::whereKey($domanda->id)
            ->where('stato', 'da_rifiutare')
            ->whereNull('promemoria_diniego_il')
            ->update(['promemoria_diniego_il' => now()]);
        if ($marcata === 0) {
            return false;
        }

        return $this->avvisaPresidente(new PromemoriaDiniego($domanda->fresh()), $domanda, 'promemoria_diniego');
    }

    /** Quota non versata nel termine: la domanda decade (art. 6.6). */
    public function faiDecadere(Domanda $domanda): bool
    {
        $fatto = Domanda::whereKey($domanda->id)
            ->where('stato', 'approvata')
            ->whereNull('socio_id')
            ->update(['stato' => 'decaduta', 'updated_at' => now()]);
        if ($fatto === 0) {
            return false;
        }
        $this->audit($domanda, 'DOMANDA_DECADUTA', "Domanda {$domanda->id}: quota non versata entro il termine, domanda decaduta.");

        return true;
    }

    /**
     * Nessun consigliere in carica collegato a un socio: la votazione non si
     * apre. Prima si fermava in silenzio (collaudo A6, 29/09/2026). Errore
     * nel log e un solo avviso per domanda al Presidente (o alla
     * segreteria); il comando orario riprova ad aprirla.
     */
    private function segnalaSenzaConsiglieri(Domanda $domanda): void
    {
        $domanda->refresh();
        if ($domanda->stato !== 'in_attesa' || $domanda->voti()->exists()) {
            return;
        }

        Log::error("Domanda {$domanda->id}: nessun consigliere in carica, votazione del CD non aperta.");

        $giaAvvisato = \App\Models\EmailLog::where('domanda_id', $domanda->id)
            ->where('tipo_email', 'votazione_senza_cd')->exists();
        if (! $giaAvvisato) {
            $this->avvisaPresidente(new \App\Mail\VotoCdNessunConsigliere($domanda), $domanda, 'votazione_senza_cd');
        }
    }

    // ── Ritiro della domanda ─────────────────────────────────────────────

    /**
     * Prima di cancellare una domanda ritirata dal richiedente: se la
     * votazione era in corso, i consiglieri che hanno il link lo sanno.
     */
    public function avvisaRitiro(Domanda $domanda): void
    {
        if (! $domanda->isInAttesa()) {
            return;
        }
        $nome = $domanda->nomeCognome();
        foreach ($domanda->voti()->whereNotNull('consigliere_email')->get() as $voto) {
            InvioEmail::invia(new DomandaRitirataConsiglieri($nome, $voto->nomeProprio(), $voto->consigliere_nome),
                $voto->consigliere_email, $voto->consigliere_nome, 'domanda_ritirata_cd', $domanda);
        }
    }

    // ── Utilità ──────────────────────────────────────────────────────────

    /** "Presidente", "Presidente e Tesoriere": sì. "Vicepresidente": no. */
    public static function caricaDiPresidente(?string $carica): bool
    {
        return (bool) preg_match('/^\s*presidente\b/iu', (string) $carica);
    }

    /** Email del Presidente in carica (per avvisi e decisioni). */
    public static function emailPresidente(): ?array
    {
        $membro = GovernanceCd::with('socio')->where('in_carica', true)->get()
            ->first(fn (GovernanceCd $m) => self::caricaDiPresidente($m->carica) && $m->socio?->email);

        return $membro ? ['email' => $membro->socio->email, 'nome' => trim($membro->socio->nome . ' ' . $membro->socio->cognome), 'nome_proprio' => $membro->socio->nome] : null;
    }

    private function comunicaAccoglimento(Domanda $domanda): void
    {
        $oggi = now();
        $promo = Quota::promozioneAttiva() && Quota::periodoPromozione($oggi);
        $domanda->forceFill([
            'quota_importo' => Quota::importo($domanda->tipo_socio),
            'quota_promo' => $promo,
            'quota_anno' => Quota::anno($oggi, $promo),
            'quota_scadenza' => $oggi->copy()->addDays(Domanda::GIORNI_VERSAMENTO_QUOTA)->toDateString(),
        ])->save();

        $inviata = InvioEmail::invia(new DomandaAccolta($domanda), $domanda->emailDestinatario(), $domanda->nomeDestinatario(), 'domanda_accolta', $domanda);
        if ($inviata) {
            $domanda->forceFill(['esito_comunicato_il' => now()])->save();
        }
    }

    private function comunicaDiniego(Domanda $domanda): void
    {
        $inviata = InvioEmail::invia(new DomandaDiniego($domanda), $domanda->emailDestinatario(), $domanda->nomeDestinatario(), 'domanda_diniego', $domanda);
        if ($inviata) {
            $domanda->forceFill(['esito_comunicato_il' => now()])->save();
        }
    }

    private function inviaEsitoAiConsiglieri(Domanda $domanda): void
    {
        $serveIntervento = $domanda->isDaRifiutare() || $domanda->isInParita();
        $presidenteAvvisato = false;

        foreach ($domanda->voti->whereNotNull('consigliere_email') as $voto) {
            $alPresidente = $serveIntervento && $voto->presidente;
            $presidenteAvvisato = $presidenteAvvisato || $alPresidente;
            InvioEmail::invia(new VotoCdEsito($domanda, $voto->nomeProprio(), $alPresidente, $voto->consigliere_nome),
                $voto->consigliere_email, $voto->consigliere_nome, 'esito_votazione_cd', $domanda);
        }

        // Presidente senza email nella sua riga (o cambiato nel frattempo):
        // la richiesta di intervento va comunque a qualcuno.
        if ($serveIntervento && ! $presidenteAvvisato) {
            $pres = self::emailPresidente();
            $destinatario = $pres['email'] ?? Associazione::istanza()->email;
            if ($destinatario) {
                InvioEmail::invia(new VotoCdEsito($domanda, $pres['nome_proprio'] ?? 'Presidente', true, $pres['nome'] ?? null),
                    $destinatario, $pres['nome'] ?? null, 'esito_votazione_cd', $domanda);
            } else {
                Log::warning("Domanda {$domanda->id}: nessun indirizzo per chiedere l'intervento del Presidente.");
            }
        }
    }

    /** Token del link di un voto: quello già inviato, o uno nuovo se non ricostruibile. */
    private function tokenPer(VotoCd $voto): string
    {
        $token = $voto->tokenInChiaro();
        if ($token === null) {
            $token = VotoCd::generaToken();
            $voto->assegnaToken($token);
            $voto->save();
        }

        return $token;
    }

    private function inviaLink(VotoCd $voto, string $token, bool $sollecito): bool
    {
        $inviata = InvioEmail::invia(new VotoCdInvito($voto, $token, $sollecito),
            $voto->consigliere_email, $voto->consigliere_nome, $sollecito ? 'sollecito_voto_cd' : 'invito_voto_cd', $voto->domanda);
        if ($inviata && ! $sollecito) {
            $voto->forceFill(['link_inviato_il' => now()])->save();
        }

        return $inviata;
    }

    /** Email al Presidente in carica, o alla segreteria se manca. */
    private function avvisaPresidente(\Illuminate\Mail\Mailable $email, Domanda $domanda, string $tipo): bool
    {
        $pres = self::emailPresidente();
        $destinatario = $pres['email'] ?? Associazione::istanza()->email;
        if (! $destinatario) {
            Log::warning("Domanda {$domanda->id}: nessun indirizzo per l'avviso {$tipo}.");

            return false;
        }

        return InvioEmail::invia($email, $destinatario, $pres['nome'] ?? null, $tipo, $domanda);
    }

    private function audit(Domanda $domanda, string $evento, string $dettaglio, ?User $utente = null, ?string $chi = null): void
    {
        if ($domanda->is_test) {
            return;
        }

        AuditLogService::scrivi([
            'evento' => $evento,
            'entita_tipo' => 'Domanda',
            'entita_id' => $domanda->id,
            'utente_nome' => $chi ?? $utente?->name ?? (auth()->user()?->name ?? 'sistema'),
            'dettaglio' => $dettaglio,
        ]);
    }
}
