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
