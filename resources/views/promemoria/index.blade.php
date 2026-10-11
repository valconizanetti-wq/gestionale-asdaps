@extends('layouts.app')

@section('title', 'Promemoria')

@use('App\Promemoria\Invii')

@section('content')
    <x-page-header titolo="Promemoria" sottotitolo="Email automatiche pianificate: ai soci (quote) e, per le scadenze, a Presidente e Tesoriere prima della data. Chi le modifica dipende dalla sezione." />

    <x-sola-lettura sezione="promemoria" />

    <x-avviso tipo="info" titolo="Come funzionano.">
        Il giorno e l'ora scelti il gestionale calcola i destinatari (per esempio chi non ha versato la quota), manda le email e registra ogni invio.
        Un socio riceve lo stesso promemoria una volta sola per anno; per i minorenni l'email va al genitore. Chi non ha email resta nello storico, da avvisare a mano.
        I promemoria «N giorni prima» partono per ogni scadenza (o gruppo di tessere) quando arriva la sua data, una volta sola.
    </x-avviso>

    @forelse ($funzioni as $chiave => $f)
        @php
            $elenco = $promemoria->get($chiave, collect());
            $puo = $utente->puoModificare($f->sezione());
        @endphp
        <x-card :titolo="$f->nome()" senza-padding>
            <x-slot:azioni>
                @if ($puo)
                    <a href="{{ route('promemoria.create', ['funzione' => $chiave]) }}" class="btn btn-secondary btn-sm"><x-icona nome="piu" width="14" height="14" /> Nuovo promemoria</a>
                @endif
            </x-slot:azioni>
            <p class="pr-descrizione">{{ $f->descrizione() }}</p>
            @if ($elenco->isEmpty())
                <x-vuoto>Nessun promemoria per questa funzione.</x-vuoto>
            @else
                <div class="table-wrap">
                    <table class="data-table responsive">
                        <thead>
                            <tr>
                                <th scope="col">Promemoria</th>
                                <th scope="col">Quando</th>
                                <th scope="col">Destinatari</th>
                                <th scope="col">Ultimo invio</th>
                                <th scope="col">Prossimo invio</th>
                                <th scope="col">Stato</th>
                                <th scope="col"><span class="sr-only">Azioni</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($elenco as $p)
                                @php $prossimo = $p->attivo ? Invii::prossimoDettaglio($p) : null; @endphp
                                <tr class="riga-link" data-href="{{ route('promemoria.edit', $p) }}">
                                    <td class="td-primario"><a href="{{ route('promemoria.edit', $p) }}" class="td-name">{{ $p->nome }}</a>
                                        <span class="td-sub">{{ $p->oggetto }}</span></td>
                                    <td data-label="Quando">{{ $p->quandoTesto() }}</td>
                                    <td data-label="Destinatari">{{ $p->destinatariTesto() }}</td>
                                    <td data-label="Ultimo invio">{{ $p->ultimoInvioTesto() }}</td>
                                    <td data-label="Prossimo invio">
                                        @if ($prossimo)
                                            <span class="td-pila"><span>{{ $prossimo['quando']->format('d/m/Y H:i') }}</span>@if ($prossimo['cosa'])<span class="td-sub">{{ $prossimo['cosa'] }}</span>@endif</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td data-label="Stato"><x-badge :variante="$p->attivo ? 'ok' : 'neutral'">{{ $p->attivo ? 'Attivo' : 'Spento' }}</x-badge></td>
                                    {{-- Collaudo K9: dettaglio (destinatari, anteprima, prova) da qui o con un clic sulla riga --}}
                                    <td class="td-azioni"><a href="{{ route('promemoria.edit', $p) }}" class="action-btn" aria-label="Apri «{{ $p->nome }}»: destinatari, anteprima e prova"><x-icona nome="avanti" /></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    @empty
        <x-vuoto>Nessuna funzione con promemoria per il tuo ruolo.</x-vuoto>
    @endforelse

    <p class="form-help">Altre funzioni (per esempio i certificati medici in scadenza) si aggiungono con i moduli che le portano: stessa pagina, stesso storico.</p>
@endsection
