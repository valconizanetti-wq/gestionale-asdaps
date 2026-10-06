{{--
    Ricevuta della domanda di adesione online (RicevutaDomanda), su carta
    intestata della Compagnia (pdf/carta-intestata). Semplice (collaudo A1,
    29/09/2026): chi chiede, i dati, cosa ha dichiarato, cosa succede ora.
    In fondo, in piccolo, i dati della presentazione che valgono come firma
    elettronica semplice (domande-iscrizione.md §1): data e ora, IP,
    indirizzo della ricevuta, apertura di statuto e informativa, impronte.
    Sostituisce il modulo cartaceo CSEN.
--}}
@extends('pdf.carta-intestata')

@php
    $minore = $domanda->tipo_socio === 'minorenne';
    $socio = trim($domanda->nome . ' ' . $domanda->cognome);
    $nato = $domanda->sesso === 'F' ? 'Nata' : 'Nato';
    $ammesso = $domanda->sesso === 'F' ? 'ammessa' : 'ammesso';
    $data = fn ($d) => $d ? \Carbon\Carbon::parse($d)->locale('it')->isoFormat('D MMMM YYYY') : '—';
    $ora = fn ($d) => $d ? \Carbon\Carbon::parse($d)->timezone('Europe/Rome')->format('d/m/Y \a\l\l\e H:i') : '—';
    // Impronte SHA-256 in gruppi di 8: DomPDF non spezza le parole lunghe.
    $impronta = fn ($h) => $h ? trim(chunk_split($h, 8, ' ')) : '—';
    $documento = ($documenti[$domanda->documento_tipo] ?? $domanda->documento_tipo) . ' n. ' . $domanda->documento_numero;
    $emailRicevuta = $domanda->genitore_email ?: $domanda->email;
@endphp

@section('titolo', 'Domanda di adesione n. ' . $domanda->id)

@section('content')
<h1>Domanda di adesione</h1>
<p class="sotto">N. {{ $domanda->id }} · presentata online il {{ $ora($domanda->created_at) }}</p>

<p>
    @if ($minore)
        <strong>{{ $domanda->genitore_nome }}</strong>, genitore, chiede che <strong>{{ $socio }}</strong> sia {{ $ammesso }} come <strong>socio giovane</strong> della Compagnia.
    @else
        <strong>{{ $socio }}</strong> chiede di essere {{ $ammesso }} come <strong>socio ordinario</strong> della Compagnia.
    @endif
</p>

@php
    $righeSocio = [
        ['Nome e cognome', $socio, false],
        ['Codice fiscale', $domanda->codice_fiscale, true],
        [$nato, 'il ' . $data($domanda->data_nascita) . ' a ' . $domanda->luogo_nascita, false],
        ['Residenza', $domanda->indirizzo . ', ' . $domanda->cap . ' ' . $domanda->citta . ' (' . $domanda->provincia . ')', false],
    ];
    if ($minore) {
        $righeSocio[] = ['Email', $domanda->email && $domanda->email !== $domanda->genitore_email ? $domanda->email : 'nessuna: comunicazioni al genitore', false];
    } else {
        $righeSocio[] = ['Email e telefono', $domanda->email . ($domanda->telefono ? ' · ' . $domanda->telefono : ''), false];
        $righeSocio[] = ['Documento', $documento, false];
    }
    $righeGenitore = [
        ['Nome e cognome', $domanda->genitore_nome, false],
        ['Codice fiscale', $domanda->genitore_codice_fiscale, true],
        ['Email e telefono', $domanda->genitore_email . ($domanda->genitore_telefono ? ' · ' . $domanda->genitore_telefono : ''), false],
        ['Documento', $documento, false],
    ];
@endphp

@if ($minore)
    {{-- Minore e genitore affiancati (v0.31.0): con l'assenso del minore la ricevuta deve restare in una pagina --}}
    <table class="colonne">
        <tr>
            <td class="colonna">
                <h2>Il minore</h2>
                @include('pdf._righe-dati', ['righe' => $righeSocio])
            </td>
            <td class="colonna colonna-destra">
                <h2>Il genitore</h2>
                @include('pdf._righe-dati', ['righe' => $righeGenitore])
            </td>
        </tr>
    </table>
@else
    <h2>Dati del richiedente</h2>
    @include('pdf._righe-dati', ['righe' => $righeSocio])
@endif

<h2>{{ $minore ? 'Il genitore dichiara' : 'Il richiedente dichiara' }}, con l'invio del {{ $ora($domanda->created_at) }}</h2>
<ul>
    <li>di aver letto e di accettare lo statuto (versione {{ $domanda->statuto_versione }}) e i regolamenti, compresa la clausola arbitrale dell'art. 30;</li>
    <li>di chiedere il tesseramento al CSEN per l'anno in corso e di aver preso visione della polizza infortuni CSEN base, compresa nella quota;</li>
    @if ($minore)
        <li>di autorizzare il minore a partecipare alle attività sportive della Compagnia;</li>
    @endif
    <li>di aver letto l'informativa sul trattamento dei dati personali (versione {{ $domanda->informativa_versione }});</li>
    <li><strong>{{ $domanda->consenso_foto_video ? 'di acconsentire' : 'di non acconsentire' }}</strong> alla pubblicazione di foto e video.</li>
</ul>

@if ($domanda->assenso_minore_il)
    {{-- Assenso del minore dai 14 anni (art. 16, c. 2, D.Lgs. 36/2021; v0.31.0). Compatto: la ricevuta resta in una pagina. --}}
    <h2>Il minore dichiara ({{ $domanda->etaAllaDomanda() }} anni), con l'invio del {{ $ora($domanda->assenso_minore_il) }}</h2>
    <p class="citazione">«{{ $domanda->testoAssensoMinore() }}»</p>
    <div class="norma">{{ trim(\App\Models\Domanda::NORMA_ASSENSO . ' ' . ($domanda->consenso_foto_video ? \App\Models\Domanda::NORMA_IMMAGINE : '')) }}</div>
@endif

<div class="riquadro">
    <strong>Cosa succede ora.</strong> Il Consiglio Direttivo decide entro il {{ $data($domanda->scadenza_cd) }} (se entro quella data non vota la maggioranza dei consiglieri, la domanda è accolta). L'esito arriva per email, con le istruzioni per la quota. La domanda si può ritirare con il link dell'email fino al termine per versare la quota.
</div>

<h2>Dati della presentazione</h2>
<div class="nota">
    <p>La domanda è stata presentata per via telematica con il modulo della Compagnia (statuto, art. 6): l'invio con le dichiarazioni, questa ricevuta inviata all'indirizzo indicato e il link per ritirarla valgono come sottoscrizione.</p>
    <table>
        <tr><td class="etichetta">Domanda e dichiarazioni</td><td>inviate il {{ $ora($domanda->created_at) }} con il pulsante "Invia la domanda", dall'indirizzo IP {{ $domanda->ip_address }}</td></tr>
        @if ($conConferma)
            <tr><td class="etichetta">Confermata il</td><td>{{ $ora($confermataIl) }}, dal link inviato a {{ $emailRicevuta }}</td></tr>
        @else
            <tr><td class="etichetta">Ricevuta inviata a</td><td>{{ $emailRicevuta }}</td></tr>
        @endif
        @if ($domanda->statuto_aperto_il)
            <tr><td class="etichetta">Statuto aperto il</td><td>{{ $ora($domanda->statuto_aperto_il) }}</td></tr>
        @endif
        @if ($domanda->informativa_aperta_il)
            <tr><td class="etichetta">Informativa aperta il</td><td>{{ $ora($domanda->informativa_aperta_il) }}</td></tr>
        @endif
        <tr><td class="etichetta">Impronta dello statuto</td><td class="mono">{{ $impronta($statutoHash) }}</td></tr>
        <tr><td class="etichetta">Impronta dell'informativa</td><td class="mono">{{ $impronta($domanda->informativa_hash) }}</td></tr>
    </table>
</div>
@endsection
