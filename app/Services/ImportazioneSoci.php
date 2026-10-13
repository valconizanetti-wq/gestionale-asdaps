<?php

namespace App\Services;

use App\Models\Rinnovo;
use App\Models\Socio;
use App\Models\User;
use App\Support\LettoreXlsx;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Importazione dei soci dal modello Excel (v0.40.0,
 * analisi-importazione-soci.md, tavole IS-1…IS-3).
 *
 * leggi(): anteprima con errori (bloccano) e avvisi (no) riga per riga.
 * importa(): rilegge e ricontrolla il file, poi tutto o niente in una
 * transazione. Privacy, foto/video e tessere non si importano.
 */
class ImportazioneSoci
{
    public const FOGLIO = 'Soci';

    public const MAX_RIGHE = 500;

    /** Colonne del modello, dalla A, con l'intestazione attesa (senza asterisco). */
    public const COLONNE = [
        'cognome' => 'Cognome',
        'nome' => 'Nome',
        'codice_fiscale' => 'Codice fiscale',
        'indirizzo' => 'Indirizzo',
        'cap' => 'CAP',
        'comune' => 'Comune',
        'provincia' => 'Provincia',
        'email' => 'Email',
        'telefono' => 'Telefono',
        'ammissione' => 'Data di ammissione',
        'fondatore' => 'Fondatore',
        'quota_fino' => 'Quota valida fino al',
        'gen_nome' => 'Genitore: cognome e nome',
        'gen_cf' => 'Genitore: codice fiscale',
        'gen_relazione' => 'Genitore: relazione',
        'gen_telefono' => 'Genitore: telefono',
        'gen_email' => 'Genitore: email',
        'note' => 'Note',
    ];

    private const DATE = ['ammissione', 'quota_fino'];

    public const RELAZIONI = ['padre' => 'Padre', 'madre' => 'Madre', 'tutore' => 'Tutore'];

    /** File in lettura (serve per le date: sistema 1900 o 1904). */
    private ?LettoreXlsx $lettore = null;

    /**
     * @return array{errore: ?string, righe: list<array>, esempio: bool, conteggi: array{pronte: int, avvisi: int, errori: int}}
     */
    public function leggi(string $percorso, ?Carbon $oggi = null): array
    {
        $oggi = ($oggi ?? now())->copy()->startOfDay();
        $vuoto = ['errore' => null, 'righe' => [], 'esempio' => false, 'conteggi' => ['pronte' => 0, 'avvisi' => 0, 'errori' => 0]];

        try {
            $lettore = new LettoreXlsx($percorso);
            if (! in_array(self::FOGLIO, $lettore->nomiFogli(), true)) {
                return ['errore' => 'Il file non è il modello dei soci: manca il foglio «' . self::FOGLIO . '».'] + $vuoto;
            }
            $righe = $lettore->righe(self::FOGLIO);
            $this->lettore = $lettore;
        } catch (\RuntimeException $e) {
            return ['errore' => $e->getMessage()] + $vuoto;
        }

        // Intestazioni della riga 1
        $intestazione = $righe[1] ?? [];
        unset($righe[1]);
        $i = 0;
        foreach (self::COLONNE as $chiave => $attesa) {
            $trovata = self::normalizzaIntestazione((string) ($intestazione[$i] ?? ''));
            if ($trovata !== self::normalizzaIntestazione($attesa)) {
                $lettera = chr(65 + $i);

                return ['errore' => "Le colonne non sono quelle del modello (colonna {$lettera}: atteso «{$attesa}», trovato «" . ($intestazione[$i] ?? '') . '»). Scarica il modello e copia lì i dati.'] + $vuoto;
            }
            $i++;
        }

        // Righe con il solo «Fondatore» (es. «No» copiato giù per la colonna):
        // non sono soci
        $colFondatore = array_search('fondatore', array_keys(self::COLONNE), true);
        foreach ($righe as $n => $celle) {
            if (array_keys($celle) === [$colFondatore]) {
                unset($righe[$n]);
            }
        }

        // Riga d'esempio: riconosciuta dal contenuto (Paolo, 13/10/2026)
        $esempio = false;
        foreach ($righe as $n => $celle) {
            if (str_contains(mb_strtoupper((string) ($celle[0] ?? '')), 'ESEMPIO')) {
                unset($righe[$n]);
                $esempio = true;
            }
        }
        if (! $righe) {
            return ['errore' => 'Il foglio «Soci» non ha righe da importare' . ($esempio ? ' (a parte la riga d\'esempio).' : '.')] + $vuoto + ['esempio' => $esempio];
        }
        if (count($righe) > self::MAX_RIGHE) {
            return ['errore' => 'Troppe righe: al massimo ' . self::MAX_RIGHE . ' soci per file.'] + $vuoto;
        }

        $lette = [];
        foreach ($righe as $n => $celle) {
            $lette[] = $this->riga($n, $celle, $oggi);
        }
        $this->controllaDoppioni($lette);

        $conteggi = ['pronte' => 0, 'avvisi' => 0, 'errori' => 0];
        foreach ($lette as &$r) {
            $r['esito'] = $r['errori'] ? 'errore' : ($r['avvisi'] ? 'avviso' : 'pronta');
            $conteggi[$r['errori'] ? 'errori' : ($r['avvisi'] ? 'avvisi' : 'pronte')]++;
        }
        unset($r);

        return ['errore' => null, 'righe' => $lette, 'esempio' => $esempio, 'conteggi' => $conteggi];
    }

    /**
     * Importa tutte le righe, solo se il file non ha errori.
     *
     * @return array{soci: int, minorenni: int, quote: int}
     * @throws \RuntimeException con un messaggio per l'utente
     */
    public function importa(string $percorso, string $nomeFile, User $utente): array
    {
        $letto = $this->leggi($percorso);
        if ($letto['errore']) {
            throw new \RuntimeException($letto['errore']);
        }
        if ($letto['conteggi']['errori'] > 0) {
            throw new \RuntimeException("Nel file ci sono {$letto['conteggi']['errori']} righe con errori (magari nel frattempo è stato inserito un socio con lo stesso codice fiscale): correggile e ricarica il file.");
        }

        $avvio = RinnoviQuota::annoAvvio();

        return DB::transaction(function () use ($letto, $nomeFile, $utente, $avvio) {
            $esito = ['soci' => 0, 'minorenni' => 0, 'quote' => 0];

            foreach ($letto['righe'] as $r) {
                // Ricontrollo sotto lock: due importazioni insieme non creano doppioni
                if (Socio::withTrashed()->where('codice_fiscale', $r['codice_fiscale'])->lockForUpdate()->exists()) {
                    throw new \RuntimeException("Il codice fiscale {$r['codice_fiscale']} (riga {$r['numero']}) è già registrato: ricarica il file.");
                }

                $socio = new Socio();
                $socio->forceFill([
                    'tipo' => $r['minorenne'] ? 'minorenne' : 'ordinario',
                    'fondatore' => $r['fondatore'],
                    'stato' => 'attivo',
                    'cognome' => $r['cognome'],
                    'nome' => $r['nome'],
                    'codice_fiscale' => $r['codice_fiscale'],
                    'data_nascita' => $r['nascita']['data'],
                    'luogo_nascita' => $r['nascita']['luogo'],
                    'sesso' => $r['nascita']['sesso'],
                    'indirizzo' => $r['indirizzo'],
                    'cap' => $r['cap'],
                    'citta' => $r['comune'],
                    'provincia' => $r['provincia'],
                    'email' => $r['email'],
                    'telefono' => $r['telefono'],
                    'tutore_nome' => $r['minorenne'] ? $r['gen_nome'] : null,
                    'tutore_codice_fiscale' => $r['minorenne'] ? $r['gen_cf'] : null,
                    'tutore_relazione' => $r['minorenne'] ? (self::RELAZIONI[$r['gen_relazione']] ?? null) : null,
                    'tutore_telefono' => $r['minorenne'] ? $r['gen_telefono'] : null,
                    'tutore_email' => $r['minorenne'] ? $r['gen_email'] : null,
                    'data_prima_iscrizione' => $r['ammissione'],
                    'anno_iscrizione' => (int) substr($r['ammissione'], 0, 4),
                    'quota_scadenza' => $r['quota_fino'],
                    'note' => $r['note'],
                    'inserito_da' => $utente->id,
                    'importato' => true,
                ])->save();
                $esito['soci']++;
                $esito['minorenni'] += $r['minorenne'] ? 1 : 0;

                // Quote del vecchio gestionale dall'anno d'avvio della
                // contabilità: una riga senza ricevuta né movimento per anno
                // (D4), così la situazione dell'anno risulta «in regola».
                if ($r['quota_fino']) {
                    $fino = (int) substr($r['quota_fino'], 0, 4);
                    for ($anno = max($avvio, (int) substr($r['ammissione'], 0, 4)); $anno <= $fino; $anno++) {
                        $categoria = RinnoviQuota::categoria($socio, $anno);
                        $pagata = Carbon::parse($r['ammissione'])->max(Carbon::create($anno - 1, 10, 1));
                        (new Rinnovo())->forceFill([
                            'socio_id' => $socio->id,
                            'anno' => $anno,
                            'importo' => RinnoviQuota::importo($categoria),
                            'categoria' => $categoria,
                            'data_pagamento' => $pagata->min(now())->toDateString(),
                            'metodo_pagamento' => 'altro',
                            'numero_ricevuta' => null,
                            'ricevuta_stato' => 'non_generata',
                            'note' => 'Quota incassata nel vecchio gestionale (importazione dei soci): senza ricevuta né movimento.',
                            'registrato_da' => $utente->id,
                        ])->save();
                        $esito['quote']++;
                    }
                }
            }

            AuditLogService::scrivi([
                'evento' => 'IMPORT_SOCI',
                'operazione' => 'created',
                'entita_tipo' => 'ImportazioneSoci',
                'livello_rischio' => 'HIGH',
                'dettaglio' => "Importati {$esito['soci']} soci dal modello Excel ({$esito['minorenni']} minorenni, {$esito['quote']} quote del vecchio gestionale senza ricevuta). Privacy da raccogliere con il link.",
                'new_values' => ['file' => mb_substr($nomeFile, 0, 200)] + $esito,
            ]);

            return $esito;
        });
    }

    /** Una riga del foglio: valori ripuliti, dati dal codice fiscale, controlli. */
    private function riga(int $numero, array $celle, Carbon $oggi): array
    {
        $v = [];
        $i = 0;
        foreach (array_keys(self::COLONNE) as $chiave) {
            $v[$chiave] = $celle[$i] ?? null;
            $i++;
        }

        $errori = [];
        $avvisi = [];
        $testo = fn ($x) => $x === null ? null : (trim(preg_replace('/\s+/u', ' ', is_float($x) ? self::numeroTesto($x) : (string) $x)) ?: null);

        $r = [
            'numero' => $numero,
            'cognome' => $testo($v['cognome']),
            'nome' => $testo($v['nome']),
            'codice_fiscale' => CodiceFiscale::normalizza((string) $testo($v['codice_fiscale'])),
            'indirizzo' => $testo($v['indirizzo']),
            'cap' => $testo($v['cap']),
            'comune' => $testo($v['comune']),
            'provincia' => mb_strtoupper((string) $testo($v['provincia'])) ?: null,
            'email' => ($e = $testo($v['email'])) ? mb_strtolower($e) : null,
            'telefono' => $testo($v['telefono']),
            'note' => $testo($v['note']),
            'gen_nome' => $testo($v['gen_nome']),
            'gen_cf' => ($g = $testo($v['gen_cf'])) ? CodiceFiscale::normalizza($g) : null,
            'gen_relazione' => ($g = $testo($v['gen_relazione'])) ? mb_strtolower($g) : null,
            'gen_telefono' => $testo($v['gen_telefono']),
            'gen_email' => ($g = $testo($v['gen_email'])) ? mb_strtolower($g) : null,
            'nascita' => ['data' => null, 'luogo' => null, 'sesso' => null, 'eta' => null],
            'minorenne' => false,
            'fondatore' => false,
        ];

        // CAP numerico in Excel: lo zero davanti si perde (5014 → 05014)
        if ($r['cap'] !== null && preg_match('/^\d{3,4}$/', $r['cap'])) {
            $r['cap'] = str_pad($r['cap'], 5, '0', STR_PAD_LEFT);
        }

        foreach (['cognome' => 'il cognome', 'nome' => 'il nome', 'indirizzo' => "l'indirizzo", 'comune' => 'il comune'] as $campo => $nome) {
            if ($r[$campo] === null) {
                $errori[] = "Manca {$nome}.";
            }
        }
        foreach (['cognome' => 100, 'nome' => 100, 'indirizzo' => 255, 'comune' => 100, 'telefono' => 30, 'gen_telefono' => 30, 'gen_nome' => 200, 'email' => 255, 'gen_email' => 255] as $campo => $max) {
            if ($r[$campo] !== null && mb_strlen($r[$campo]) > $max) {
                $errori[] = ucfirst(str_replace(['gen_', '_'], ['genitore: ', ' '], $campo)) . " troppo lungo (al massimo {$max} caratteri).";
            }
        }

        // Codice fiscale e dati di nascita
        if ($r['codice_fiscale'] === '') {
            $errori[] = 'Manca il codice fiscale.';
        } else {
            $cf = CodiceFiscale::analizza($r['codice_fiscale'], $oggi);
            if (! $cf['valido']) {
                $errori[] = $cf['errore'];
            } else {
                $eta = $cf['data_nascita']->diffInYears($oggi);
                $r['nascita'] = ['data' => $cf['data_nascita']->toDateString(), 'luogo' => CodiceFiscale::luogoLeggibile($cf), 'sesso' => $cf['sesso'], 'eta' => (int) floor($eta)];
                $r['minorenne'] = $cf['data_nascita']->copy()->addYears(18)->greaterThan($oggi);
                if ($r['cognome'] && $r['nome'] && ! CodiceFiscale::corrispondeANominativo($r['codice_fiscale'], $r['cognome'], $r['nome'])) {
                    $avvisi[] = 'Il codice fiscale non corrisponde a cognome e nome: controlla che sia proprio di questa persona.';
                }
            }
        }

        if ($r['cap'] === null) {
            $errori[] = 'Manca il CAP.';
        } elseif (! preg_match('/^\d{5}$/', $r['cap'])) {
            $errori[] = 'Il CAP deve avere 5 cifre.';
        }
        if ($r['provincia'] === null) {
            $errori[] = 'Manca la provincia.';
        } elseif (! preg_match('/^[A-Z]{2}$/', $r['provincia'])) {
            $errori[] = 'La provincia va scritta con la sigla di 2 lettere (es. TR).';
        }

        // Email e telefono: obbligatori per i maggiorenni
        if ($r['email'] !== null && ! filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
            $errori[] = "L'email «{$r['email']}» non è scritta bene.";
        }
        if (! $r['minorenne'] && $r['nascita']['data']) {
            if ($r['email'] === null) {
                $errori[] = "Maggiorenne: manca l'email.";
            }
            if ($r['telefono'] === null) {
                $errori[] = 'Maggiorenne: manca il telefono.';
            }
        }

        // Date
        foreach (self::DATE as $campo) {
            [$r[$campo], $err] = $this->data($v[$campo]);
            if ($err) {
                $errori[] = ($campo === 'ammissione' ? 'Data di ammissione' : 'Quota valida fino al') . ': ' . $err;
            }
        }
        if ($r['ammissione'] === null && ! self::haErrore($errori, 'Data di ammissione')) {
            $errori[] = 'Manca la data di ammissione.';
        } elseif ($r['ammissione']) {
            if ($r['ammissione'] > $oggi->toDateString()) {
                $errori[] = 'La data di ammissione è nel futuro.';
            } elseif ($r['nascita']['data'] && $r['ammissione'] < $r['nascita']['data']) {
                $errori[] = 'La data di ammissione è prima della nascita.';
            }
        }
        if ($r['quota_fino']) {
            if (substr($r['quota_fino'], 5) !== '12-31') {
                $errori[] = 'Quota valida fino al: deve essere il 31/12 di un anno.';
            } elseif ($r['ammissione'] && (int) substr($r['quota_fino'], 0, 4) < (int) substr($r['ammissione'], 0, 4)) {
                $errori[] = "Quota valida fino al: è prima dell'anno di ammissione.";
            } elseif ($r['quota_fino'] < $oggi->toDateString()) {
                $avvisi[] = 'Quota scaduta il ' . Carbon::parse($r['quota_fino'])->format('d/m/Y') . ': si registra lo stesso.';
            } elseif ((int) substr($r['quota_fino'], 0, 4) > $oggi->year + 1) {
                $errori[] = 'Quota valida fino al: più in là dell\'anno prossimo, controlla la data.';
            }
        }

        // Fondatore
        $f = mb_strtolower((string) $testo($v['fondatore']));
        if (in_array($f, ['sì', 'si', 'sí'], true) || $v['fondatore'] === true) {
            $r['fondatore'] = true;
        } elseif (! in_array($f, ['', 'no'], true) && $v['fondatore'] !== false) {
            $errori[] = 'Fondatore: scrivi «Sì» o «No» (vuoto vale No).';
        }

        // Genitore: obbligatorio per i minorenni
        $genitore = array_filter([$r['gen_nome'], $r['gen_cf'], $r['gen_relazione'], $r['gen_telefono'], $r['gen_email']]);
        if ($r['minorenne']) {
            $mancano = [];
            foreach (['gen_nome' => 'cognome e nome', 'gen_cf' => 'codice fiscale', 'gen_relazione' => 'relazione', 'gen_telefono' => 'telefono', 'gen_email' => 'email'] as $campo => $nome) {
                if ($r[$campo] === null) {
                    $mancano[] = $nome;
                }
            }
            if ($mancano) {
                $errori[] = 'Minorenne: del genitore mancano ' . User::elenco($mancano) . '.';
            }
            if ($r['gen_cf']) {
                $gcf = CodiceFiscale::analizza($r['gen_cf'], $oggi);
                if (! $gcf['valido']) {
                    $errori[] = 'Codice fiscale del genitore: ' . lcfirst((string) $gcf['errore']);
                } elseif ($gcf['data_nascita']->copy()->addYears(18)->greaterThan($oggi)) {
                    $errori[] = 'Il codice fiscale del genitore è di un minorenne.';
                } elseif ($r['gen_cf'] === $r['codice_fiscale']) {
                    $errori[] = 'Il codice fiscale del genitore è uguale a quello del socio.';
                }
            }
            if ($r['gen_relazione'] && ! isset(self::RELAZIONI[$r['gen_relazione']])) {
                $errori[] = 'Relazione del genitore: padre, madre o tutore.';
            }
            if ($r['gen_email'] && ! filter_var($r['gen_email'], FILTER_VALIDATE_EMAIL)) {
                $errori[] = "L'email del genitore «{$r['gen_email']}» non è scritta bene.";
            }
        } elseif ($genitore && $r['nascita']['data']) {
            $avvisi[] = 'Maggiorenne: i dati del genitore non si importano.';
        }

        // Già nel gestionale
        if ($r['codice_fiscale'] !== '' && ! self::haErrore($errori, 'codice fiscale')) {
            $esistente = Socio::withTrashed()->where('codice_fiscale', $r['codice_fiscale'])->first();
            if ($esistente) {
                $errori[] = $esistente->trashed()
                    ? "C'è una scheda eliminata con questo codice fiscale ({$esistente->cognome} {$esistente->nome}): ripristinala da Soci › Filtri › Stato «Schede eliminate» e togli la riga."
                    : "Già nel libro soci: {$esistente->cognome} {$esistente->nome}. Togli la riga.";
            } elseif ($r['cognome'] && $r['nome']) {
                $omonimo = Socio::where('cognome', $r['cognome'])->where('nome', $r['nome'])->first();
                if ($omonimo) {
                    $avvisi[] = "Esiste già {$omonimo->cognome} {$omonimo->nome} (" . mb_substr((string) $omonimo->codice_fiscale, 0, 8) . "…): è un'altra persona?";
                }
            }
        }

        return $r + ['errori' => array_values(array_unique($errori)), 'avvisi' => $avvisi];
    }

    /** Lo stesso codice fiscale su più righe: errore su tutte. */
    private function controllaDoppioni(array &$righe): void
    {
        $per = [];
        foreach ($righe as $k => $r) {
            if ($r['codice_fiscale'] !== '') {
                $per[$r['codice_fiscale']][] = $k;
            }
        }
        foreach ($per as $chiavi) {
            if (count($chiavi) > 1) {
                $numeri = array_map(fn ($k) => $righe[$k]['numero'], $chiavi);
                foreach ($chiavi as $k) {
                    $altre = array_diff($numeri, [$righe[$k]['numero']]);
                    $righe[$k]['errori'][] = 'Codice fiscale ripetuto nel file (anche alla riga ' . implode(', ', $altre) . ').';
                }
            }
        }
    }

    /** @return array{0: ?string, 1: ?string} data Y-m-d e messaggio d'errore */
    private function data(mixed $x): array
    {
        if ($x === null || $x === '') {
            return [null, null];
        }
        if (is_float($x)) {
            $d = $this->lettore ? $this->lettore->data($x) : LettoreXlsx::dataExcel($x);

            return $d && $d->year >= 1900 ? [$d->toDateString(), null] : [null, 'non è una data.'];
        }
        $t = trim((string) $x);
        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d'] as $formato) {
            $d = \DateTimeImmutable::createFromFormat('!' . $formato, $t);
            if ($d && $d->format($formato) === $t) {
                return [$d->format('Y-m-d'), null];
            }
        }

        return [null, "«{$t}» non è una data (scrivila come gg/mm/aaaa)."];
    }

    /** 3331234567.0 → «3331234567»; 5014.0 → «5014». */
    private static function numeroTesto(float $x): string
    {
        return floor($x) === $x ? number_format($x, 0, '', '') : (string) $x;
    }

    private static function haErrore(array $errori, string $parola): bool
    {
        foreach ($errori as $e) {
            if (stripos($e, $parola) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function normalizzaIntestazione(string $h): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', str_replace('*', '', $h))));
    }
}
