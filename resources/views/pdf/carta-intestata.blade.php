{{--
    Carta intestata della Compagnia per i documenti inviati ai soci e ai
    richiedenti (prima: la ricevuta della domanda, collaudo A1 del
    29/09/2026). Semplice e stampabile: logo e nome a sinistra, recapiti
    a destra, filetto verde e oro; nel piede sede, codice fiscale, email e
    pagina. Colori del sito (ui.md §3, "Tema del sito").
    Indirizzo: sempre quello pubblico di config/sito.php (Paolo,
    29/09/2026: carta intestata = uso pubblico; la forma ufficiale serve
    per atti e moduli fiscali). Email e sito sono quelli definitivi di
    config/sito.php (info@arciericastello.it, arciericastello.it), non la
    scheda Associazione né SITO_URL, che durante il collaudo puntano ancora
    alla casella e al sito temporanei (collaudo, 29/09/2026). Ragione
    sociale da config/sito.php; codice fiscale dalla scheda Associazione,
    se vuota da config/sito.php.

    @extends('pdf.carta-intestata') con le sezioni 'titolo' (tag <title>)
    e 'content'. Variabili facoltative: $associazione, $dataDocumento (data
    del documento: sceglie il periodo dell'ente, profilo-ente.md).
--}}
@php
    $a = $associazione ?? \App\Models\Associazione::istanza();
    $piede = config('sito.piede');
    // Denominazione e forma del periodo dell'ente alla data del documento
    // (profilo-ente.md, v0.32.0): una ricevuta del 2027 dice "B.A.S." anche
    // se rigenerata dopo il passaggio. $dataDocumento è facoltativa (oggi).
    $periodo = \App\Services\ProfiloEnte::al($dataDocumento ?? null);
    $ragioneSociale = $periodo->denominazione;
    $forma = $periodo->descrizioneForma();
    $indirizzo = config('sito.indirizzo.pubblico');
    $cf = $a?->codice_fiscale ?: $piede['codice_fiscale'];
    $email = config('sito.email_definitiva');
    $sito = config('sito.dominio_definitivo');
    $logo = file_exists(public_path('images/logo-arcieri.png'))
        ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/logo-arcieri.png')))
        : null;
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>@yield('titolo', 'Documento')</title>
<style>
@page { margin: 0; size: A4 portrait; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #15181A; margin: 0; padding: 35mm 18mm 18mm; line-height: 1.4; }

.ci-testa { position: fixed; top: 0; left: 0; right: 0; padding: 11mm 18mm 0; }
.ci-riga { display: table; width: 100%; border-collapse: collapse; }
.ci-logo { display: table-cell; width: 17mm; vertical-align: middle; }
.ci-logo img { width: 16mm; display: block; }
.ci-nome { display: table-cell; vertical-align: middle; padding-left: 3mm; }
.ci-forma { display: block; font-size: 7.5pt; color: #4f5451; }
.ci-nome strong { display: block; font-size: 12pt; line-height: 1.15; color: #0f4124; white-space: nowrap; }
.ci-recapiti { display: table-cell; width: 52mm; vertical-align: middle; text-align: right; font-size: 7pt; color: #4f5451; line-height: 1.45; white-space: nowrap; }
.ci-filetto { margin-top: 4mm; border-top: 1.6pt solid #0f4124; }
.ci-filetto-oro { border-top: 0.8pt solid #F2B84A; margin-top: 0.6mm; }

.ci-piede { position: fixed; bottom: 8mm; left: 18mm; right: 18mm; border-top: 0.5pt solid #cfd3d8; padding-top: 2mm;
    font-size: 6.8pt; color: #4f5451; }
.ci-piede-riga { display: table; width: 100%; }
.ci-piede-testo { display: table-cell; }
.ci-piede-pagina { display: table-cell; text-align: right; white-space: nowrap; }
.ci-piede-pagina:after { content: "Pagina " counter(page); }

/* Corpo */
h1 { font-size: 15pt; color: #0f4124; margin: 0 0 0.5mm; }
.sotto { font-size: 8.5pt; color: #4f5451; margin: 0 0 4mm; }
h2 { font-size: 8pt; text-transform: uppercase; letter-spacing: .06em; color: #0f4124; margin: 4mm 0 1.2mm;
    padding-bottom: 1mm; border-bottom: 0.5pt solid #cfd3d8; }
p { margin: 0 0 2.5mm; }
table.dati { width: 100%; border-collapse: collapse; }
table.dati td { padding: 0.5mm 0; vertical-align: top; }
table.dati td.etichetta { width: 38mm; color: #4f5451; font-size: 8.5pt; }
.mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5pt; }
ul { margin: 0 0 2.5mm; padding-left: 5mm; }
li { margin-bottom: 0.5mm; }
.riquadro { background: #F8F7F2; border: 0.5pt solid #E5E7EB; border-left: 2pt solid #0f4124; padding: 2.5mm 3.5mm; margin: 2mm 0 3mm; }
.nota { font-size: 7pt; color: #4f5451; line-height: 1.35; }
/* Riferimento normativo in piccolo sotto una dichiarazione (v0.31.0) */
.norma { font-size: 6.5pt; color: #5f5a52; line-height: 1.3; margin-top: 1mm; }
.citazione { margin: 0; font-style: italic; }
/* Due colonne affiancate (minore e genitore nella ricevuta, v0.31.0) */
table.colonne { width: 100%; border-collapse: collapse; }
td.colonna { width: 50%; vertical-align: top; padding: 0; }
td.colonna-destra { padding-left: 5mm; }
td.colonna table.dati td.etichetta { width: 26mm; padding-right: 2mm; white-space: nowrap; }
.nota table { width: 100%; border-collapse: collapse; }
.nota td { padding: 0.3mm 0; vertical-align: top; }
.nota td.etichetta { width: 42mm; }
</style>
</head>
<body>

<div class="ci-testa">
    <div class="ci-riga">
        @if ($logo)
            <div class="ci-logo"><img src="{{ $logo }}" alt=""></div>
        @endif
        <div class="ci-nome">
            @if ($forma)
                <span class="ci-forma">{{ $forma }}</span>
            @endif
            <strong>{{ $ragioneSociale }}</strong>
        </div>
        <div class="ci-recapiti">
            {!! implode('<br>', array_map('e', $indirizzo)) !!}
        </div>
    </div>
    <div class="ci-filetto"></div>
    <div class="ci-filetto-oro"></div>
</div>

<div class="ci-piede">
    <div class="ci-piede-riga">
        <div class="ci-piede-testo">{{ $ragioneSociale }} · C.F. {{ $cf }} · {{ $email }} · {{ $sito }}</div>
        <div class="ci-piede-pagina"></div>
    </div>
</div>

@yield('content')

</body>
</html>
