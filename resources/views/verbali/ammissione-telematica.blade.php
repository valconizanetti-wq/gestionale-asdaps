{{--
    Corpo della bozza di verbale della consultazione telematica su una
    domanda di adesione (VerbaleAmmissione). HTML semplice, senza stili: si
    mostra dentro .documento-testo e un domani nel PDF dei verbali.
    Statuto v1.0: art. 6.4 (voto di ciascun consigliere, accoglimento
    d'ufficio), art. 16.4 (consultazione telematica), art. 16.6 (verbale).
--}}
@php
    $data = fn ($d) => $d?->locale('it')->isoFormat('D MMMM YYYY');
    $ora = fn ($d) => $d?->format('d/m/Y H:i');
    $categoria = $domanda->tipo_socio === 'minorenne' ? 'socio giovane' : 'socio ordinario';
    $chiusura = [
        'scadenza' => 'alla scadenza del termine',
        'tutti' => 'prima della scadenza, avendo votato tutti i consiglieri',
    ][$domanda->votazione_chiusura] ?? '';
    $componenti = $domanda->voti->count();
    $espressi = $domanda->votiEspressi();
    $presentataDa = $domanda->tipo_socio === 'minorenne'
        ? ', presentata da ' . $domanda->genitore_nome . ' che esercita la responsabilità genitoriale'
        : '';
    $comunicata = $domanda->esito_comunicato_il ? ', comunicata al richiedente il ' . $ora($domanda->esito_comunicato_il) : '';
@endphp
<h1>Verbale di consultazione telematica del Consiglio Direttivo</h1>

<p><strong>Oggetto:</strong> domanda di adesione come {{ $categoria }} di {{ $domanda->nomeCognome() }}, nato/a il {{ $domanda->data_nascita?->format('d/m/Y') }} a {{ $domanda->luogo_nascita }}{{ $presentataDa }}, presentata online il {{ $ora($domanda->created_at) }}.</p>

@if ($domanda->assenso_minore_il)
    {{-- v0.31.0: assenso del minore dai 14 anni --}}
    <p><strong>Assenso del minore:</strong> {{ $domanda->nomeCognome() }}, che ha compiuto {{ \App\Models\Domanda::ETA_ASSENSO_MINORE }} anni, ha prestato personalmente il proprio assenso al tesseramento con l'invio della domanda, il {{ $ora($domanda->assenso_minore_il) }} (art. 16, c. 2, D.Lgs. 36/2021; statuto, art. 6.3).</p>
@endif

<p><strong>Modalità:</strong> consultazione telematica ai sensi degli artt. 6.4 e 16.4 dello statuto. Ogni consigliere in carica ha ricevuto all'indirizzo di posta elettronica registrato la domanda e un collegamento personale per votare entro il {{ $ora($domanda->scadenza_cd) }}. La consultazione si è chiusa il {{ $ora($domanda->votazione_chiusa_il) }}, {{ $chiusura }}.</p>

<h2>Voti</h2>
<table>
    <thead>
        <tr><th>Consigliere</th><th>Voto</th><th>Data e ora</th><th>Collegamento</th></tr>
    </thead>
    <tbody>
        @foreach ($domanda->voti as $voto)
            <tr>
                <td>{{ $voto->consigliere_nome . ($voto->presidente ? ' (Presidente)' : '') }}</td>
                <td>{{ ['favorevole' => 'Favorevole', 'contrario' => 'Contrario'][$voto->voto] ?? ($voto->consigliere_email ? 'Non ha votato' : 'Non ha votato (senza indirizzo email)') }}</td>
                <td>{{ $voto->votato_il?->format('d/m/Y H:i') ?? '—' }}</td>
                <td>{{ $voto->votato_il ? 'n. ' . $voto->id : '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@php $motivati = $domanda->voti->where('voto', 'contrario')->filter(fn ($v) => filled($v->motivo_rifiuto)); @endphp
@if ($motivati->isNotEmpty())
    <h2>Motivazioni dei voti contrari</h2>
    <ul>
        @foreach ($motivati as $voto)
            <li><strong>{{ $voto->consigliere_nome }}:</strong> {{ $voto->motivo_rifiuto }}</li>
        @endforeach
    </ul>
@endif

<h2>Esito</h2>
<p>Componenti del Consiglio: {{ $componenti }}. Voti espressi: {{ $espressi }} ({{ $domanda->riepilogoVoti(false) }}).</p>
@if ($domanda->silenzio_assenso)
    <p>Alla scadenza del termine non ha votato la maggioranza dei componenti: la domanda si intende <strong>accolta d'ufficio</strong> (art. 6.4).</p>
@elseif ($domanda->decisione_presidente_il)
    <p>I voti espressi sono pari e il Presidente non aveva votato. Il {{ $ora($domanda->decisione_presidente_il) }} il Presidente ha deciso: la domanda è <strong>{{ $domanda->isRifiutata() ? 'non accolta' : 'accolta' }}</strong>.</p>
@elseif ($domanda->isInParita())
    <p>I voti espressi sono pari e il Presidente non ha votato: la decisione spetta al Presidente.</p>
@elseif (in_array($domanda->stato, ['da_rifiutare', 'rifiutata'], true))
    <p>Con la maggioranza dei voti espressi la domanda <strong>non è accolta</strong>.</p>
@else
    <p>Con la maggioranza dei voti espressi la domanda è <strong>accolta</strong>.</p>
@endif

@if ($domanda->isRifiutata() && filled($domanda->motivo_rifiuto))
    <p><strong>Motivazione del diniego</strong>{{ $comunicata }}: {{ $domanda->motivo_rifiuto }}</p>
    <p>Il richiedente può presentare appello all'Assemblea entro 30 giorni dalla comunicazione (art. 6.5).</p>
@elseif ($domanda->isDaRifiutare())
    <p>Il diniego motivato va comunicato al richiedente entro il {{ $data($domanda->scadenzaDiniego()) }} (art. 6.5).</p>
@elseif ($domanda->isApprovata() || $domanda->socio_id)
    <p>L'ammissione si perfeziona con il versamento della quota, disposto entro {{ \App\Models\Domanda::GIORNI_VERSAMENTO_QUOTA }} giorni dalla comunicazione, e con l'iscrizione nel libro dei soci (art. 6.6).</p>
@endif

<p>Il Presidente ______________________ &nbsp;&nbsp;&nbsp; Il Segretario ______________________</p>
