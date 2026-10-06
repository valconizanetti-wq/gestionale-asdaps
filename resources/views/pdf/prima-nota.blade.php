{{-- Registro della Prima Nota (A4 orizzontale), con gli stessi filtri dell'elenco (prima-nota.md §3). --}}
@php
    $docTitolo      = 'Prima Nota ' . $anno;
    $docSottotitolo = $riga?->isChiuso() ? 'Anno chiuso il ' . $riga->chiuso_il?->format('d/m/Y') : 'Anno aperto';
    $docDataLabel   = 'Aggiornato al';
    $docData        = $generatoIl;
    $orientamento   = 'landscape';
    $showPagination = true;
    $bodyPadding    = '34mm 12mm 20mm 12mm';
    $it = fn ($v) => \App\Support\Importo::it($v);
    $entrate = $movimenti->filter(fn ($m) => $m->isEntrata() && ! $m->isGiroconto() && ! $m->isAnticipoAperto())->sum('importo');
    $uscite = $movimenti->filter(fn ($m) => ! $m->isEntrata() && ! $m->isGiroconto() && ! $m->isAnticipoAperto())->sum('importo');
@endphp
@extends('pdf.layout')

@section('styles')
<style>
body { font-size: 7.5px; }
table.doc-table { font-size: 7px; }
table.doc-table th.c-num  { width: 5%; }
table.doc-table th.c-data { width: 7%; }
table.doc-table th.c-desc { width: 31%; }
table.doc-table th.c-cat  { width: 17%; }
table.doc-table th.c-cont { width: 9%; }
table.doc-table th.c-doc  { width: 13%; }
table.doc-table th.c-imp  { width: 9%; }
table.doc-table td.imp { text-align: right; white-space: nowrap; }
table.doc-table td.entrata { color: #1f6b41; font-weight: bold; }
table.doc-table td.uscita { color: #9e2328; font-weight: bold; }
table.doc-table tr.totali td { font-weight: bold; background: #ecebe6; }
.sotto { color: #5f5a52; display: block; font-size: 6.5px; }
</style>
@endsection

@section('content')
<div class="kpi-bar">
    <div class="kpi-cell"><span class="kpi-num">{{ $it($apertura['cassa']) }}</span><span class="kpi-lbl">Apertura cassa</span></div>
    <div class="kpi-cell"><span class="kpi-num">{{ $it($apertura['banca']) }}</span><span class="kpi-lbl">Apertura banca</span></div>
    <div class="kpi-cell"><span class="kpi-num">{{ $it($finali['cassa']) }}</span><span class="kpi-lbl">Saldo cassa</span></div>
    <div class="kpi-cell"><span class="kpi-num">{{ $it($finali['banca']) }}</span><span class="kpi-lbl">Saldo banca</span></div>
    <div class="kpi-cell ultima"><span class="kpi-num">{{ $movimenti->count() }}</span><span class="kpi-lbl">Righe</span></div>
</div>

<p class="filtro-info">
    Importi in euro, per cassa.@if ($apertura['da_impostare']) Saldi di apertura non ancora impostati.@endif
    @if ($filtrato) Elenco filtrato: i saldi sono quelli dell'intero anno.@endif
    Giroconti e anticipi non ancora rimborsati non entrano nei totali.
</p>

<table class="doc-table">
    <thead>
        <tr>
            <th class="c-num">N.</th>
            <th class="c-data">Data</th>
            <th class="c-desc">Descrizione</th>
            <th class="c-cat">Categoria</th>
            <th class="c-cont">Conto</th>
            <th class="c-doc">Documento</th>
            <th class="c-imp">Entrata</th>
            <th class="c-imp">Uscita</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($movimenti as $i => $m)
            <tr @class(['pari' => $i % 2 === 1])>
                <td class="num">{{ $m->id }}</td>
                <td class="center">{{ $m->data->format('d/m/Y') }}</td>
                <td>
                    {{ $m->descrizione }}
                    @php $sotto = array_filter([$m->soggetto, $m->tag?->nome ? 'tag ' . $m->tag->nome : null, $m->isAnticipoAperto() ? 'anticipo da rimborsare' : null]); @endphp
                    @if ($sotto)<span class="sotto">{{ implode(' · ', $sotto) }}</span>@endif
                </td>
                <td>{{ $m->isGiroconto() ? 'Giroconto' : $m->categoria?->nome }}</td>
                <td>{{ $m->contoLabel() }}{{ $m->isGiroconto() && $m->giroconto ? ($m->isEntrata() ? ' ← ' : ' → ') . $m->giroconto->contoLabel() : '' }}</td>
                <td>{{ trim(implode(' ', array_filter([\App\Models\PrimaNota::TIPI_DOCUMENTO[$m->tipo_documento] ?? null, $m->numero_documento]))) }}</td>
                <td class="imp {{ $m->isGiroconto() ? '' : 'entrata' }}">{{ $m->isEntrata() ? $it($m->importo) : '' }}</td>
                <td class="imp {{ $m->isGiroconto() ? '' : 'uscita' }}">{{ $m->isEntrata() ? '' : $it($m->importo) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="center">Nessun movimento.</td></tr>
        @endforelse
        @if ($movimenti->isNotEmpty())
            <tr class="totali">
                <td colspan="6">Totali dell'elenco</td>
                <td class="imp entrata">{{ $it($entrate) }}</td>
                <td class="imp uscita">{{ $it($uscite) }}</td>
            </tr>
        @endif
    </tbody>
</table>
@endsection
