{{-- Certificato di integrità dell'Audit Log (tavola "Audit Log 4"; audit-log.md §9). --}}
@php
    $docTitolo     = 'Certificato di integrità';
    $docSottotitolo = 'Audit Log · anno ' . $anno;
    $docData       = $generatoIl;
    $dataDocumento = $generatoIl;
    $bodyPadding   = '38mm 18mm 24mm 18mm';
    $n = fn ($v) => number_format($v, 0, ',', '.');
    // "Nome Cognome (Ruolo)" → nome sopra, ruolo sotto
    preg_match('/^(.*?)\s*\(([^()]*)\)$/', $generatoDa, $chi);
@endphp
@extends('pdf.layout')

@section('styles')
<style>
table.cert { width: 100%; border-collapse: collapse; margin: 3mm 0; font-size: 9.5pt; }
table.cert td { padding: 2mm 3mm; border: 1px solid #c9c5bd; }
table.cert td.v { text-align: right; width: 35%; }
.centrato { text-align: center; }
.nota-piccola { font-size: 7.5pt; color: #5f5a52; }
.nota-piccola.spazio { margin-top: 2mm; }
.nota { font-size: 8.5pt; color: #444; }
.firma-nome { font-size: 9pt; }
.codice { font-family: 'DejaVu Sans Mono', monospace; font-size: 7.5pt; white-space: nowrap; }
.hash { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5pt; word-wrap: break-word; }
</style>
@endsection

@section('piede', 'Codice del documento ' . $uuid)

@section('content')
<h1 class="doc-title">CERTIFICATO DI INTEGRITÀ</h1>
<h2 class="doc-subtitle centrato">del registro delle operazioni del gestionale · anno {{ $anno }}</h2>

<p class="doc-body">Si attesta che il registro delle operazioni (audit log) del gestionale dell'Associazione, per l'anno {{ $anno }}, è stato verificato il <strong>{{ $generatoIl->format('d/m/Y') }} alle {{ $generatoIl->format('H:i') }}</strong> ricalcolando l'intera catena di impronte SHA-256 dalla prima riga del registro, e che <strong>non risultano alterazioni</strong>: ogni riga corrisponde alla propria impronta ed è collegata alla precedente.</p>

<table class="cert">
    <tr><td>Righe dell'anno {{ $anno }}</td><td class="v">{{ $n($r['totale']) }}</td></tr>
    <tr><td>di cui a rischio alto</td><td class="v">{{ $n($r['HIGH']) }}</td></tr>
    <tr><td>di cui a rischio medio</td><td class="v">{{ $n($r['MEDIUM']) }}</td></tr>
    <tr><td>di cui a rischio basso</td><td class="v">{{ $n($r['LOW']) }}</td></tr>
    <tr><td>Prima e ultima riga</td><td class="v">n. {{ $r['prima_riga'] }} – n. {{ $r['ultima_riga'] }}</td></tr>
    <tr><td>Anomalie rilevate</td><td class="v">nessuna</td></tr>
</table>

<div class="doc-box">
    <div class="nota-piccola">Impronta (SHA-256) dell'ultima riga dell'anno, n. {{ $r['ultima_riga'] }}</div>
    <div class="hash">{{ $r['hash_ultima'] }}</div>
</div>

<p class="doc-body nota">Come si controlla: chi ha accesso alla sezione Audit Log può ripetere la verifica; se il registro non è stato toccato, l'impronta della riga n. {{ $r['ultima_riga'] }} resta quella indicata qui sopra. I valori «prima» e «dopo» delle modifiche, che contengono dati personali, sono fuori dalla catena, per poterli oscurare senza alterarla.</p>

<div class="doc-firme">
    <div class="doc-firma-col">
        <div class="doc-firma-label">Generato da</div>
        <div class="firma-nome">{{ $chi[1] ?? $generatoDa }}</div>
        @if (! empty($chi[2]))
            <div class="nota-piccola">{{ $chi[2] }}</div>
        @endif
        <div class="nota-piccola spazio">Codice del documento:</div>
        <div class="codice">{{ $uuid }}</div>
    </div>
    <div class="doc-firma-col">
        <div class="doc-firma-label">Firma</div>
        <div class="doc-firma-box">&nbsp;</div>
    </div>
</div>
@endsection
