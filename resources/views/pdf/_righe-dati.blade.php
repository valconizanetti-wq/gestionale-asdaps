{{-- Tabella etichetta/valore dei PDF (ricevuta della domanda). $righe: [[etichetta, valore, monospazio], …] --}}
<table class="dati">
    @foreach ($righe as [$etichetta, $valore, $mono])
        <tr><td class="etichetta">{{ $etichetta }}</td><td @class(['mono' => $mono])>{{ $valore }}</td></tr>
    @endforeach
</table>
