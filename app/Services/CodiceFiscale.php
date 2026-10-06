<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Codice fiscale delle persone fisiche (D.M. 23/12/1976): controllo e
 * ricavo di data di nascita, sesso e luogo di nascita.
 *
 * Nella domanda di iscrizione il richiedente scrive solo il codice
 * fiscale; data, sesso e luogo di nascita si ricavano da qui, sia nel
 * browser (anteprima) sia sul server (unico valore che conta).
 *
 * Gestisce l'omocodia: in caso di codici uguali l'Agenzia delle Entrate
 * sostituisce le cifre, partendo da destra, con le lettere LMNPQRSTUV.
 */
class CodiceFiscale
{
    private const MESI = 'ABCDEHLMPRST';

    /** Lettere che sostituiscono le cifre 0-9 nei codici omocodici. */
    private const OMOCODIA = 'LMNPQRSTUV';

    /** Posizioni (da 0) che in un codice normale sono cifre. */
    private const POSIZIONI_NUMERICHE = [6, 7, 9, 10, 12, 13, 14];

    private const DISPARI = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    /** @var array<string, list<array{0:string,1:string,2:?string,3:?string}>>|null */
    private static ?array $catastali = null;

    public static function normalizza(?string $codice): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $codice));
    }

    /**
     * Analizza il codice. Restituisce i dati ricavati oppure, in 'errore',
     * il motivo per cui il codice non è valido (testo per l'utente).
     *
     * @return array{valido: bool, errore: ?string, data_nascita: ?CarbonImmutable,
     *               sesso: ?string, codice_catastale: ?string, luogo: ?string,
     *               provincia: ?string, estero: bool}
     */
    public static function analizza(?string $codice, ?\DateTimeInterface $oggi = null): array
    {
        $cf = self::normalizza($codice);
        $esito = [
            'valido' => false, 'errore' => null, 'data_nascita' => null, 'sesso' => null,
            'codice_catastale' => null, 'luogo' => null, 'provincia' => null, 'estero' => false,
        ];

        if (! preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/', $cf)) {
            $esito['errore'] = 'Il codice fiscale deve avere 16 caratteri nel formato corretto.';
            return $esito;
        }

        if (self::carattereControllo(substr($cf, 0, 15)) !== $cf[15]) {
            $esito['errore'] = 'Il codice fiscale non è corretto: controlla di averlo scritto bene.';
            return $esito;
        }

        $base = self::deomocodizza($cf);

        $mese = strpos(self::MESI, $base[8]) + 1;
        $giorno = (int) substr($base, 9, 2);
        $sesso = $giorno > 40 ? 'F' : 'M';
        $giorno = $giorno > 40 ? $giorno - 40 : $giorno;
        $anno2 = (int) substr($base, 6, 2);

        $oggi = CarbonImmutable::instance($oggi ?? now());
        // Secolo: l'anno più recente che non cade nel futuro. Per un'associazione
        // sportiva il caso di chi ha più di 100 anni non si pone.
        $anno = 2000 + $anno2;
        if ($anno > $oggi->year) {
            $anno -= 100;
        }

        if (! checkdate($mese, $giorno, $anno)) {
            $esito['errore'] = 'La data di nascita contenuta nel codice fiscale non esiste.';
            return $esito;
        }

        $nascita = CarbonImmutable::create($anno, $mese, $giorno)->startOfDay();
        if ($nascita->greaterThan($oggi)) {
            $esito['errore'] = 'La data di nascita contenuta nel codice fiscale è nel futuro.';
            return $esito;
        }

        $catastale = substr($base, 11, 4);
        $luogo = self::luogo($catastale, $nascita);
        if ($luogo === null) {
            $esito['errore'] = 'Il comune o lo Stato di nascita del codice fiscale non è riconosciuto.';
            return $esito;
        }

        return [
            'valido' => true,
            'errore' => null,
            'data_nascita' => $nascita,
            'sesso' => $sesso,
            'codice_catastale' => $catastale,
            'luogo' => $luogo['nome'],
            'provincia' => $luogo['estero'] ? null : $luogo['provincia'],
            'estero' => $luogo['estero'],
        ];
    }

    /** "Orvieto (TR)" oppure, per l'estero, "Stati Uniti D'America". */
    public static function luogoLeggibile(array $esito): ?string
    {
        if (! $esito['valido']) {
            return null;
        }

        return $esito['estero'] ? $esito['luogo'] : "{$esito['luogo']} ({$esito['provincia']})";
    }

    /**
     * Verifica che le prime sei lettere corrispondano a cognome e nome.
     * Serve a intercettare il codice di un'altra persona (es. il genitore
     * che scrive il proprio codice al posto di quello del figlio).
     */
    public static function corrispondeANominativo(string $codice, string $cognome, string $nome): bool
    {
        $cf = self::normalizza($codice);

        return substr($cf, 0, 3) === self::codiceCognome($cognome)
            && substr($cf, 3, 3) === self::codiceNome($nome);
    }

    public static function codiceCognome(string $cognome): string
    {
        [$consonanti, $vocali] = self::lettere($cognome);

        return substr($consonanti . $vocali . 'XXX', 0, 3);
    }

    public static function codiceNome(string $nome): string
    {
        [$consonanti, $vocali] = self::lettere($nome);

        if (strlen($consonanti) >= 4) {
            return $consonanti[0] . $consonanti[2] . $consonanti[3];
        }

        return substr($consonanti . $vocali . 'XXX', 0, 3);
    }

    public static function carattereControllo(string $primi15): string
    {
        $somma = 0;
        for ($i = 0; $i < 15; $i++) {
            $c = $primi15[$i];
            // Le posizioni si contano da 1: dispari = indici 0, 2, 4…
            $somma += $i % 2 === 0
                ? self::DISPARI[$c]
                : (ctype_digit($c) ? (int) $c : ord($c) - ord('A'));
        }

        return chr(ord('A') + $somma % 26);
    }

    /** Riporta a cifre le lettere di omocodia nelle posizioni numeriche. */
    private static function deomocodizza(string $cf): string
    {
        foreach (self::POSIZIONI_NUMERICHE as $p) {
            $i = strpos(self::OMOCODIA, $cf[$p]);
            if ($i !== false) {
                $cf[$p] = (string) $i;
            }
        }

        return $cf;
    }

    /** @return array{0: string, 1: string} consonanti e vocali, senza accenti */
    private static function lettere(string $testo): array
    {
        $ascii = strtoupper(\Illuminate\Support\Str::ascii($testo));
        $ascii = preg_replace('/[^A-Z]/', '', $ascii);

        return [
            preg_replace('/[AEIOU]/', '', $ascii),
            preg_replace('/[^AEIOU]/', '', $ascii),
        ];
    }

    /** @return array{nome: string, provincia: string, estero: bool}|null */
    private static function luogo(string $codice, CarbonImmutable $nascita): ?array
    {
        self::$catastali ??= require resource_path('data/codici-catastali.php');

        $voci = self::$catastali[$codice] ?? null;
        if (! $voci) {
            return null;
        }

        $data = $nascita->toDateString();
        $scelta = null;
        foreach ($voci as [$nome, $sigla, $dal, $al]) {
            if (($dal === null || $dal <= $data) && ($al === null || $al >= $data)) {
                $scelta = [$nome, $sigla];
            }
        }
        // Nessuna voce valida alla data (dati storici incompleti): l'ultima
        // denominazione nota, meglio di un rifiuto del codice.
        $scelta ??= [end($voci)[0], end($voci)[1]];

        return ['nome' => $scelta[0], 'provincia' => $scelta[1], 'estero' => $scelta[1] === 'EE'];
    }
}
