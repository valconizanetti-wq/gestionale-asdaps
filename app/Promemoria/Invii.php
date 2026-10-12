<?php

namespace App\Promemoria;

use App\Mail\PromemoriaEmail;
use App\Models\Promemoria;
use App\Models\PromemoriaInvio;
use App\Models\Socio;
use App\Models\User;
use App\Support\InvioEmail;
use Illuminate\Support\Carbon;

/**
 * Motore dei promemoria (promemoria.md §3): quando è il momento, calcola i
 * destinatari della funzione, manda le email e registra ogni invio. Un
 * destinatario riceve lo stesso promemoria una volta sola per periodo; un
 * invio fallito si ritenta al giro dopo. Chi non ha email resta nel registro
 * come «senza email», da avvisare a mano.
 *
 * Due modi (promemoria.md §3): data fissa ogni anno, oppure «N giorni prima
 * o dopo» una data di ogni destinatario (relativa, v0.38.0: scadenze
 * dell'associazione, tessere in scadenza). Nel secondo ogni destinatario ha
 * la sua finestra d'invio e il suo periodo.
 */
class Invii
{
    /** Giorni dopo la data prevista in cui un invio mancato (server spento) si recupera. */
    public const RECUPERO_GIORNI = 7;

    /** Sostituisce {segnaposto} con i valori; quelli sconosciuti restano come sono. */
    public static function componi(string $testo, array $valori): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => array_key_exists($m[1], $valori) ? $valori[$m[1]] : $m[0], $testo);
    }

    /** Data e ora previste per quest'anno (data fissa), con il 29 febbraio portato al 28 negli anni non bisestili. */
    public static function previsto(Promemoria $p, Carbon $adesso): ?Carbon
    {
        if ($p->quando !== 'data_fissa' || ! $p->giorno || ! $p->mese) {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', $p->oraBreve() ?: '09:00'));
        $mese = Carbon::create($adesso->year, $p->mese, 1, $h, $m, 0, $adesso->timezone);

        return $mese->day(min($p->giorno, $mese->daysInMonth));
    }

    /** È il momento: dalla data prevista per RECUPERO_GIORNI giorni. */
    public static function eIlMomento(Promemoria $p, Carbon $adesso): bool
    {
        $previsto = self::previsto($p, $adesso);

        return $previsto && $adesso->gte($previsto) && $adesso->lt($previsto->copy()->addDays(self::RECUPERO_GIORNI));
    }

    /** Prossimo invio previsto (per le pagine). */
    public static function prossimo(Promemoria $p, ?Carbon $adesso = null): ?Carbon
    {
        return self::prossimoDettaglio($p, $adesso)['quando'] ?? null;
    }

    /**
     * Prossimo invio con «che cosa» (pagina Promemoria, tavola PR-3: «01/07/2027
     * · Polizza RC»). Data fissa: la prossima data dell'anno. Relativa: la data
     * più vicina tra i destinatari di oggi non ancora avvisati (se è già
     * arrivata, il prossimo giro del comando).
     *
     * @return array{quando: Carbon, cosa: ?string}|null
     */
    public static function prossimoDettaglio(Promemoria $p, ?Carbon $adesso = null): ?array
    {
        $adesso ??= now();
        if ($p->quando === 'relativa') {
            $f = $p->funzioneOggetto();
            if (! $f || $p->giorni === null) {
                return null;
            }
            $fatti = self::fatti($p);
            $prossimi = $f->destinatari($p, $adesso)
                ->filter(fn (Destinatario $d) => $d->data && ! isset($fatti[self::periodoDi($p, $d, $adesso) . '|' . $d->chiave]))
                ->map(fn (Destinatario $d) => ['quando' => self::momento($p, $d), 'fine' => self::fineFinestra($p, $d), 'cosa' => $d->cosa])
                ->filter(fn ($x) => $x['fine']->gt($adesso))
                ->sortBy(fn ($x) => $x['quando']->timestamp);
            $primo = $prossimi->first();
            if (! $primo) {
                return null;
            }
            // Più destinatari per la stessa cosa (Presidente e Tesoriere): una volta sola
            $cose = $prossimi->filter(fn ($x) => $x['quando']->eq($primo['quando']))->pluck('cosa')->filter()->unique()->values();

            return ['quando' => $primo['quando']->lt($adesso) ? $adesso->copy() : $primo['quando'],
                'cosa' => $cose->count() > 1 ? $cose->first() . ' e altre ' . ($cose->count() - 1) : $cose->first()];
        }
        $previsto = self::previsto($p, $adesso);
        if (! $previsto) {
            return null;
        }

        return ['quando' => $adesso->lt($previsto) ? $previsto : self::previsto($p, $adesso->copy()->addYear()->startOfYear()), 'cosa' => null];
    }

    /** Relativa: data e ora dell'invio per un destinatario (la sua data ± giorni, all'ora scelta). */
    public static function momento(Promemoria $p, Destinatario $d): Carbon
    {
        [$h, $m] = array_map('intval', explode(':', $p->oraBreve() ?: '09:00'));

        return $d->data->copy()->startOfDay()->addDays((int) $p->giorni)->setTime($h, $m);
    }

    /**
     * Relativa: fine della finestra d'invio. Si recupera per RECUPERO_GIORNI
     * giorni (server spento), ma un promemoria «prima» non parte dopo la data:
     * un avviso di scadenza arrivato a cose fatte confonderebbe.
     */
    public static function fineFinestra(Promemoria $p, Destinatario $d): Carbon
    {
        $fine = self::momento($p, $d)->addDays(self::RECUPERO_GIORNI);
        if ((int) $p->giorni < 0) {
            $fine = $fine->min($d->data->copy()->startOfDay()->addDay());
        }

        return $fine;
    }

    /** Relativa: il destinatario è nella sua finestra d'invio. */
    public static function dovuto(Promemoria $p, Destinatario $d, Carbon $adesso): bool
    {
        return $d->data && $adesso->gte(self::momento($p, $d)) && $adesso->lt(self::fineFinestra($p, $d));
    }

    /** Periodo dell'invio per un destinatario: il suo (relativa) o quello della funzione. */
    public static function periodoDi(Promemoria $p, Destinatario $d, Carbon $adesso): string
    {
        if ($d->periodo !== null) {
            return $d->periodo;
        }
        if ($d->data) {
            return $d->data->toDateString();
        }

        return $p->funzioneOggetto()->periodo($p, $adesso);
    }

    /** Invii già fatti (o senza email): «periodo|chiave» => true. */
    private static function fatti(Promemoria $p): array
    {
        if (! $p->exists) {
            return [];
        }

        return PromemoriaInvio::where('promemoria_id', $p->id)->whereIn('esito', ['inviato', 'senza_email'])
            ->get(['periodo', 'chiave_destinatario'])
            ->mapWithKeys(fn ($i) => [$i->periodo . '|' . $i->chiave_destinatario => true])->all();
    }

    /**
     * Destinatari che riceverebbero il promemoria in questo momento: quelli
     * della funzione, senza chi l'ha già ricevuto nel periodo.
     *
     * @return \Illuminate\Support\Collection<int, Destinatario>
     */
    public function daAvvisare(Promemoria $p, Carbon $adesso)
    {
        $f = $p->funzioneOggetto();
        if (! $f) {
            return collect();
        }
        $fatti = self::fatti($p);
        $relativa = $p->quando === 'relativa';

        return $f->destinatari($p, $adesso)
            ->filter(fn (Destinatario $d) => ! $relativa || self::dovuto($p, $d, $adesso))
            ->reject(fn (Destinatario $d) => isset($fatti[self::periodoDi($p, $d, $adesso) . '|' . $d->chiave]))
            ->values();
    }

    /** @return array{inviati: int, falliti: int, senza_email: int} */
    public function esegui(Promemoria $p, Carbon $adesso): array
    {
        $esito = ['inviati' => 0, 'falliti' => 0, 'senza_email' => 0];
        $f = $p->funzioneOggetto();
        if (! $f || ! $p->attivo) {
            return $esito;
        }
        foreach ($this->daAvvisare($p, $adesso) as $d) {
            $periodo = self::periodoDi($p, $d, $adesso);
            if (! $d->email) {
                $this->registra($p, $periodo, $d, null, 'senza_email');
                $esito['senza_email']++;

                continue;
            }
            $ok = InvioEmail::invia(
                new PromemoriaEmail(self::componi($p->oggetto, $d->valori), self::componi($p->testo, $d->valori)),
                $d->email, $d->nome, 'promemoria_' . $p->funzione, null, $d->socioId ? Socio::find($d->socioId) : null);
            $this->registra($p, $periodo, $d, $d->email, $ok ? 'inviato' : 'fallito');
            $esito[$ok ? 'inviati' : 'falliti']++;
        }

        return $esito;
    }

    /**
     * Valori per l'anteprima e la prova: quelli del destinatario, oppure, se
     * oggi non ce n'è nessuno, quelli d'esempio della funzione (v0.38.1:
     * prima restavano i segnaposti tra parentesi quadre).
     *
     * @return array{valori: array<string, string>, esempio: bool}
     */
    public static function valoriProva(Promemoria $p, ?Destinatario $d, User $utente, ?Carbon $adesso = null): array
    {
        if ($d) {
            return ['valori' => $d->valori, 'esempio' => false];
        }

        return ['valori' => $p->funzioneOggetto()?->esempio($p, $adesso ?? now(), $utente) ?? [], 'esempio' => true];
    }

    /**
     * Prova a sé stessi con i valori del primo destinatario (o d'esempio).
     *
     * @return array{ok: bool, esempio: bool}
     */
    public function prova(Promemoria $p, User $utente): array
    {
        $d = $this->daAvvisare($p, now())->first() ?? $p->funzioneOggetto()?->destinatari($p, now())->first();
        ['valori' => $valori, 'esempio' => $esempio] = self::valoriProva($p, $d, $utente);
        $ok = InvioEmail::invia(new PromemoriaEmail(($esempio ? '[Prova, dati d\'esempio] ' : '[Prova] ') . self::componi($p->oggetto, $valori), self::componi($p->testo, $valori)),
            $utente->email, $utente->name, 'promemoria_prova');

        PromemoriaInvio::create([
            'promemoria_id' => $p->id, 'periodo' => 'prova', 'socio_id' => null, 'chiave_destinatario' => null,
            'email' => $utente->email, 'esito' => 'prova', 'inviato_da' => $utente->id, 'inviato_il' => now(),
        ]);

        return ['ok' => $ok, 'esempio' => $esempio];
    }

    private function registra(Promemoria $p, string $periodo, Destinatario $d, ?string $email, string $esito): void
    {
        PromemoriaInvio::updateOrCreate(
            ['promemoria_id' => $p->id, 'periodo' => $periodo, 'chiave_destinatario' => $d->chiave],
            ['socio_id' => $d->socioId, 'email' => $email, 'esito' => $esito, 'inviato_il' => now()],
        );
    }
}
