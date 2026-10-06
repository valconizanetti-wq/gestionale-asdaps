{{-- Esportazione PDF dell'Audit Log (risultati filtrati; audit-log.md §9). --}}
@use('App\Services\AuditConsultazione', 'C')
@php
    $docTitolo      = 'Audit Log ' . $filtri['anno'];
    $docDataLabel   = 'Estratto il';
    $docData        = $generatoIl;
    $orientamento   = 'landscape';
    $showPagination = true;
    $bodyPadding    = '34mm 12mm 20mm 12mm';
    $mesi = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
    $descrizione = collect([
        $filtri['mese'] ? 'mese di ' . $mesi[$filtri['mese']] : null,
        $filtri['modello'] !== '' ? 'modulo ' . C::modello($filtri['modello']) : null,
        $filtri['rischio'] !== '' ? 'rischio ' . strtolower(C::rischio($filtri['rischio'])[0]) : null,
        $filtri['evento'] !== '' ? 'evento ' . C::evento($filtri['evento']) : null,
        $filtri['utente'] !== '' ? 'operatore ' . $filtri['utente'] : null,
        $filtri['cerca'] !== '' ? 'ricerca «' . $filtri['cerca'] . '»' : null,
    ])->filter()->implode(', ');
@endphp
@extends('pdf.layout')

@section('styles')
<style>
body { font-size: 7.5px; }
table.doc-table { font-size: 7px; }
table.doc-table th.c-n { width: 5%; }
table.doc-table th.c-d { width: 11%; }
table.doc-table th.c-m { width: 10%; }
table.doc-table th.c-o { width: 16%; }
table.doc-table th.c-r { width: 6%; }
table.doc-table th.c-t { width: 38%; }
table.doc-table th.c-u { width: 14%; }
</style>
@endsection

@section('piede', 'Contiene indirizzi IP e riepiloghi delle operazioni: non divulgare')

@section('content')
@php
    $quante = number_format($righe->count(), 0, ',', '.')
        . ($totale > $righe->count() ? ' di ' . number_format($totale, 0, ',', '.') . " (le più recenti; per l'elenco completo usare il CSV)" : '');
@endphp
<p class="filtro-info">Righe: <strong>{{ $quante }}</strong> · {{ $descrizione ?: 'nessun filtro' }} · estratto da {{ $operatore }}</p>
<p class="filtro-info">Ultima riga del registro all'estrazione: n. {{ $ultima?->id ?? '—' }}, impronta SHA-256 {{ $ultima?->hash ?? '—' }}</p>

<table class="doc-table">
        <thead><tr><th class="c-n">Riga</th><th class="c-d">Data e ora</th><th class="c-m">Modulo</th><th class="c-o">Operazione</th><th class="c-r">Rischio</th><th class="c-t">Riepilogo</th><th class="c-u">Operatore</th></tr></thead>
    <tbody>
        @foreach ($righe as $i => $r)
            <tr @class(['pari' => $i % 2 === 1])>
                <td class="num">{{ $r->id }}</td>
                <td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i:s') }}</td>
                <td>{{ C::modello($r->modello ?? $r->entita_tipo) }}{{ $r->entita_id ? ' #' . $r->entita_id : '' }}</td>
                <td>{{ C::operazione($r) }}</td>
                <td>{{ C::rischio($r->livello_rischio)[0] }}</td>
                <td>{{ $r->dettaglio }}</td>
                <td>{{ $r->utente_nome }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endsection
