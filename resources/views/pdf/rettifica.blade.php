{{--
    PDF della rettifica di un verbale definitivo (v0.44.0, tavola VB-3): una
    nota datata e motivata che corregge il verbale senza toccarne il testo
    (statuto, art. 23.2). Si firma digitalmente come il verbale.
    Variabili: $r (VerbaleRettifica con verbale, creataDa), $dataDocumento.
--}}
@extends('pdf.carta-intestata')

@php $v = $r->verbale; @endphp

@section('titolo', 'Rettifica n. ' . $r->numero . ' al verbale ' . $v->codice())

@section('stili')
.testata td.colonna { width: 72%; }
.testata td.numero-verbale { width: 28%; text-align: right; }
.numero-verbale { text-align: right; }
.numero-verbale strong { font-size: 15pt; color: #0f4124; }
.etichetta-num { font-size: 7pt; letter-spacing: .14em; text-transform: uppercase; color: #4f5451; }
.vb-html table { width: 100%; border-collapse: collapse; }
.vb-html td, .vb-html th { border: 0.5pt solid #cfd3d8; padding: 1mm 1.5mm; }
.firme { margin-top: 14mm; }
.firme td { width: 50%; text-align: center; font-size: 8.5pt; color: #3b4459; vertical-align: bottom; padding: 0 5mm; }
.linea-firma { border-bottom: 0.5pt solid #9aa0a8; height: 10mm; margin-bottom: 1.5mm; }
@endsection

@section('content')
<table class="colonne testata"><tr>
    <td class="colonna">
        <h1>Rettifica n. {{ $r->numero }}</h1>
        <p class="sotto">al verbale {{ $v->codice() }} del {{ $v->data_seduta?->format('d/m/Y') }} · {{ $v->titolo }}</p>
    </td>
    <td class="colonna numero-verbale"><span class="etichetta-num">Data della rettifica</span><br>
        <strong>{{ $r->created_at->format('d/m/Y') }}</strong></td>
</tr></table>

<div class="riquadro"><strong>Motivo.</strong> {{ $r->motivo }}</div>

<h2>Testo della rettifica</h2>
<div class="vb-html">{!! $r->testo_html !!}</div>

<p class="nota">La rettifica si aggiunge in fondo al verbale {{ $v->codice() }} e non ne modifica il testo, che resta come è stato reso definitivo (statuto, art. 23.2).
    Registrata nel Gestionale il {{ $r->created_at->format('d/m/Y \a\l\l\e H:i') }}.</p>

<table class="colonne firme"><tr>
    <td><div class="linea-firma"></div>Il presidente della seduta<br><strong>{{ $v->presidente_assemblea ?: '—' }}</strong></td>
    <td><div class="linea-firma"></div>Il segretario verbalizzante<br><strong>{{ $v->segretario_verbalizzante ?: '—' }}</strong></td>
</tr></table>
@endsection
