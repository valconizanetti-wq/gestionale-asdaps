{{-- Avviso di un'operazione delicata fatta da un altro utente (AvvisiOperazioni, v0.40.0). Solo il dettaglio dell'Audit Log, che non contiene dati personali. --}}
<x-mail::message>
<p class="occhiello">Gestionale · operazione registrata</p>

# {{ ucfirst($titolo) }}

Ciao {{ explode(' ', trim($destinatario))[0] }},

**{{ $autore }}** ha fatto un'operazione che riguarda anche il tuo ruolo, il {{ $quando->format('d/m/Y') }} alle {{ $quando->format('H:i') }}. Nel registro delle operazioni:

@foreach ($righe as $r)
- **{{ ucfirst(\App\Mail\OperazioneAvviso::titoloEvento($r['evento'])) }}** (riga n. {{ $r['logId'] }}). {{ $r['dettaglio'] }}
@endforeach

Se non te l'aspettavi, parlane con {{ $autore }} o con il Presidente.

<x-mail::button :url="$url">
Apri il gestionale
</x-mail::button>

Ricevi questo avviso perché nel Consiglio Direttivo hai un ruolo che vede questa parte del gestionale.

<p class="indirizzo">Indirizzo della pagina: {{ $url }}</p>
</x-mail::message>
