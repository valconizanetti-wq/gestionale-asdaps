<?php

namespace App\Services;

use App\Mail\ConsultazioneEsito;
use App\Mail\ConsultazioneInvito;
use App\Models\Consultazione;
use App\Models\ConsultazioneVoto;
use App\Models\GovernanceCd;
use App\Models\User;
use App\Models\Verbale;
use App\Support\HtmlSanitizer;
use App\Support\InvioEmail;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Consultazione telematica del Consiglio Direttivo su una proposta di
 * delibera (statuto art. 16.4; v0.44.0, tavole CT-1…CT-3; analisi V8, V16,
 * V18). Stesso schema del voto sulle domande (VotazioneCd): link personale
 * per email, token salvato come impronta.
 *
 * - la prepara in bozza chi scrive i verbali (Presidente, Segretario);
 * - la avvia solo il Presidente, o il Vicepresidente «in sostituzione»
 *   (art. 18.3): partono le email e corre il termine (almeno 3 giorni);
 * - si chiude alla scadenza, quando hanno votato tutti, o quando almeno un
 *   terzo dei componenti chiede di discuterne in riunione (art. 16.4 d);
 * - validità: votanti (astenuti compresi) almeno la maggioranza dei
 *   componenti (art. 16.4 c); esito con la regola di tutte le votazioni
 *   (Votazioni::esito): favorevoli contro contrari, in parità il Presidente;
 * - alla chiusura nasce il verbale in bozza nel libro del CD.
 *
 * Non si usa per il rendiconto né per l'esclusione di un socio (art. 16.5):
 * lo dice la pagina; il tipo di delibera della consultazione è «ordinaria».
 */
class Consultazioni
{
    public function __construct(private DocumentiOrgani $documenti)
    {
    }

    // ----------------------------------------------------------------
    // Bozza (CT-1)
    // ----------------------------------------------------------------

    /** @param array<int, UploadedFile>|null $files */
    public function salvaBozza(?Consultazione $c, string $oggetto, ?string $testoHtml, string $termine, ?array $files, User $utente): Consultazione
    {
        if ($c && ! $c->isBozza()) {
            throw ValidationException::withMessages(['oggetto' => 'La consultazione è già avviata: non si modifica più.']);
        }
        $testo = HtmlSanitizer::pulisci($testoHtml);
        if (HtmlSanitizer::vuoto($testo)) {
            throw ValidationException::withMessages(['testo_html' => 'Scrivi il testo della proposta di delibera.']);
        }

        $scritti = [];
        try {
            return DB::transaction(function () use ($c, $oggetto, $testo, $termine, $files, $utente, &$scritti) {
                $c ??= new Consultazione();
                $c->fill(['oggetto' => trim($oggetto), 'testo_html' => $testo, 'termine' => Carbon::parse($termine)]);
                if (! $c->exists) {
                    $c->forceFill(['stato' => 'bozza', 'preparata_da' => $utente->id]);
                }
                $c->save();
                $scritti = $this->documenti->salva('consultazione', $c->id, $files, $utente);

                return $c;
            });
        } catch (\Throwable $e) {
            DocumentiOrgani::cancellaFile($scritti);
            throw $e;
        }
    }

    public function eliminaBozza(Consultazione $c): void
    {
        if (! $c->isBozza()) {
            throw ValidationException::withMessages(['elimina' => 'Si elimina solo una consultazione in bozza.']);
        }
        DB::transaction(function () use ($c) {
            foreach ($c->documenti() as $d) {
                $this->documenti->elimina($d);
            }
            $c->delete();
        });
    }

    // ----------------------------------------------------------------
    // Avvio (V16)
    // ----------------------------------------------------------------

    /** Chi può avviare: null se può, altrimenti il motivo. */
    public static function motivoNonAvvia(User $u, bool $inSostituzione): ?string
    {
        if ($u->haRuolo('presidente')) {
            return null;
        }
        if ($u->haRuolo('vicepresidente')) {
            return $inSostituzione ? null : 'Il Vicepresidente avvia la consultazione solo in sostituzione del Presidente: spunta la casella (art. 18.3).';
        }

        return 'La consultazione la avvia il Presidente (art. 16.4) o, in sua sostituzione, il Vicepresidente.';
    }

    /** Termine minimo: 3 giorni dall'avvio (art. 16.4 a). */
    public static function termineMinimo(?Carbon $da = null): Carbon
    {
        return ($da ?? now())->copy()->addDays(Consultazione::GIORNI_MINIMI);
    }

    /** @return array{inviate: int, senza_email: list<string>} */
    public function avvia(Consultazione $consultazione, User $utente, bool $inSostituzione): array
    {
        if ($m = self::motivoNonAvvia($utente, $inSostituzione)) {
            throw ValidationException::withMessages(['avvia' => $m]);
        }
        if ($consultazione->termine->lt(self::termineMinimo()->subMinutes(5))) {
            throw ValidationException::withMessages(['avvia' => 'Il termine deve essere almeno 3 giorni dopo l\'avvio (art. 16.4): spostalo e riprova.']);
        }
        $membri = Riunioni::membri();
        if ($membri->count() < 1) {
            throw ValidationException::withMessages(['avvia' => 'Nel Consiglio Direttivo non c\'è nessun membro in carica.']);
        }
        $senza = $membri->filter(fn (GovernanceCd $m) => trim((string) $m->socio->email) === '')
            ->map(fn (GovernanceCd $m) => trim($m->socio->nome . ' ' . $m->socio->cognome))->values()->all();
        if ($senza) {
            throw ValidationException::withMessages(['avvia' => 'Senza email non si vota: manca l\'email di ' . implode(', ', $senza) . ' (scheda del socio).']);
        }

        $token = [];
        DB::transaction(function () use ($consultazione, $membri, $utente, $inSostituzione, &$token) {
            $c = Consultazione::lockForUpdate()->findOrFail($consultazione->id);
            if (! $c->isBozza()) {
                throw ValidationException::withMessages(['avvia' => 'La consultazione è già avviata.']);
            }
            foreach ($membri as $m) {
                $v = new ConsultazioneVoto([
                    'consultazione_id' => $c->id,
                    'governance_cd_id' => $m->id,
                    'socio_id' => $m->socio_id,
                    'nome' => trim($m->socio->nome . ' ' . $m->socio->cognome),
                    'carica' => ConsiglioDirettivo::etichetta($m->carica_codice),
                    'email' => trim((string) $m->socio->email),
                    'presidente' => in_array('presidente', ConsiglioDirettivo::ruoliCarica($m->carica_codice), true),
                ]);
                $t = ConsultazioneVoto::generaToken();
                $v->assegnaToken($t);
                $v->save();
                $token[$v->id] = $t;
            }
            $c->forceFill([
                'stato' => 'in_corso',
                'componenti' => $membri->count(),
                'avviata_il' => now(),
                'avviata_da' => $utente->id,
                'in_sostituzione' => ! $utente->haRuolo('presidente') && $inSostituzione,
            ])->save();
            $this->audit($c, 'CONSULTAZIONE_AVVIATA', 'MEDIUM', "Consultazione telematica n. {$c->id} avviata: {$membri->count()} componenti, termine {$c->termine->format('d/m/Y H:i')}"
                . ($c->in_sostituzione ? ', dal Vicepresidente in sostituzione del Presidente' : '') . '.',
                ['stato' => 'bozza'], ['stato' => 'in_corso', 'componenti' => $membri->count(), 'termine' => $c->termine->toDateTimeString()]);
        });

        $inviate = 0;
        foreach (ConsultazioneVoto::where('consultazione_id', $consultazione->id)->with('consultazione', 'socio')->get() as $v) {
            if (InvioEmail::invia(new ConsultazioneInvito($v, $token[$v->id]), $v->email, $v->nome, 'consultazione_invito', socio: $v->socio)) {
                $inviate++;
            }
        }

        return ['inviate' => $inviate, 'senza_email' => []];
    }

    // ----------------------------------------------------------------
    // Voto (CT-2)
    // ----------------------------------------------------------------

    public static function aperta(Consultazione $c): bool
    {
        return $c->isInCorso() && $c->termine->isFuture();
    }

    /** @return bool registrato (false se nel frattempo è chiusa o il voto c'era già) */
    public function vota(ConsultazioneVoto $voto, string $scelta, ?string $nota, Request $request): bool
    {
        if (! isset(ConsultazioneVoto::VOTI[$scelta])) {
            throw ValidationException::withMessages(['voto' => 'Scegli il tuo voto.']);
        }
        $registrato = DB::transaction(function () use ($voto, $scelta, $nota, $request) {
            $c = Consultazione::lockForUpdate()->findOrFail($voto->consultazione_id);
            $v = ConsultazioneVoto::lockForUpdate()->findOrFail($voto->id);
            if (! self::aperta($c) || $v->haVotato()) {
                return false;
            }
            $v->forceFill([
                'voto' => $scelta,
                'nota' => $nota !== null && trim($nota) !== '' ? mb_substr(trim($nota), 0, 1000) : null,
                'votato_il' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ])->save();

            return true;
        });

        if ($registrato) {
            $c = $voto->consultazione()->first();
            $voti = $c->voti()->get();
            if ($voti->where('voto', 'riunione')->count() >= self::sogliaRiunione((int) $c->componenti)) {
                $this->chiudi($c, 'riunione');
            } elseif ($voti->whereNull('voto')->isEmpty()) {
                $this->chiudi($c, 'tutti');
            }
        }

        return $registrato;
    }

    /** Almeno un terzo dei componenti chiede la riunione (art. 16.4 d). */
    public static function sogliaRiunione(int $componenti): int
    {
        return max(1, (int) ceil($componenti / 3));
    }

    /** Validità: votanti (astenuti compresi) ≥ maggioranza dei componenti. */
    public static function quorum(int $componenti): int
    {
        return Votazioni::quorumCd($componenti);
    }

    /**
     * Esito dai voti (anche a consultazione in corso, per CT-3).
     * @return array{esito: string, testo: string, votanti: int, servono: int}
     */
    public static function calcola(Consultazione $c): array
    {
        $voti = $c->voti;
        $conti = $voti->whereNotNull('voto')->countBy('voto')->all();
        $votanti = ($conti['favorevole'] ?? 0) + ($conti['contrario'] ?? 0) + ($conti['astenuto'] ?? 0);
        $componenti = (int) $c->componenti;
        $servono = self::quorum($componenti);
        $riunione = $conti['riunione'] ?? 0;

        if ($riunione >= self::sogliaRiunione($componenti)) {
            return ['esito' => 'riunione', 'votanti' => $votanti, 'servono' => $servono,
                'testo' => "Da discutere in riunione: {$riunione} su {$componenti} componenti lo hanno chiesto (almeno un terzo, art. 16.4 d). La consultazione si ferma."];
        }
        if ($votanti < $servono) {
            return ['esito' => 'non_valida', 'votanti' => $votanti, 'servono' => $servono,
                'testo' => "Non valida: hanno votato {$votanti} su {$componenti} componenti, ne servivano almeno {$servono} (art. 16.4 c)."];
        }
        $presidente = $voti->firstWhere('presidente', true)?->voto;
        $r = Votazioni::esito('ordinaria', $conti, $presidente, $componenti);

        return ['esito' => $r['esito'], 'testo' => $r['testo'] . " Votanti {$votanti} su {$componenti} componenti.", 'votanti' => $votanti, 'servono' => $servono];
    }

    // ----------------------------------------------------------------
    // Chiusura
    // ----------------------------------------------------------------

    /** $come: scadenza, tutti, riunione. */
    public function chiudi(Consultazione $consultazione, string $come): ?Verbale
    {
        $verbale = DB::transaction(function () use ($consultazione, $come) {
            $c = Consultazione::lockForUpdate()->findOrFail($consultazione->id);
            if (! $c->isInCorso()) {
                return null;
            }
            $c->load('voti');
            $r = self::calcola($c);
            $c->forceFill(['stato' => 'chiusa', 'esito' => $r['esito'], 'esito_testo' => $r['testo'], 'chiusa_il' => now()])->save();

            $occupati = ConsiglioDirettivo::occupati();
            $nome = fn (?GovernanceCd $m) => $m?->socio ? trim($m->socio->nome . ' ' . $m->socio->cognome) : null;
            $v = new Verbale([
                'tipo' => 'cd_consultazione',
                'libro' => 'cd',
                'organo' => 'cd',
                'titolo' => 'Consultazione telematica: ' . $c->oggetto,
                'data_seduta' => now()->toDateString(),
                'presidente_assemblea' => $nome($occupati['presidente'] ?? null),
                'segretario_verbalizzante' => $nome($occupati['segretario'] ?? null),
            ]);
            $v->forceFill(['stato' => 'bozza', 'redatto_da' => $c->avviata_da])->save();
            $c->forceFill(['verbale_id' => $v->id])->save();
            $this->documenti->copia('consultazione', $c->id, 'verbale', $v->id);

            $this->audit($c, 'CONSULTAZIONE_CHIUSA', 'MEDIUM', "Consultazione telematica n. {$c->id} chiusa ("
                . ['scadenza' => 'alla scadenza', 'tutti' => 'hanno votato tutti', 'riunione' => 'richiesta la riunione'][$come] . '): '
                . Consultazione::ESITI[$r['esito']][0] . '; verbale in bozza nel libro del CD.',
                ['stato' => 'in_corso'], ['stato' => 'chiusa', 'esito' => $r['esito'], 'votanti' => $r['votanti'], 'componenti' => $c->componenti]);

            return $v;
        });

        if ($verbale) {
            $c = $consultazione->fresh('voti.socio');
            foreach ($c->voti as $v) {
                InvioEmail::invia(new ConsultazioneEsito($c, $v->nomeProprio()), $v->email, $v->nome, 'consultazione_esito', socio: $v->socio);
            }
        }

        return $verbale;
    }

    /** Consultazioni in corso con il termine passato (comando ogni 15 minuti). @return int chiuse */
    public function chiudiScadute(): int
    {
        $n = 0;
        foreach (Consultazione::where('stato', 'in_corso')->where('termine', '<=', now())->get() as $c) {
            if ($this->chiudi($c, 'scadenza')) {
                $n++;
            }
        }

        return $n;
    }

    public function ritira(Consultazione $consultazione, string $motivo, User $utente): void
    {
        if ($m = self::motivoNonAvvia($utente, $utente->haRuolo('vicepresidente'))) {
            throw ValidationException::withMessages(['motivo_ritiro' => 'La consultazione la ritira chi la può avviare: il Presidente o il Vicepresidente in sua sostituzione.']);
        }
        $c = DB::transaction(function () use ($consultazione, $motivo) {
            $c = Consultazione::lockForUpdate()->findOrFail($consultazione->id);
            if (! $c->isInCorso()) {
                throw ValidationException::withMessages(['motivo_ritiro' => 'Si ritira solo una consultazione in corso.']);
            }
            $c->forceFill(['stato' => 'ritirata', 'esito' => 'ritirata', 'esito_testo' => 'Ritirata prima della chiusura.', 'motivo_ritiro' => $motivo, 'chiusa_il' => now()])->save();
            $this->audit($c, 'CONSULTAZIONE_RITIRATA', 'HIGH', "Consultazione telematica n. {$c->id} ritirata prima della chiusura.",
                ['stato' => 'in_corso'], ['stato' => 'ritirata', 'motivazione' => $motivo]);

            return $c;
        });
        foreach ($c->voti()->with('socio')->get() as $v) {
            InvioEmail::invia(new ConsultazioneEsito($c, $v->nomeProprio()), $v->email, $v->nome, 'consultazione_ritirata', socio: $v->socio);
        }
    }

    /** Sollecito a chi non ha votato. @return int inviate */
    public function sollecita(Consultazione $c, User $utente): int
    {
        if (! self::aperta($c)) {
            throw ValidationException::withMessages(['sollecita' => 'La consultazione non è aperta.']);
        }
        $n = 0;
        foreach ($c->voti()->whereNull('voto')->with('socio', 'consultazione')->get() as $v) {
            $t = $v->tokenInChiaro();
            if ($t && InvioEmail::invia(new ConsultazioneInvito($v, $t, true), $v->email, $v->nome, 'consultazione_sollecito', socio: $v->socio)) {
                $v->forceFill(['sollecitato_il' => now()])->save();
                $n++;
            }
        }

        return $n;
    }

    // ----------------------------------------------------------------
    // Audit
    // ----------------------------------------------------------------

    private function audit(Consultazione $c, string $evento, string $rischio, string $dettaglio, ?array $prima, ?array $dopo): void
    {
        AuditLogService::scrivi([
            'evento' => $evento,
            'entita_tipo' => 'Consultazione',
            'entita_id' => $c->id,
            'anno' => (int) ($c->avviata_il ?? now())->year,
            'livello_rischio' => $rischio,
            'dettaglio' => $dettaglio,
            'old_values' => $prima,
            'new_values' => $dopo,
        ]);
    }
}
