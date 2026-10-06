{{--
====================================================================
LAYOUT COMUNE DEI PDF — Gestionale ASD (vedi ui.md, "Modelli PDF")
====================================================================
Ripreso dal layout PDF del Gestionale APS (pdf.layout-contabilita):
stessa struttura, stesse variabili, identità della Compagnia.

Variabili (tutte facoltative tranne una fra $docTitolo e $docCodice):
  $docTitolo      titolo del documento, a destra nell'intestazione
  $docCodice      codice documento (es. RIC-2028-004), se non c'è titolo
  $docSottotitolo riga sotto il titolo
  $docData        Carbon o stringa (default: oggi)
  $docDataLabel   etichetta della data (default: "{luogo},")
  $docLuogo       default "Castel Viscardo"
  $docOggetto     riga in corsivo sotto la data
  $orientamento   'portrait' (default) o 'landscape'
  $bodyPadding    margini del contenuto (spazio per intestazione e piede)
  $showPagination true per il numero di pagina

Intestazione e piè di pagina: se esistono public/img/pdf-header.jpg e
public/img/pdf-footer.jpg (grafiche preparate, come nel Gestionale APS)
si usano quelle; altrimenti si compongono con logo e nome.

Numero di pagina: contatore CSS di DomPDF, senza abilitare l'esecuzione
di PHP nei PDF (isPhpEnabled resta spento, per sicurezza). Limite: mostra
"Pagina N", non il totale.
====================================================================
--}}
@php
    $immagine = function (string $percorso, string $mime) {
        return file_exists($percorso) ? "data:{$mime};base64," . base64_encode(file_get_contents($percorso)) : null;
    };
    $headerSrc = $immagine(public_path('img/pdf-header.jpg'), 'image/jpeg');
    $footerSrc = $immagine(public_path('img/pdf-footer.jpg'), 'image/jpeg');
    $logoSrc   = $immagine(public_path('images/logo-arcieri.png'), 'image/png');

    $luogo = $docLuogo ?? 'Castel Viscardo';
    $dataLabel = $docDataLabel ?? ($luogo . ',');
    $data = isset($docData)
        ? ($docData instanceof \Carbon\Carbon ? $docData : \Carbon\Carbon::parse($docData))
        : \Carbon\Carbon::now();
    $dataFormattata = $data->locale('it')->isoFormat('D MMMM YYYY');
    $orientamento = $orientamento ?? 'portrait';
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>{{ $docTitolo ?? $docCodice ?? 'Documento' }}</title>
<style>
@page { margin: 0; size: A4 {{ $orientamento }}; }

body {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 10pt;
    color: #1c2640;
    margin: 0;
    padding: {{ $bodyPadding ?? '38mm 14mm 24mm 14mm' }};
    line-height: 1.5;
}

/* ── Intestazione ── */
.pdf-header { position: fixed; top: 0; left: 0; right: 0; padding: 9mm 14mm 0; }
.pdf-header-inner { display: table; width: 100%; border-collapse: collapse; }
.pdf-header-logo { display: table-cell; vertical-align: middle; }
.pdf-header-logo img.grafica { width: 99.2mm; display: block; }
.pdf-brand { display: table; border-collapse: collapse; }
.pdf-brand-logo { display: table-cell; vertical-align: middle; width: 20mm; }
/* Solo la larghezza: l'altezza segue le proporzioni del logo (512 × 420). */
.pdf-brand-logo img { width: 18mm; display: block; }
.pdf-brand-testo { display: table-cell; vertical-align: middle; padding-left: 2mm; }
.pdf-brand-nome { font-size: 12pt; font-weight: bold; color: #081939; display: block; line-height: 1.2; }
.pdf-brand-nome span { color: #004aad; }
.pdf-brand-sub { font-size: 7.5pt; color: #5f5a52; display: block; letter-spacing: .04em; }
.pdf-header-info { display: table-cell; vertical-align: bottom; text-align: right; }
.pdf-header-titolo, .pdf-header-codice { font-size: 13pt; font-weight: bold; color: #004aad; display: block; }
.pdf-header-sottotitolo { font-size: 8.5pt; font-weight: bold; color: #081939; display: block; margin-top: 1px; }
.pdf-header-data { font-size: 8pt; color: #5f5a52; display: block; margin-top: 3px; }
.pdf-header-oggetto { font-size: 7.5pt; color: #7a746b; display: block; margin-top: 2px; font-style: italic; }
.pdf-header-line { border-top: 1.2pt solid #004aad; margin-top: 3mm; }

/* ── Piè di pagina ── */
.pdf-footer { position: fixed; bottom: 6mm; left: 0; right: 0; padding: 0 14mm; }
.pdf-footer img { width: 210mm; display: block; margin-left: -14mm; }
.pdf-footer-inner { display: table; width: 100%; border-top: 0.5pt solid #c9c5bd; padding-top: 2mm; }
.pdf-footer-testo { display: table-cell; font-size: 7pt; color: #5f5a52; }
.pdf-footer-pagina { display: table-cell; font-size: 7pt; color: #5f5a52; text-align: right; }
.pdf-footer-pagina:after { content: "Pagina " counter(page); }

/* ── Corpo (classi comuni, come nel Gestionale APS) ── */
h1.doc-title { font-size: 15pt; font-weight: bold; color: #081939; text-align: center; margin: 4mm 0 1mm; }
h2.doc-subtitle { font-size: 9pt; font-weight: normal; color: #004aad; text-transform: uppercase;
    letter-spacing: .1em; text-align: center; margin: 0 0 5mm; }
h3.doc-section { font-size: 8pt; font-weight: bold; color: #081939; text-transform: uppercase;
    letter-spacing: .08em; border-left: 2.5pt solid #004aad; padding-left: 5px; margin: 4mm 0 2mm; }
p.doc-body { margin: 0 0 3mm; text-align: justify; }
.doc-box { background: #f7f6f2; border: 1px solid #dcd9d2; border-left: 3px solid #004aad;
    padding: 4px 9px; margin: 2mm 0; font-size: 8pt; }
.doc-firme { display: table; width: 100%; margin-top: 10mm; }
.doc-firma-col { display: table-cell; width: 50%; padding: 0 6px; vertical-align: top; }
.doc-firma-label { font-size: 7px; color: #5f5a52; text-transform: uppercase; letter-spacing: .1em; }
.doc-firma-box { border-top: 1px solid #444; padding-top: 3px; margin-top: 12mm; font-size: 9px; }

/* Riepilogo numerico in testa ai documenti-elenco */
.kpi-bar { display: table; width: 100%; border-collapse: collapse; margin-bottom: 6px; border: 1px solid #dcd9d2; }
.kpi-cell { display: table-cell; text-align: center; padding: 4px 8px; border-right: 1px solid #dcd9d2; vertical-align: middle; }
.kpi-cell.ultima { border-right: none; }
.kpi-num { display: block; font-size: 11pt; font-weight: bold; color: #004aad; line-height: 1.2; }
.kpi-num.warn { color: #8a5a0d; }
.kpi-lbl { display: block; font-size: 6.5pt; color: #5f5a52; text-transform: uppercase; letter-spacing: .3px; margin-top: 1px; }
.filtro-info { font-size: 6.5pt; color: #5f5a52; margin: 0 0 4px; font-style: italic; }

/* Tabella dati: intestazione colorata, righe alternate */
table.doc-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
table.doc-table th { background: #004aad; color: #fff; padding: 3px 4px; text-align: left; font-weight: bold;
    border: 1px solid #003a88; }
table.doc-table td { padding: 2.5px 4px; border: 1px solid #dcd9d2; vertical-align: middle; line-height: 1.35; }
table.doc-table tr { page-break-inside: avoid; }
table.doc-table tr.pari td { background: #f7f6f2; }
table.doc-table td.num { font-family: 'DejaVu Sans Mono', monospace; text-align: center; }
table.doc-table td.center { text-align: center; }
table.doc-table .si { color: #1f6b41; font-weight: bold; }
table.doc-table .no { color: #9e2328; }
table.doc-table .attenuato { color: #7a746b; }
</style>
@yield('styles')
</head>
<body>

{{-- Piè di pagina (dichiarato prima del contenuto, come richiede DomPDF) --}}
<div class="pdf-footer">
    @if ($footerSrc)
        <img src="{{ $footerSrc }}" alt="">
    @else
        <div class="pdf-footer-inner">
            <div class="pdf-footer-testo">
                {{ \App\Services\ProfiloEnte::firma($dataDocumento ?? null) }}
                @hasSection('piede') · @yield('piede') @endif
            </div>
            @if (! empty($showPagination))<div class="pdf-footer-pagina"></div>@endif
        </div>
    @endif
</div>

{{-- Intestazione --}}
<div class="pdf-header">
    <div class="pdf-header-inner">
        <div class="pdf-header-logo">
            @if ($headerSrc)
                <img class="grafica" src="{{ $headerSrc }}" alt="{{ \App\Services\ProfiloEnte::denominazione($dataDocumento ?? null) }}">
            @else
                <div class="pdf-brand">
                    @if ($logoSrc)<div class="pdf-brand-logo"><img src="{{ $logoSrc }}" alt=""></div>@endif
                    <div class="pdf-brand-testo">
                        <span class="pdf-brand-nome">Arcieri <span>Castel Viscardo</span></span>
                        <span class="pdf-brand-sub">{{ \App\Services\ProfiloEnte::denominazione($dataDocumento ?? null) }}</span>
                    </div>
                </div>
            @endif
        </div>
        <div class="pdf-header-info">
            @isset($docTitolo)
                <span class="pdf-header-titolo">{{ $docTitolo }}</span>
            @else
                <span class="pdf-header-codice">{{ $docCodice ?? '' }}</span>
            @endisset
            @isset($docSottotitolo)<span class="pdf-header-sottotitolo">{{ $docSottotitolo }}</span>@endisset
            <span class="pdf-header-data">{{ $dataLabel }} {{ $dataFormattata }}</span>
            @isset($docOggetto)<span class="pdf-header-oggetto">{{ $docOggetto }}</span>@endisset
        </div>
    </div>
    <div class="pdf-header-line"></div>
</div>

@yield('content')

</body>
</html>
