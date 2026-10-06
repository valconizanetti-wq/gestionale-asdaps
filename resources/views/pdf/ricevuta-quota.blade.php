{{--
    Ricevuta della quota associativa (tavole RQ-4 e RQ-6; rinnovi-quota.md §3).
    Carta intestata del periodo dell'ente alla data del pagamento. Contenuto
    richiesto per la detrazione sportiva dei ragazzi (D.M. 28/03/2007) sempre
    presente per i soci giovani: sede legale e C.F. dell'ente, causale,
    attività sportiva, dati del ragazzo, chi ha pagato con il C.F. Il riquadro
    della detrazione solo se RinnoviQuota::detraibile(). Annullata: timbro.
    Variabili: $r (Rinnovo con socio), $periodo, $detraibile, $tesoriere, $cf.
--}}
@extends('pdf.carta-intestata')

@use('App\Support\ImportoInLettere')
@use('App\Support\Importo')

@php
    $s = $r->socio;
    $giovane = $r->categoria === 'giovane';
    $nato = $s->sesso === 'F' ? 'Nata il' : 'Nato il';
    $versatoDa = $r->versato_da_nome
        ? $r->versato_da_nome . ($giovane ? ', genitore' : '') . ($r->versato_da_cf ? ' · C.F. ' . $r->versato_da_cf : '')
        : trim($s->nome . ' ' . $s->cognome);
    $modalita = match ($r->metodo_pagamento) {
        'contante' => 'Contanti',
        'bonifico' => 'Bonifico bancario',
        'carta' => 'Carta o POS',
        default => 'Altro',
    } . ' del ' . $r->data_pagamento->format('d/m/Y');
@endphp

@section('titolo', 'Ricevuta n. ' . $r->numero_ricevuta)

@section('stili')
.numero-ricevuta { text-align: right; }
.numero-ricevuta strong { font-size: 18pt; color: #0f4124; }
.etichetta-num { font-size: 7pt; letter-spacing: .14em; text-transform: uppercase; color: #4f5451; }
table.righe td { padding: 1.6mm 0; border-bottom: 0.5pt solid #e3e1db; }
table.righe td.etichetta { width: 40mm; }
.piede-ricevuta { margin-top: 18mm; }
.piede-ricevuta td.nota { width: 60%; padding-right: 8mm; }
.firma { text-align: center; font-size: 8.5pt; color: #3b4459; vertical-align: bottom; }
.linea-firma { border-bottom: 0.5pt solid #9aa0a8; height: 14mm; margin-bottom: 1.5mm; }
.annullata { border: 1.5pt solid #9e2328; color: #9e2328; font-weight: bold; font-size: 14pt; text-align: center; padding: 2mm; margin-bottom: 4mm; letter-spacing: .1em; }
@endsection

@section('content')
@if ($r->isAnnullato())
    <div class="annullata">ANNULLATA il {{ $r->annullata_il->format('d/m/Y') }}</div>
@endif
<table class="colonne"><tr>
    <td class="colonna"><h1>Quota associativa — anno sociale {{ $r->anno }}</h1>
        <p class="sotto">{{ $periodo->denominazione }} · sede legale {{ $periodo->sede_legale }} · C.F. {{ $cf }}{{ $periodo->iscrittoRasd() ? ' · RASD n. ' . $periodo->rasd_numero : '' }}</p></td>
    <td class="colonna numero-ricevuta"><span class="etichetta-num">Ricevuta</span><br><strong>n. {{ $r->numero_ricevuta }}</strong><br>
        <span class="sotto">Castel Viscardo, {{ $r->data_pagamento->format('d/m/Y') }}</span></td>
</tr></table>

<table class="dati righe">
    <tr><td class="etichetta">Socio</td><td>{{ $s->cognome }} {{ $s->nome }} · C.F. <span class="mono">{{ $s->codice_fiscale }}</span></td></tr>
    @if ($giovane && $s->data_nascita)
        <tr><td class="etichetta">{{ $nato }}</td><td>{{ $s->data_nascita->format('d/m/Y') }}{{ $s->luogo_nascita ? ', ' . $s->luogo_nascita : '' }}</td></tr>
    @endif
    <tr><td class="etichetta">Categoria</td><td>{{ $giovane ? 'Giovane (minorenne)' : 'Ordinario' }}</td></tr>
    <tr><td class="etichetta">Attività sportiva</td><td>Tiro con l'arco (dilettantistico)</td></tr>
    <tr><td class="etichetta">Versata da</td><td>{{ $versatoDa }}</td></tr>
    <tr><td class="etichetta">Importo</td><td><strong>€ {{ Importo::it($r->importo) }}</strong> ({{ ImportoInLettere::euro($r->importo) }})</td></tr>
    <tr><td class="etichetta">Modalità</td><td>{{ $modalita }}</td></tr>
    <tr><td class="etichetta">Validità</td><td>Dal versamento al 31/12/{{ $r->anno }}</td></tr>
</table>

@if ($detraibile)
    <div class="riquadro"><strong>Spesa per l'attività sportiva di ragazzi tra 5 e 18 anni</strong>
        (art. 15, c. 1, lett. i-quinquies, TUIR; D.M. 28/03/2007). Pagamento tracciabile, sostenuto da
        {{ $r->versato_da_nome ?: $s->nome . ' ' . $s->cognome }}{{ $r->versato_da_cf ? ' (C.F. ' . $r->versato_da_cf . ')' : '' }}
        per {{ $s->nome }} {{ $s->cognome }}.</div>
@endif

<p>La Compagnia dichiara di aver ricevuto la somma indicata a titolo di <strong>quota associativa per l'anno sociale {{ $r->anno }}</strong>,
    che dà diritto alla pratica del tiro con l'arco secondo lo statuto (artt. 5 e 8). La quota è personale, non trasmissibile e non rimborsabile.</p>

@if ($r->isAnnullato())
    <p class="nota"><strong>Ricevuta annullata</strong> il {{ $r->annullata_il->format('d/m/Y') }}: {{ $r->annullamento_motivo }}</p>
@endif

<table class="colonne piede-ricevuta"><tr>
    <td class="colonna nota">Somma non soggetta a IVA (art. 4 D.P.R. 633/1972) e non imponibile ai fini delle imposte sui redditi
        (art. 148, c. 1, TUIR). Documento non fiscale.
        @if ((float) $r->importo <= 77.47)
            Importo non superiore a 77,47 €: nessuna imposta di bollo.
        @endif
        <br><br>Codice del documento: RQ-{{ $r->anno_ricevuta }}-{{ str_pad((string) $r->progressivo, 4, '0', STR_PAD_LEFT) }}
        · emessa dal Gestionale il {{ $r->created_at?->timezone('Europe/Rome')->format('d/m/Y \a\l\l\e H:i') }}</td>
    <td class="colonna firma"><div class="linea-firma"></div>Il Tesoriere
        @if ($tesoriere)
            <br><strong>{{ $tesoriere }}</strong>
        @endif
    </td>
</tr></table>

@endsection
