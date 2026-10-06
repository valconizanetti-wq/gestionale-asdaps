{{-- Libro soci (struttura ripresa dal Gestionale APS, soci/pdf-libro). --}}
@php
    $docTitolo      = 'Libro Soci';
    $docDataLabel   = 'Aggiornato al';
    $docData        = $generatoIl;
    $showPagination = true;
    $bodyPadding    = '36mm 14mm 22mm 14mm';
    $conCessazione  = $soci->contains(fn ($s) => $s->stato === 'cancellato');
@endphp
@extends('pdf.layout')

@section('styles')
<style>
body { font-size: 7.5px; }
table.doc-table { font-size: 7px; }
table.doc-table th.c-num   { width: 5%; }
table.doc-table th.c-nome  { width: {{ $conCessazione ? '23%' : '29%' }}; }
table.doc-table th.c-tipo  { width: 9%; }
table.doc-table th.c-nasc  { width: 10%; }
table.doc-table th.c-cf    { width: 16%; }
table.doc-table th.c-dal   { width: 10%; }
table.doc-table th.c-quota { width: 11%; }
table.doc-table th.c-doc   { width: 10%; }
table.doc-table th.c-cess  { width: 6%; }
table.doc-table td.cf { font-size: 6.5px; }
</style>
@endsection

@section('piede', 'Documento contenente dati personali: non divulgare')

@section('content')
<div class="kpi-bar">
    <div class="kpi-cell"><span class="kpi-num">{{ $soci->count() }}</span><span class="kpi-lbl">Totale soci</span></div>
    <div class="kpi-cell"><span class="kpi-num">{{ $riepilogo['quota_valida'] }}</span><span class="kpi-lbl">Quota valida</span></div>
    <div class="kpi-cell"><span class="kpi-num warn">{{ $riepilogo['da_versare'] }}</span><span class="kpi-lbl">Da sollecitare</span></div>
    <div class="kpi-cell"><span class="kpi-num">{{ $riepilogo['minorenni'] }}</span><span class="kpi-lbl">Minorenni</span></div>
    <div class="kpi-cell ultima"><span class="kpi-num">{{ $riepilogo['documenti_completi'] }}</span><span class="kpi-lbl">Documenti completi</span></div>
</div>

<p class="filtro-info">Elenco: <strong>{{ $etichettaStato }}</strong>@if ($filtriAttivi) — filtrato @endif</p>

<table class="doc-table">
    <thead>
        <tr>
            <th class="c-num">{{ $mostraTessera ? 'Tessera' : 'N.' }}</th>
            <th class="c-nome">Cognome e nome</th>
            <th class="c-tipo">Tipo</th>
            <th class="c-nasc">Data nascita</th>
            <th class="c-cf">Cod. fiscale</th>
            <th class="c-dal">Iscritto dal</th>
            <th class="c-quota">Quota</th>
            <th class="c-doc">Documenti</th>
            @if ($conCessazione)<th class="c-cess">Cessato</th>@endif
        </tr>
    </thead>
    <tbody>
        @forelse ($soci as $i => $s)
            @php
                $quotaValida = $s->quota_scadenza?->gte(today());
                $mancanti = $s->documentiMancanti($certificatoRichiesto);
                $cessato = $s->stato === 'cancellato';
            @endphp
            <tr @class(['pari' => $i % 2 === 1, 'attenuato' => $cessato])>
                <td class="num">{{ $mostraTessera ? ($s->numero_tessera ?? '—') : $i + 1 }}</td>
                <td><strong>{{ $s->cognome }}</strong> {{ $s->nome }}</td>
                <td class="center">{{ $tipi[$s->tipo] ?? $s->tipo }}</td>
                <td class="center">{{ $s->data_nascita?->format('d/m/Y') ?? '—' }}</td>
                <td class="num cf">{{ $s->codice_fiscale ?? '—' }}</td>
                <td class="center">{{ $s->data_prima_iscrizione?->format('d/m/Y') ?? '—' }}</td>
                <td class="center">
                    @if ($quotaValida)
                        <span class="si">✓ {{ $s->quota_scadenza->format('d/m/Y') }}</span>
                    @else
                        <span class="no">da versare</span>
                    @endif
                </td>
                <td class="center">
                    @if ($mancanti === 0)
                        <span class="si">Completi</span>
                    @else
                        <span class="no">{{ $mancanti }} {{ $mancanti === 1 ? 'mancante' : 'mancanti' }}</span>
                    @endif
                </td>
                @if ($conCessazione)
                    <td class="center">{{ $cessato ? $s->data_cancellazione?->format('d/m/Y') : '' }}</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $conCessazione ? 9 : 8 }}" class="center">Nessun socio corrisponde ai filtri.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
