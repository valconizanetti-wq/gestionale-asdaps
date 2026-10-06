{{-- Anomalia nella catena dell'Audit Log (tavola "Audit Log 5"; audit-log.md §9). Nessun dato del registro. --}}
<x-mail::message>
<p class="occhiello">Gestionale · verifica automatica</p>

# Anomalia nel registro

la verifica mensile del registro delle operazioni ha trovato **{{ $dati['anomalie'] }} {{ $dati['anomalie'] === 1 ? 'riga anomala' : 'righe anomale' }} su {{ number_format($dati['righe'], 0, ',', '.') }}**, a partire dalla riga n. {{ $dati['primo_anomalo'] }} (anno {{ $anno }}).

Nessuna correzione automatica è stata fatta. Apri la pagina di verifica per il dettaglio e, se l'anomalia non ha una spiegazione nota, parlane con chi gestisce il gestionale.

<x-mail::button :url="$url">
Apri la verifica
</x-mail::button>

L'email non contiene dati del registro: solo numeri di riga e conteggi.

<p class="indirizzo">Indirizzo della pagina: {{ $url }}</p>
</x-mail::message>
