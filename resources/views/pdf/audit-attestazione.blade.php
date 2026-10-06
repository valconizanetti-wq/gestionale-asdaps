{{-- Bozza di attestazione tecnica da firmare fuori dal gestionale (audit-log.md §9). Non si salva. --}}
@php
    $docTitolo     = 'Attestazione tecnica';
    $docSottotitolo = 'Bozza da firmare';
    $docData       = $generatoIl;
    $bodyPadding   = '38mm 18mm 24mm 18mm';
    $n = fn ($v) => number_format($v, 0, ',', '.');
@endphp
@extends('pdf.layout')

@section('styles')
<style>
table.cert { width: 100%; border-collapse: collapse; margin: 3mm 0; font-size: 9pt; }
table.cert td { padding: 1.8mm 3mm; border: 1px solid #c9c5bd; }
table.cert td.v { text-align: right; width: 30%; }
.centrato { text-align: center; }
.nota-piccola { font-size: 7.5pt; color: #5f5a52; }
.nota-piccola.spazio { margin-top: 2mm; }
.nota { font-size: 8.5pt; color: #444; }
.firma-nome { font-size: 9pt; }
.hash { font-family: 'DejaVu Sans Mono', monospace; font-size: 8pt; word-wrap: break-word; }
</style>
@endsection

@section('piede', 'Bozza generata il ' . $generatoIl->format('d/m/Y H:i') . ' da ' . $operatore)

@section('content')
<h1 class="doc-title">ATTESTAZIONE TECNICA</h1>
<h2 class="doc-subtitle centrato">sullo stato del registro delle operazioni del gestionale</h2>

<p class="doc-body">Il sottoscritto, responsabile tecnico del gestionale dell'Associazione, attesta che alla data del {{ $generatoIl->format('d/m/Y') }} la verifica della catena di impronte SHA-256 del registro delle operazioni ha dato il seguente risultato.</p>

<table class="cert">
    <tr><td>Righe controllate (dalla prima riga della catena)</td><td class="v">{{ $n($esito['records_totali']) }}</td></tr>
    <tr><td>Righe precedenti all'avvio della catena</td><td class="v">{{ $n($esito['record_pre_catena']) }}</td></tr>
    <tr><td>Righe corrette</td><td class="v">{{ $n($esito['corretti']) }}</td></tr>
    <tr><td>Righe senza impronta</td><td class="v">{{ $n($esito['gap_count']) }}</td></tr>
    <tr><td>Anomalie</td><td class="v">{{ $n(count($esito['anomalie'])) }}</td></tr>
    <tr><td>Eccezioni note documentate</td><td class="v">{{ $n(count($eccezioni)) }}</td></tr>
</table>

@if ($eccezioni)
    <h3 class="doc-section">Eccezioni note</h3>
    @foreach ($eccezioni as $id => $motivo)
        <p class="doc-body"><strong>Riga n. {{ $id }}</strong>: {{ $motivo }}</p>
    @endforeach
@endif

@if ($esito['anomalie'])
    <h3 class="doc-section">Anomalie da spiegare</h3>
    @foreach (array_slice($esito['anomalie'], 0, 50) as $a)
        <p class="doc-body">Riga n. {{ $a['id'] }} del {{ \Carbon\Carbon::parse($a['created_at'])->format('d/m/Y H:i') }} ({{ $a['evento'] }}): [SPIEGAZIONE].</p>
    @endforeach
@endif

<div class="doc-box">
    <div class="nota-piccola">Impronta dell'ultima riga del registro</div>
    <div class="hash">{{ $esito['hash_finale'] ?? '—' }}</div>
</div>

<p class="doc-body">[Note del responsabile tecnico, da completare prima della firma.]</p>

<div class="doc-firme">
    <div class="doc-firma-col"><div class="doc-firma-label">Luogo e data</div><div class="doc-firma-box">&nbsp;</div></div>
    <div class="doc-firma-col"><div class="doc-firma-label">Firma (digitale o CIE)</div><div class="doc-firma-box">&nbsp;</div></div>
</div>
@endsection
