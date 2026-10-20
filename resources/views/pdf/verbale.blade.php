{{--
    PDF del verbale (v0.44.0, tavola VB-4), sulla carta intestata del periodo
    dell'ente alla data della seduta. Si genera una volta quando il verbale
    diventa definitivo (salvato con l'impronta); in bozza è l'anteprima, con
    la scritta BOZZA. In fondo i nomi di chi firma digitalmente: il
    presidente della seduta e il segretario verbalizzante (artt. 13.4, 16.6).
    Variabili: $v (Verbale), $documenti, $dataDocumento.
--}}
@extends('pdf.carta-intestata')

@section('titolo', ($v->codice() ? 'Verbale ' . $v->codice() : 'Bozza di verbale') . ' · ' . $v->titolo)

@section('stili')
.testata td.colonna { width: 72%; }
.testata td.numero-verbale { width: 28%; text-align: right; }
.numero-verbale { text-align: right; }
.numero-verbale strong { font-size: 15pt; color: #0f4124; }
.etichetta-num { font-size: 7pt; letter-spacing: .14em; text-transform: uppercase; color: #4f5451; }
.bozza { border: 1.5pt solid #9e6a00; color: #9e6a00; font-weight: bold; font-size: 12pt; text-align: center; padding: 2mm; margin-bottom: 4mm; letter-spacing: .1em; }
table.vb-tabella { width: 100%; border-collapse: collapse; margin: 1.5mm 0 3mm; }
table.vb-tabella th { font-size: 7pt; letter-spacing: .08em; text-transform: uppercase; color: #4f5451; text-align: left; padding: 1.2mm 1.5mm 1.2mm 0; border-bottom: 0.8pt solid #9aa0a8; }
table.vb-tabella td { padding: 1.2mm 1.5mm 1.2mm 0; border-bottom: 0.5pt solid #e3e1db; vertical-align: top; }
table.vb-dati { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
table.vb-dati td { padding: 0.6mm 0; vertical-align: top; }
table.vb-dati td.etichetta { width: 45mm; color: #4f5451; }
.vb-delibera { border-left: 2pt solid #0f4124; padding: 1mm 0 1mm 3.5mm; margin: 2mm 0 3mm; }
.vb-delibera-titolo { margin-bottom: 1mm; }
.vb-esito { margin-top: 1.5mm; }
.vb-nota { font-size: 7.5pt; color: #4f5451; }
.vb-html table { width: 100%; border-collapse: collapse; }
.vb-html td, .vb-html th { border: 0.5pt solid #cfd3d8; padding: 1mm 1.5mm; }
.vb-chiusura { margin-top: 4mm; }
.firme { margin-top: 12mm; }
.firme td { width: 50%; text-align: center; font-size: 8.5pt; color: #3b4459; vertical-align: bottom; padding: 0 5mm; }
.linea-firma { border-bottom: 0.5pt solid #9aa0a8; height: 10mm; margin-bottom: 1.5mm; }
.piede-verbale { margin-top: 8mm; font-size: 7pt; color: #4f5451; }
@endsection

@section('content')
@if ($v->isBozza())
    <div class="bozza">BOZZA · senza numero, non è il verbale definitivo</div>
@endif
<table class="colonne testata"><tr>
    <td class="colonna">
        <h1>Verbale {{ $v->libro === 'cd' ? 'del Consiglio Direttivo' : 'dell\'Assemblea' }}</h1>
        <p class="sotto">{{ $v->titolo }}</p>
    </td>
    <td class="colonna numero-verbale"><span class="etichetta-num">{{ $v->libro === 'cd' ? 'Libro del CD' : 'Libro delle Assemblee' }}</span><br>
        <strong>{{ $v->numeroTesto() ? 'n. ' . $v->numeroTesto() : '—' }}</strong><br>
        <span class="sotto">{{ $v->data_seduta?->format('d/m/Y') }}</span></td>
</tr></table>

@include('verbali._corpo', ['v' => $v, 'documenti' => $documenti])

<table class="colonne firme"><tr>
    <td><div class="linea-firma"></div>Il presidente della seduta<br><strong>{{ $v->presidente_assemblea ?: '—' }}</strong></td>
    <td><div class="linea-firma"></div>Il segretario verbalizzante<br><strong>{{ $v->segretario_verbalizzante ?: '—' }}</strong></td>
</tr></table>

<p class="piede-verbale">
    Verbale firmato digitalmente dal presidente della seduta e dal segretario verbalizzante.
    @if ($v->numeroTesto())
        Numero {{ $v->numeroTesto() }} del libro, reso definitivo il {{ $v->definitivo_il?->format('d/m/Y \a\l\l\e H:i') }}: da qui il testo non cambia; eventuali correzioni sono rettifiche datate e motivate (statuto, art. 23.2).
    @endif
    I documenti allegati si conservano a parte, ciascuno con il suo file.
</p>
@endsection
