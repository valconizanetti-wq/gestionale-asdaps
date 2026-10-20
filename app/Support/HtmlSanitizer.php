<?php

namespace App\Support;

/**
 * Pulizia dell'HTML scritto con l'editor avanzato (v0.44.0, analisi V3):
 * punti e delibere dei verbali, proposte di consultazione, rettifiche.
 *
 * Ripresa dal Gestionale APS del Dante (App\Services\HtmlSanitizer, v71),
 * con la stessa lista di tag, attributi e proprietà CSS ammessi. Differenze:
 * - nel Gestionale ASD l'editor non ha il pulsante «sorgente» (HTML grezzo):
 *   la pulizia resta comunque su ogni salvataggio, perché un modulo si può
 *   sempre mandare a mano;
 * - niente marcatore di interruzione di pagina (pdf-pagebreak), che la
 *   barra dell'editor non offre;
 * - testoSemplice(): la stessa pulizia ridotta al solo testo, per i campi
 *   che finiscono negli elenchi e nelle email.
 *
 * Il risultato si stampa senza escape nelle viste e nei PDF: tag e
 * attributi non ammessi spariscono, script e simili con il loro contenuto,
 * i link restano solo se http o https.
 */
class HtmlSanitizer
{
    /**
     * Tag ammessi. Qualsiasi altro tag (non presente ne' qui ne' nella
     * lista sottostante) viene rimosso MA il suo contenuto testuale/dei
     * figli viene preservato, spostato al livello del genitore.
     */
    private const TAG_AMMESSI = [
        'p', 'br', 'strong', 'em', 'u', 's', 'sup', 'sub',
        'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre',
        'table', 'thead', 'tbody', 'tr', 'td', 'th',
        'a', 'hr', 'span', 'div',
    ];

    /**
     * Tag da eliminare INTERAMENTE, compreso il contenuto: non hanno senso
     * come testo semplice in un verbale (codice, media, form, embed).
     */
    private const TAG_DA_ELIMINARE_CON_CONTENUTO = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'select', 'textarea',
        'img', 'video', 'audio', 'source', 'svg', 'link', 'meta', 'head',
    ];

    /**
     * Proprieta' CSS ammesse dentro l'attributo style. Tutte le altre
     * vengono scartate dalla stringa; l'attributo style resta solo se ne
     * sopravvive almeno una.
     */
    private const STYLE_PROPRIETA_AMMESSE = [
        'font-family', 'font-size', 'color', 'background-color',
        'text-align', 'text-decoration', 'margin-left', 'margin-right',
        'border-top', 'border-collapse', 'width',
    ];

    public static function pulisci(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        // DOMDocument senza indicazione esplicita di charset interpreta
        // l'HTML come ISO-8859-1 e corrompe gli accentati: wrapper con
        // dichiarazione XML encoding, tecnica standard per forzare UTF-8.
        $wrapper = '<?xml encoding="UTF-8"><div id="gs-sanitize-root">' . $html . '</div>';

        $dom = new \DOMDocument();
        $precedente = libxml_use_internal_errors(true);
        $dom->loadHTML($wrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($precedente);

        $root = $dom->getElementById('gs-sanitize-root');
        if (!$root) {
            return '';
        }

        self::pulisciNodo($dom, $root);
        self::riparaListe($dom, $root);

        $risultato = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $risultato .= $dom->saveHTML($child);
        }

        return $risultato;
    }

    private static function pulisciNodo(\DOMDocument $dom, \DOMNode $nodo): void
    {
        // Copia statica dei figli: l'albero viene modificato durante il ciclo.
        $figli = iterator_to_array($nodo->childNodes);

        foreach ($figli as $figlio) {
            if ($figlio->nodeType === XML_TEXT_NODE) {
                continue; // testo semplice: sempre ammesso
            }

            if ($figlio->nodeType !== XML_ELEMENT_NODE) {
                // commenti, CDATA, processing instructions: rimossi
                $nodo->removeChild($figlio);
                continue;
            }

            /** @var \DOMElement $figlio */
            $tag = strtolower($figlio->tagName);

            if (in_array($tag, self::TAG_DA_ELIMINARE_CON_CONTENUTO, true)) {
                $nodo->removeChild($figlio);
                continue;
            }

            if (!in_array($tag, self::TAG_AMMESSI, true)) {
                // Tag non whitelisted ma "innocuo": si conserva il testo,
                // si eliminano tag e attributi. Prima si ripuliscono i
                // figli, poi si sostituisce il nodo con i suoi stessi figli.
                self::pulisciNodo($dom, $figlio);
                while ($figlio->firstChild) {
                    $nodo->insertBefore($figlio->firstChild, $figlio);
                }
                $nodo->removeChild($figlio);
                continue;
            }

            self::pulisciAttributi($figlio, $tag);
            self::pulisciNodo($dom, $figlio);
        }
    }

    private static function pulisciAttributi(\DOMElement $el, string $tag): void
    {
        // Copia statica: la NamedNodeMap viene modificata durante il ciclo.
        $attributi = $el->attributes ? iterator_to_array($el->attributes) : [];

        foreach ($attributi as $attr) {
            $nome   = strtolower($attr->nodeName);
            $valore = $attr->nodeValue;

            // Rimuove sempre gli event handler inline (onclick, onerror, ...)
            if (str_starts_with($nome, 'on')) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }

            switch ($nome) {
                case 'style':
                    $pulito = self::pulisciStyle($valore);
                    if ($pulito === '') {
                        $el->removeAttribute('style');
                    } else {
                        $el->setAttribute('style', $pulito);
                    }
                    break;

                case 'href':
                    if ($tag !== 'a' || !self::hrefSicuro($valore)) {
                        $el->removeAttribute('href');
                    }
                    break;

                case 'colspan':
                case 'rowspan':
                    // Celle unite delle tabelle dell'editor: solo numeri piccoli
                    if (! in_array($tag, ['td', 'th'], true) || ! preg_match('/^[1-9]\d?$/', trim($valore))) {
                        $el->removeAttribute($attr->nodeName);
                    }
                    break;

                default:
                    // Qualunque altro attributo (id, class, data-*, target,
                    // rel, width "libero", ecc.) rimosso: non prodotto da
                    // nessun pulsante della barra dell'editor.
                    $el->removeAttribute($attr->nodeName);
            }
        }
    }

    private static function pulisciStyle(string $style): string
    {
        $dichiarazioni = array_filter(array_map('trim', explode(';', $style)));
        $pulite = [];

        foreach ($dichiarazioni as $dich) {
            if (!str_contains($dich, ':')) {
                continue;
            }
            [$proprieta, $val] = array_map('trim', explode(':', $dich, 2));
            $proprieta = strtolower($proprieta);

            if (!in_array($proprieta, self::STYLE_PROPRIETA_AMMESSE, true)) {
                continue;
            }
            // Blocca url(...)/expression(...) anche su proprieta' ammesse:
            // nessuna delle proprieta' whitelisted le richiede legittimamente.
            if (stripos($val, 'url(') !== false || stripos($val, 'expression(') !== false) {
                continue;
            }

            $pulite[] = $proprieta . ':' . $val;
        }

        return implode(';', $pulite);
    }

    private static function hrefSicuro(string $href): bool
    {
        $href = trim($href);
        if ($href === '') {
            return false;
        }

        // parse_url() valida realmente la struttura dell'URL, non solo il
        // prefisso della stringa: un controllo tipo preg_match('#^https?://#')
        // farebbe passare valori come "https://javascript:alert(1)" (che
        // inizia con "https://" ma non e' un URL valido -- parse_url() lo
        // rifiuta correttamente restituendo false). Riscontrato in test v71:
        // Jodit stesso normalizza un href "javascript:..." grezzo anteponendo
        // "https://" invece di rimuoverlo, quindi il controllo deve validare
        // la URL risultante, non fidarsi del prefisso letterale.
        $parti = parse_url($href);
        if ($parti === false || empty($parti['scheme']) || empty($parti['host'])) {
            return false;
        }

        return in_array(strtolower($parti['scheme']), ['http', 'https'], true);
    }

    /**
     * Riparazione strutturale liste (v71, secondo intervento).
     *
     * HTML non valido tipico di copia-incolla da Word: <p> o <ul>/<ol>
     * annidati come figli DIRETTI di <ul>/<ol>, invece che dentro un
     * <li>. libxml (usato sopra in pulisciNodo) e' molto permissivo e
     * lascia passare questa struttura inalterata; DomPDF ha un motore
     * di parsing HTML diverso e piu' rigido, che puo' interpretarla in
     * modo imprevedibile (bug riscontrato: sparizione del salto pagina
     * e di contenuto successivo nel PDF, verbale di test 01/07/2026).
     *
     * Riparazione: nessun contenuto viene scartato. La lista viene
     * "spezzata" in piu' liste valide (stesso tag ul/ol) attorno agli
     * elementi estranei, che vengono spostati come fratelli della
     * lista invece che restare al suo interno.
     */
    private static function riparaListe(\DOMDocument $dom, \DOMElement $root): void
    {
        $liste = [];
        self::raccogliListe($root, $liste);

        foreach ($liste as $list) {
            self::ripartiListaSeNecessario($dom, $list);
        }
    }

    private static function raccogliListe(\DOMNode $nodo, array &$liste): void
    {
        foreach ($nodo->childNodes as $figlio) {
            if ($figlio->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($figlio->tagName);
                if ($tag === 'ul' || $tag === 'ol') {
                    $liste[] = $figlio;
                }
                self::raccogliListe($figlio, $liste);
            }
        }
    }

    private static function ripartiListaSeNecessario(\DOMDocument $dom, \DOMElement $list): void
    {
        $parent = $list->parentNode;
        if (!$parent) {
            return;
        }

        $tagLista = strtolower($list->tagName);
        $figli = iterator_to_array($list->childNodes);

        // Segmenta i figli diretti: sequenze di <li>/testo formano un
        // segmento "lista" valido; qualunque altro elemento (<p>, <ul>,
        // <div>, ...) forma un segmento "estraneo" da spostare fuori.
        $segmenti = [];
        $buffer = [];

        $chiudiBuffer = function () use (&$buffer, &$segmenti) {
            if (self::contieneLi($buffer)) {
                $segmenti[] = ['tipo' => 'lista', 'nodi' => $buffer];
            }
            // Buffer di solo whitespace tra elementi estranei: scartato,
            // non ha valore semantico proprio.
            $buffer = [];
        };

        foreach ($figli as $figlio) {
            $eLiOTesto = ($figlio->nodeType === XML_TEXT_NODE)
                || ($figlio->nodeType === XML_ELEMENT_NODE && strtolower($figlio->tagName) === 'li');

            if ($eLiOTesto) {
                $buffer[] = $figlio;
            } else {
                $chiudiBuffer();
                $segmenti[] = ['tipo' => 'estraneo', 'nodi' => [$figlio]];
            }
        }
        $chiudiBuffer();

        // Caso normale: un solo segmento, di tipo lista. Nulla da riparare.
        if (count($segmenti) === 1 && $segmenti[0]['tipo'] === 'lista') {
            return;
        }
        // Nessun segmento valido (lista vuota o di solo whitespace): rimossa.
        if (count($segmenti) === 0) {
            $parent->removeChild($list);
            return;
        }

        $ancora = $list->nextSibling;
        $parent->removeChild($list);

        foreach ($segmenti as $segmento) {
            if ($segmento['tipo'] === 'lista') {
                $nuovaLista = $dom->createElement($tagLista);
                foreach ($segmento['nodi'] as $n) {
                    $nuovaLista->appendChild($n);
                }
                $ancora ? $parent->insertBefore($nuovaLista, $ancora) : $parent->appendChild($nuovaLista);
            } else {
                foreach ($segmento['nodi'] as $n) {
                    $ancora ? $parent->insertBefore($n, $ancora) : $parent->appendChild($n);
                }
            }
        }
    }

    private static function contieneLi(array $nodi): bool
    {
        foreach ($nodi as $n) {
            if ($n->nodeType === XML_ELEMENT_NODE && strtolower($n->tagName) === 'li') {
                return true;
            }
        }
        return false;
    }

    /**
     * Vero se l'HTML pulito non ha testo (editor lasciato vuoto: «<p><br></p>»).
     */
    public static function vuoto(?string $html): bool
    {
        return self::testoSemplice($html) === '';
    }

    /** Solo il testo, a capo tra i blocchi: per email, elenchi e controlli. */
    public static function testoSemplice(?string $html): string
    {
        $html = preg_replace('#<(br|/p|/li|/h[1-6]|/tr|/div|/blockquote)\b[^>]*>#i', "$0\n", (string) $html);
        $testo = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $testo = preg_replace("/[ \t\x{00A0}]+/u", ' ', $testo);

        return trim(preg_replace("/\n\s*\n+/", "\n", $testo));
    }
}

