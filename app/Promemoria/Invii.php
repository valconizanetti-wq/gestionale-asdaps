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
        $adesso ??= now();
        $previsto = self::previsto($p, $adesso);
        if (! $previsto) {
            return null;
        }

        return $adesso->lt($previsto) ? $previsto : self::previsto($p, $adesso->copy()->addYear()->startOfYear());
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
        $periodo = $f->periodo($p, $adesso);
        $fatti = PromemoriaInvio::where('promemoria_id', $p->id)->where('periodo', $periodo)
            ->whereIn('esito', ['inviato', 'senza_email'])->pluck('chiave_destinatario')->flip();

        return $f->destinatari($p, $adesso)->reject(fn (Destinatario $d) => isset($fatti[$d->chiave]))->values();
    }

    /** @return array{inviati: int, falliti: int, senza_email: int} */
    public function esegui(Promemoria $p, Carbon $adesso): array
    {
        $esito = ['inviati' => 0, 'falliti' => 0, 'senza_email' => 0];
        $f = $p->funzioneOggetto();
        if (! $f || ! $p->attivo) {
            return $esito;
        }
        $periodo = $f->periodo($p, $adesso);

        foreach ($this->daAvvisare($p, $adesso) as $d) {
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

    /** Prova a sé stessi con i valori del primo destinatario (o di esempio). */
    public function prova(Promemoria $p, User $utente): bool
    {
        $d = $this->daAvvisare($p, now())->first() ?? $p->funzioneOggetto()?->destinatari($p, now())->first();
        $valori = $d?->valori ?? array_combine(array_keys($p->funzioneOggetto()?->segnaposti() ?? []),
            array_map(fn ($k) => '[' . $k . ']', array_keys($p->funzioneOggetto()?->segnaposti() ?? [])));
        $ok = InvioEmail::invia(new PromemoriaEmail('[Prova] ' . self::componi($p->oggetto, $valori), self::componi($p->testo, $valori)),
            $utente->email, $utente->name, 'promemoria_prova');

        PromemoriaInvio::create([
            'promemoria_id' => $p->id, 'periodo' => 'prova', 'socio_id' => null, 'chiave_destinatario' => null,
            'email' => $utente->email, 'esito' => 'prova', 'inviato_da' => $utente->id, 'inviato_il' => now(),
        ]);

        return $ok;
    }

    private function registra(Promemoria $p, string $periodo, Destinatario $d, ?string $email, string $esito): void
    {
        PromemoriaInvio::updateOrCreate(
            ['promemoria_id' => $p->id, 'periodo' => $periodo, 'chiave_destinatario' => $d->chiave],
            ['socio_id' => $d->socioId, 'email' => $email, 'esito' => $esito, 'inviato_il' => now()],
        );
    }
}
