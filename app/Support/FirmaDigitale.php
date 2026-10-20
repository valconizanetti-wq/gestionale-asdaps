<?php

namespace App\Support;

/**
 * Controlla che un file contenga una firma digitale (audit-log.md §9,
 * v0.34.1). Riconosce i due formati della firma digitale e della firma
 * con CIE:
 *  - PAdES: PDF con la firma dentro (dizionario di firma con /ByteRange
 *    e un /SubFilter di firma);
 *  - CAdES: file .p7m, busta PKCS#7 "signedData" (binaria o in base64).
 *
 * Controlla che la firma CI SIA, non che sia valida: la validità del
 * certificato (catena di fiducia, revoca, data) si verifica con gli
 * strumenti dei certificatori o con il servizio di verifica dell'AgID.
 */
class FirmaDigitale
{
    /** OID 1.2.840.113549.1.7.2 (signedData) codificato in DER. */
    private const OID_SIGNED_DATA = "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x07\x02";

    /** @return 'PAdES'|'CAdES'|null il tipo di firma trovato, null se non c'è */
    public static function rileva(string $percorso, string $estensione): ?string
    {
        $contenuto = @file_get_contents($percorso);
        if ($contenuto === false || $contenuto === '') {
            return null;
        }

        return strtolower($estensione) === 'p7m'
            ? (self::busta($contenuto) ? 'CAdES' : null)
            : (self::pdfFirmato($contenuto) ? 'PAdES' : null);
    }

    private static function pdfFirmato(string $c): bool
    {
        return str_starts_with(ltrim(substr($c, 0, 1024)), '%PDF')
            && preg_match('#/ByteRange\s*\[\s*\d+\s+\d+\s+\d+\s+\d+\s*\]#', $c) === 1
            && preg_match('#/SubFilter\s*/(adbe\.pkcs7\.(detached|sha1)|ETSI\.CAdES\.detached|adbe\.x509\.rsa_sha1)#', $c) === 1;
    }

    /**
     * Firme di un PDF firmato (PAdES): un dizionario di firma, con il suo
     * /ByteRange, per ogni firma (v0.44.0, verbali, V14). Per un .p7m le
     * firme non si contano in modo affidabile: null.
     */
    public static function contaFirme(string $percorso, string $estensione): ?int
    {
        if (strtolower($estensione) === 'p7m') {
            return null;
        }
        $contenuto = (string) @file_get_contents($percorso);

        return preg_match_all('#/ByteRange\s*\[\s*\d+\s+\d+\s+\d+\s+\d+\s*\]#', $contenuto);
    }

    /**
     * Nomi dei firmatari scritti nei dizionari di firma (/Name), se il
     * programma di firma li ha messi. Solo testo: il certificato non si
     * verifica. @return list<string>
     */
    public static function firmatari(string $percorso, string $estensione): array
    {
        if (strtolower($estensione) === 'p7m') {
            return [];
        }
        $contenuto = (string) @file_get_contents($percorso);
        preg_match_all('#/Type\s*/Sig\b[^>]{0,2000}?/Name\s*\(((?:\\\\.|[^\\\\)]){1,120})\)|/Name\s*\(((?:\\\\.|[^\\\\)]){1,120})\)[^>]{0,2000}?/Type\s*/Sig\b#s', $contenuto, $m, PREG_SET_ORDER);
        $nomi = [];
        foreach ($m as $r) {
            $nome = stripcslashes($r[1] !== '' ? $r[1] : ($r[2] ?? ''));
            // Testo UTF-16 (BOM FE FF) dei PDF
            if (str_starts_with($nome, "\xFE\xFF")) {
                $nome = (string) mb_convert_encoding(substr($nome, 2), 'UTF-8', 'UTF-16BE');
            }
            $nome = trim(preg_replace('/[^\p{L}\p{N} .\'-]/u', '', $nome));
            if ($nome !== '' && ! in_array($nome, $nomi, true)) {
                $nomi[] = mb_substr($nome, 0, 80);
            }
        }

        return $nomi;
    }

    private static function busta(string $c): bool
    {
        // .p7m in base64 (con o senza intestazione PEM): si decodifica.
        if ($c[0] !== "\x30") {
            $testo = preg_replace('/-----[A-Z0-9 ]+-----|\s+/', '', $c);
            $c = base64_decode((string) $testo, true) ?: '';
        }

        return $c !== '' && $c[0] === "\x30" && str_contains(substr($c, 0, 64), self::OID_SIGNED_DATA);
    }
}
