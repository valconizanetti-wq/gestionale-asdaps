<?php

namespace App\Services;

use App\Mail\ConvocazioneSeduta;
use App\Models\GovernanceCd;
use App\Models\Seduta;
use App\Models\SedutaPresenza;
use App\Models\User;
use App\Models\Verbale;
use App\Models\VerbalePunto;
use App\Models\VerbaleVoto;
use App\Support\HtmlSanitizer;
use App\Support\InvioEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Riunioni del Consiglio Direttivo (v0.44.0, tavole CD-5 e VB-2): bozza,
 * convocazione per email con i termini dell'art. 16.1 (V4), rinvio,
 * annullamento, presenze con il quorum, verbale per punti con il voto di
 * ciascun consigliere (art. 16.6) e l'esito calcolato (Votazioni).
 *
 * Ripreso dal Dante (AssembleeController, VerbaliController::storeDelibera)
 * con i difetti corretti (analisi §3): una strada sola per le riunioni del
 * CD (difetto 3), presenze dai membri in carica (4), voti di ciascuno ed
 * esito calcolato (5), termini controllati e niente modifiche dopo la
 * convocazione (6), email dei membri dalla scheda del socio (7), presenze
 * solo dal giorno della riunione (8), allegati solo PDF (10).
 */
class Riunioni
{
    public function __construct(private DocumentiOrgani $documenti, private Verbali $verbali)
    {
    }

    // ----------------------------------------------------------------
    // Bozza
    // ----------------------------------------------------------------

    /**
     * @param array{data: string, ora_inizio: string, luogo: ?string, videoconferenza: bool, collegamento: ?string,
     *              punti: list<string>, urgente: bool, motivo_urgenza: ?string} $d
     * @param array<int, UploadedFile>|null $files
     */
    public function salvaBozza(?Seduta $seduta, array $d, ?array $files, User $utente): Seduta
    {
        if ($seduta && ! $seduta->modificabile()) {
            throw ValidationException::withMessages(['data' => 'Dopo la convocazione data, luogo e ordine del giorno non si cambiano: rinvia la riunione e riconvocala.']);
        }
        $punti = array_values(array_filter(array_map(fn ($p) => mb_substr(trim((string) $p), 0, 500), $d['punti']), fn ($p) => $p !== ''));
        if (! $punti) {
            throw ValidationException::withMessages(['punti' => 'Scrivi almeno un punto dell\'ordine del giorno.']);
        }

        $scritti = [];
        try {
            return DB::transaction(function () use ($seduta, $d, $files, $utente, $punti, &$scritti) {
                $s = $seduta ?? new Seduta(['tipo' => 'cd']);
                $s->fill([
                    'data' => $d['data'],
                    'ora_inizio' => $d['ora_inizio'],
                    'luogo' => $d['luogo'] ?: null,
                    'videoconferenza' => $d['videoconferenza'],
                    'collegamento' => $d['videoconferenza'] ? ($d['collegamento'] ?: null) : null,
                    'ordine_del_giorno' => $punti,
                    'urgente' => $d['urgente'],
                    'motivo_urgenza' => $d['urgente'] ? ($d['motivo_urgenza'] ?: null) : null,
                ]);
                if (! $s->exists) {
                    $s->forceFill(['stato' => 'bozza', 'creata_da' => $utente->id]);
                }
                $s->save();
                $scritti = $this->documenti->salva('seduta', $s->id, $files, $utente);

                return $s;
            });
        } catch (\Throwable $e) {
            DocumentiOrgani::cancellaFile($scritti);
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // Convocazione (V4)
    // ----------------------------------------------------------------

    /** Messaggio se la convocazione non può partire adesso, altrimenti null. */
    public static function controllaTermine(Seduta $s): ?string
    {
        $inizio = $s->inizio();
        if (! $s->ora_inizio) {
            return 'Manca l\'ora della riunione.';
        }
        if ($inizio->lte(now())) {
            return 'La data della riunione è già passata.';
        }
        if (now()->diffInHours($inizio, false) < Seduta::ORE_URGENTE) {
            return 'La convocazione deve partire almeno 24 ore prima della riunione (art. 16.1).';
        }
        if (now()->copy()->addDays(Seduta::GIORNI_CD)->gt($inizio)) {
            if (! $s->urgente || ! filled($s->motivo_urgenza)) {
                return 'Mancano meno di 3 giorni alla riunione: si convoca solo come urgente, con il motivo (art. 16.1).';
            }
        }

        return null;
    }

    /** Membri in carica, con la scheda del socio. @return Collection<int, GovernanceCd> */
    public static function membri(): Collection
    {
        return ConsiglioDirettivo::membri()->filter(fn (GovernanceCd $m) => $m->socio !== null)->values();
    }

    /** @return array{inviate: int, senza_email: list<string>} */
    public function convoca(Seduta $seduta, User $utente, bool $prova = false): array
    {
        if ($seduta->stato !== 'bozza') {
            throw ValidationException::withMessages(['convoca' => 'La riunione non è in bozza: è già stata convocata.']);
        }
        if (! $prova && ($m = self::controllaTermine($seduta))) {
            throw ValidationException::withMessages(['convoca' => $m]);
        }
        $documenti = $seduta->documenti();

        if ($prova) {
            $ok = $utente->email && InvioEmail::invia(new ConvocazioneSeduta($seduta, $utente->name, $documenti, true), $utente->email, $utente->name, 'convocazione_cd_prova');

            return ['inviate' => $ok ? 1 : 0, 'senza_email' => []];
        }

        $membri = self::membri();
        if ($membri->isEmpty()) {
            throw ValidationException::withMessages(['convoca' => 'Nel Consiglio Direttivo non c\'è nessun membro in carica.']);
        }
        DB::transaction(function () use ($seduta, $membri) {
            $s = Seduta::lockForUpdate()->findOrFail($seduta->id);
            if ($s->stato !== 'bozza') {
                throw ValidationException::withMessages(['convoca' => 'La riunione è già stata convocata.']);
            }
            $s->forceFill(['stato' => 'convocata', 'convocazione_inviata_il' => now(), 'convocati' => $membri->count()])->save();
            $this->audit($s, 'SEDUTA_CONVOCATA', 'LOW', "Riunione del CD del {$s->data->format('d/m/Y')} convocata: {$membri->count()} membri"
                . ($s->urgente ? ', come urgente' : '') . '.', null, ['data' => $s->data->toDateString(), 'urgente' => $s->urgente]);
        });

        $inviate = 0;
        $senza = [];
        foreach ($membri as $m) {
            $email = trim((string) $m->socio->email);
            $nome = trim($m->socio->nome . ' ' . $m->socio->cognome);
            if ($email === '') {
                $senza[] = $nome;

                continue;
            }
            if (InvioEmail::invia(new ConvocazioneSeduta($seduta->fresh(), $nome, $documenti), $email, $nome, 'convocazione_cd', socio: $m->socio)) {
                $inviate++;
            }
        }

        return ['inviate' => $inviate, 'senza_email' => $senza];
    }

    public function rinvia(Seduta $seduta, string $motivo, bool $avvisa, User $utente): int
    {
        $s = DB::transaction(function () use ($seduta, $motivo) {
            $s = Seduta::lockForUpdate()->findOrFail($seduta->id);
            if ($s->stato !== 'convocata' || $s->verbale) {
                throw ValidationException::withMessages(['motivo_rinvio' => 'Si rinvia solo una riunione convocata, prima delle presenze.']);
            }
            $s->forceFill(['stato' => 'rinviata', 'rinviata_il' => now(), 'rinvio_note' => $motivo])->save();
            $this->audit($s, 'SEDUTA_RINVIATA', 'MEDIUM', "Riunione del CD del {$s->data->format('d/m/Y')} rinviata.", null, ['motivazione' => $motivo]);

            return $s;
        });

        $inviate = 0;
        if ($avvisa) {
            foreach (self::membri() as $m) {
                $email = trim((string) $m->socio->email);
                $nome = trim($m->socio->nome . ' ' . $m->socio->cognome);
                if ($email !== '' && InvioEmail::invia(new ConvocazioneSeduta($s, $nome, collect(), false, $motivo), $email, $nome, 'rinvio_cd', socio: $m->socio)) {
                    $inviate++;
                }
            }
        }

        return $inviate;
    }

    /** Da rinviata torna in bozza, per cambiare data e luogo e convocare di nuovo. */
    public function riconvoca(Seduta $seduta, User $utente): void
    {
        DB::transaction(function () use ($seduta) {
            $s = Seduta::lockForUpdate()->findOrFail($seduta->id);
            if ($s->stato !== 'rinviata') {
                throw ValidationException::withMessages(['riconvoca' => 'Solo una riunione rinviata si riconvoca.']);
            }
            $s->forceFill(['stato' => 'bozza', 'convocazione_inviata_il' => null])->save();
        });
    }

    public function annulla(Seduta $seduta, string $motivo, User $utente): void
    {
        DB::transaction(function () use ($seduta, $motivo) {
            $s = Seduta::lockForUpdate()->findOrFail($seduta->id);
            if (in_array($s->stato, ['svolta', 'annullata'], true) || $s->verbale) {
                throw ValidationException::withMessages(['motivo_annullamento' => 'Una riunione con le presenze registrate non si annulla.']);
            }
            $prima = $s->stato;
            $s->forceFill(['stato' => 'annullata', 'annullata_il' => now(), 'motivo_annullamento' => $motivo])->save();
            $this->audit($s, 'SEDUTA_ANNULLATA', 'HIGH', "Riunione del CD del {$s->data->format('d/m/Y')} annullata.", ['stato' => $prima], ['stato' => 'annullata', 'motivazione' => $motivo]);
        });
    }

    // ----------------------------------------------------------------
    // Presenze
    // ----------------------------------------------------------------

    /**
     * Righe delle presenze: quelle salvate o, la prima volta, i membri in
     * carica. @return Collection<int, SedutaPresenza>
     */
    public static function righePresenze(Seduta $s): Collection
    {
        if ($s->presenze->isNotEmpty()) {
            return $s->presenze;
        }

        return self::membri()->map(fn (GovernanceCd $m) => new SedutaPresenza([
            'socio_id' => $m->socio_id,
            'governance_cd_id' => $m->id,
            'nome' => trim($m->socio->cognome . ' ' . $m->socio->nome),
            'carica' => ConsiglioDirettivo::etichetta($m->carica_codice),
            'presidente' => in_array('presidente', ConsiglioDirettivo::ruoliCarica($m->carica_codice), true),
            'presenza' => 'sede',
            'giustificato' => false,
        ]));
    }

    /**
     * @param array<int, array{presenza: string, giustificato?: bool}> $presenze per socio_id
     */
    public function salvaPresenze(Seduta $seduta, array $presenze, int $presidenteId, int $segretarioId, User $utente): Verbale
    {
        if (! $seduta->presenzeAperte()) {
            throw ValidationException::withMessages(['presenze' => $seduta->data->isFuture()
                ? 'Le presenze si registrano dal giorno della riunione.'
                : 'Le presenze si registrano per una riunione convocata, finché il verbale è in bozza.']);
        }
        $righe = self::righePresenze($seduta);
        foreach ($righe as $r) {
            if (! in_array($presenze[$r->socio_id]['presenza'] ?? null, array_keys(SedutaPresenza::PRESENZE), true)) {
                throw ValidationException::withMessages(['presenze' => "Indica la presenza di {$r->nome}."]);
            }
        }
        $presenti = $righe->filter(fn ($r) => in_array($presenze[$r->socio_id]['presenza'], ['sede', 'video'], true))->pluck('socio_id')->all();
        foreach (['presidente_socio_id' => $presidenteId, 'segretario_socio_id' => $segretarioId] as $campo => $id) {
            if (! in_array($id, $presenti, true)) {
                throw ValidationException::withMessages([$campo => ($campo === 'presidente_socio_id' ? 'Chi presiede' : 'Il segretario verbalizzante') . ' deve essere tra i presenti.']);
            }
        }

        return DB::transaction(function () use ($seduta, $righe, $presenze, $presidenteId, $segretarioId, $utente, $presenti) {
            $s = Seduta::lockForUpdate()->findOrFail($seduta->id);
            foreach ($righe as $r) {
                $p = $presenze[$r->socio_id];
                SedutaPresenza::updateOrCreate(['seduta_id' => $s->id, 'socio_id' => $r->socio_id], [
                    'governance_cd_id' => $r->governance_cd_id,
                    'nome' => $r->nome,
                    'carica' => $r->carica,
                    'presidente' => $r->presidente,
                    'presenza' => $p['presenza'],
                    'giustificato' => $p['presenza'] === 'assente' && ! empty($p['giustificato']),
                ]);
            }
            $s->forceFill([
                'stato' => 'svolta',
                'svolta_il' => $s->svolta_il ?? now(),
                'svolta_da' => $s->svolta_da ?? $utente->id,
                'presidente_socio_id' => $presidenteId,
                'segretario_socio_id' => $segretarioId,
            ])->save();
            $s->load('presenze', 'presidenteSocio', 'segretarioSocio');

            $v = $s->verbale ?? new Verbale(['tipo' => 'cd_riunione', 'libro' => 'cd', 'organo' => 'cd', 'assemblea_id' => $s->id]);
            $v->fill([
                'titolo' => 'Riunione del Consiglio Direttivo del ' . $s->data->format('d/m/Y'),
                'data_seduta' => $s->data->toDateString(),
                'luogo' => trim(implode(' e ', array_filter([$s->luogo, $s->videoconferenza ? 'videoconferenza' : null]))) ?: null,
                'ora_inizio' => $s->ora_inizio,
                'presidente_assemblea' => trim($s->presidenteSocio->nome . ' ' . $s->presidenteSocio->cognome),
                'segretario_verbalizzante' => trim($s->segretarioSocio->nome . ' ' . $s->segretarioSocio->cognome),
                'presenze_cd' => $s->presenze->map(fn ($p) => ['nome' => $p->nome, 'carica' => $p->carica, 'stato' => $p->presenza, 'giustificato' => $p->giustificato])->all(),
            ]);
            if (! $v->exists) {
                $v->forceFill(['stato' => 'bozza', 'redatto_da' => $utente->id]);
            }
            $v->save();
            if (! $v->punti()->exists()) {
                foreach ($s->punti() as $i => $titolo) {
                    VerbalePunto::create(['verbale_id' => $v->id, 'ordine' => $i + 1, 'titolo' => $titolo]);
                }
            }
            // I voti si tengono solo per chi è presente
            VerbaleVoto::whereIn('punto_id', $v->punti()->pluck('id'))->whereNotIn('socio_id', $presenti)->delete();
            foreach ($v->punti()->with('voti')->get() as $p) {
                $this->ricalcolaEsito($p, $s);
            }

            $this->audit($s, 'SEDUTA_PRESENZE', 'LOW', "Riunione del CD del {$s->data->format('d/m/Y')}: presenze registrate, "
                . count($presenti) . ' presenti su ' . $righe->count() . '.', null, ['presenti' => count($presenti), 'componenti' => $righe->count()]);

            return $v;
        });
    }

    /** Il CD è validamente costituito: presenti ≥ maggioranza dei componenti (art. 16.3). */
    public static function costituita(Seduta $s): bool
    {
        $componenti = $s->presenze->count();

        return $componenti > 0 && $s->presenze->filter->presente()->count() >= Votazioni::quorumCd($componenti);
    }

    /**
     * Membri assenti senza giustificazione anche alle due riunioni svolte
     * prima (art. 15.5). @return array<string, list<string>> nome => date
     */
    public static function assenzeConsecutive(Seduta $s): array
    {
        $avvisi = [];
        $precedenti = Seduta::where('tipo', 'cd')->where('stato', 'svolta')->where('id', '!=', $s->id)
            ->where(fn ($q) => $q->where('data', '<', $s->data)->orWhere(fn ($w) => $w->where('data', $s->data)->where('id', '<', $s->id)))
            ->orderByDesc('data')->orderByDesc('id')->limit(2)->with('presenze')->get();
        if ($precedenti->count() < 2) {
            return [];
        }
        foreach ($s->presenze->where('presenza', 'assente')->where('giustificato', false) as $p) {
            $date = [];
            foreach ($precedenti as $prima) {
                $r = $prima->presenze->firstWhere('socio_id', $p->socio_id);
                if ($r && $r->presenza === 'assente' && ! $r->giustificato) {
                    $date[] = $prima->data->format('d/m/Y');
                }
            }
            if (count($date) === 2) {
                $avvisi[$p->nome] = $date;
            }
        }

        return $avvisi;
    }

    // ----------------------------------------------------------------
    // Verbale per punti (VB-2)
    // ----------------------------------------------------------------

    /**
     * @param array<int, array{discussione_html?: ?string, ha_delibera?: bool, tipo_delibera?: ?string, delibera_html?: ?string,
     *                         voti?: array<int, ?string>}> $punti per id del punto
     */
    public function salvaVerbale(Verbale $verbale, array $punti, ?string $oraChiusura, User $utente): void
    {
        if (! $verbale->isBozza() || $verbale->tipo !== 'cd_riunione' || ! $verbale->seduta) {
            throw ValidationException::withMessages(['verbale' => 'Si modifica solo il verbale in bozza di una riunione.']);
        }
        $s = $verbale->seduta->load('presenze');
        $presenti = $s->presenze->filter->presente()->keyBy('socio_id');
        $costituita = self::costituita($s);

        DB::transaction(function () use ($verbale, $punti, $oraChiusura, $s, $presenti, $costituita) {
            $v = Verbale::lockForUpdate()->findOrFail($verbale->id);
            if (! $v->isBozza()) {
                throw ValidationException::withMessages(['verbale' => 'Il verbale è già definitivo.']);
            }
            foreach ($v->punti()->get() as $p) {
                $d = $punti[$p->id] ?? [];
                $ha = ! empty($d['ha_delibera']);
                if ($ha && ! $costituita) {
                    throw ValidationException::withMessages(["punti.{$p->id}.ha_delibera" => "Punto {$p->ordine}: il Consiglio non è validamente costituito, non si delibera (art. 16.3)."]);
                }
                $tipo = $ha ? ($d['tipo_delibera'] ?? 'ordinaria') : null;
                if ($tipo && ! isset(VerbalePunto::TIPI_CD[$tipo])) {
                    $tipo = 'ordinaria';
                }
                $p->fill([
                    'discussione_html' => HtmlSanitizer::pulisci($d['discussione_html'] ?? null) ?: null,
                    'ha_delibera' => $ha,
                    'tipo_delibera' => $tipo,
                    'delibera_html' => $ha ? (HtmlSanitizer::pulisci($d['delibera_html'] ?? null) ?: null) : null,
                ])->save();

                VerbaleVoto::where('punto_id', $p->id)->delete();
                if ($ha) {
                    foreach ($presenti as $socioId => $pr) {
                        $voto = $d['voti'][$socioId] ?? null;
                        VerbaleVoto::create([
                            'punto_id' => $p->id,
                            'socio_id' => $socioId,
                            'nome' => $pr->nome,
                            'carica' => $pr->carica . ($pr->presenza === 'video' ? ' · in videoconferenza' : ''),
                            'presidente' => $pr->presidente,
                            'voto' => in_array($voto, ['favorevole', 'contrario', 'astenuto', 'non_partecipa'], true) ? $voto : null,
                        ]);
                    }
                }
                $this->ricalcolaEsito($p->load('voti'), $s);
            }
            $v->forceFill(['ora_chiusura' => $oraChiusura ?: null])->save();
        });
    }

    /** Esito della delibera dai voti, se ci sono tutti; altrimenti vuoto. */
    public function ricalcolaEsito(VerbalePunto $p, Seduta $s): void
    {
        $presenti = $s->presenze->filter->presente()->count();
        if (! $p->ha_delibera || $p->voti->whereNotNull('voto')->count() < $presenti || $presenti === 0) {
            $p->forceFill(['esito' => null, 'esito_testo' => null, 'favorevoli' => 0, 'contrari' => 0, 'astenuti' => 0, 'non_partecipano' => 0])->save();

            return;
        }
        $conti = $p->voti->countBy('voto')->all();
        $presidente = $p->voti->firstWhere('presidente', true)?->voto;
        $r = Votazioni::esito($p->tipo_delibera ?? 'ordinaria', $conti, $presidente, $s->presenze->count());
        $p->forceFill([
            'esito' => $r['esito'],
            'esito_testo' => $r['testo'],
            'favorevoli' => $conti['favorevole'] ?? 0,
            'contrari' => $conti['contrario'] ?? 0,
            'astenuti' => $conti['astenuto'] ?? 0,
            'non_partecipano' => $conti['non_partecipa'] ?? 0,
        ])->save();
    }

    // ----------------------------------------------------------------
    // Audit
    // ----------------------------------------------------------------

    private function audit(Seduta $s, string $evento, string $rischio, string $dettaglio, ?array $prima, ?array $dopo): void
    {
        AuditLogService::scrivi([
            'evento' => $evento,
            'entita_tipo' => 'Seduta',
            'entita_id' => $s->id,
            'anno' => (int) $s->data->year,
            'livello_rischio' => $rischio,
            'dettaglio' => $dettaglio,
            'old_values' => $prima,
            'new_values' => $dopo,
        ]);
    }
}
