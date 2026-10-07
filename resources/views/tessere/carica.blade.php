@extends('layouts.app')

@section('title', 'Carica tessere già emesse')

@use('App\Services\CaricoTessereCsen')

@section('content')
    <x-page-header titolo="Carica tessere" evidenza="già emesse"
                   sottotitolo="Dall'elenco esportato dal portale CSEN: le tessere dei soci con numero, tipo e data di rilascio, così i rinnovi partono dalle date giuste"
                   :indietro="route('tesseramento.index')" indietro-label="Tessere CSEN" />

    @if (! $anteprima)
        <div class="pn-colonne">
            <x-card titolo="File del CSEN">
                <form method="POST" action="{{ route('tesseramento.carica.file') }}" enctype="multipart/form-data" class="form-grid">
                    @csrf
                    <div @class(['form-field', 'has-error' => $errors->has('file')])>
                        <label class="form-label" for="campo-file">Elenco in CSV<span class="obbligatorio" aria-hidden="true">*</span></label>
                        <input id="campo-file" name="file" type="file" accept=".csv,text/csv" required class="form-input" aria-describedby="campo-file-aiuto">
                        <div class="form-help" id="campo-file-aiuto">Il file che il portale CSEN esporta per l'associazione (colonne separate da punto e virgola).</div>
                        @error('file')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Leggi il file</button>
                    </div>
                </form>
            </x-card>
            <aside class="pn-colonna">
                <x-card titolo="Cosa succede" class="pn-aiuto">
                    <p>Prima un'<strong>anteprima</strong>: niente si registra finché non confermi.</p>
                    <p>Il socio si trova per <strong>codice fiscale</strong>; la sua anagrafica non cambia. Telefono ed email del file non si leggono.</p>
                    <p>Si caricano numero, tipo, data di rilascio (la scadenza è un anno dopo), qualifica e discipline. Le tessere già presenti si saltano: il file si può ricaricare.</p>
                    <p>Un tipo di tessera nuovo (es. Gold Base) si aggiunge senza prezzo. Il file si cancella dopo la conferma.</p>
                </x-card>
            </aside>
        </div>
    @else
        @php
            $righe = collect($anteprima['righe']);
            $conteggi = $righe->countBy('esito');
        @endphp

        @if ($anteprima['errore'])
            <x-avviso tipo="ko" titolo="File non letto.">{{ $anteprima['errore'] }}</x-avviso>
        @else
            <div class="kpi-row">
                @foreach (CaricoTessereCsen::ESITI as $esito => [$label, $variante])
                    <x-kpi :label="$label" :valore="$conteggi[$esito] ?? 0" :variante="($conteggi[$esito] ?? 0) && $esito !== 'presente' ? $variante : null" />
                @endforeach
            </div>

            <x-card :titolo="'Anteprima · ' . $anteprima['nome']" senza-padding>
                <div class="table-wrap">
                    <table class="data-table responsive ts-tabella">
                        <thead>
                            <tr>
                                <th scope="col">Riga</th>
                                <th scope="col">Nel file</th>
                                <th scope="col">Socio</th>
                                <th scope="col">Numero</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Rilascio</th>
                                <th scope="col">Scade</th>
                                <th scope="col">Esito</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($righe as $r)
                                @php [$eTesto, $eVar] = CaricoTessereCsen::ESITI[$r['esito']]; @endphp
                                <tr>
                                    <td data-label="Riga" class="td-solo-desktop">{{ $r['riga'] }}</td>
                                    <td class="td-primario">{{ $r['cognome'] }} {{ $r['nome'] }}<span class="td-sub">{{ $r['codice_fiscale'] }}</span></td>
                                    <td data-label="Socio">{{ $r['socio'] ?? '—' }}</td>
                                    <td data-label="Numero"><span class="ts-numero">{{ $r['numero'] }}</span></td>
                                    <td data-label="Tipo">{{ $r['tipo'] ?: '—' }}</td>
                                    <td data-label="Rilascio">{{ $r['data_rilascio'] ? \Illuminate\Support\Carbon::parse($r['data_rilascio'])->format('d/m/Y') : '—' }}</td>
                                    <td data-label="Scade">{{ $r['scadenza'] ? \Illuminate\Support\Carbon::parse($r['scadenza'])->format('d/m/Y') : '—' }}</td>
                                    <td data-label="Esito"><x-badge :variante="$eVar">{{ $eTesto }}</x-badge>
                                        @if ($r['messaggio'])<span class="td-sub">{{ $r['messaggio'] }}</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif

        <div class="form-actions">
            <form method="POST" action="{{ route('tesseramento.carica.annulla') }}">
                @csrf
                <button type="submit" class="btn btn-ghost">{{ $anteprima['errore'] ? 'Scegli un altro file' : 'Annulla' }}</button>
            </form>
            @if (! $anteprima['errore'] && ($conteggi['nuova'] ?? 0) > 0)
                <form method="POST" action="{{ route('tesseramento.carica.conferma') }}" x-data="{ inviato: false }" x-on:submit="inviato = true">
                    @csrf
                    <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Carica {{ $conteggi['nuova'] }} {{ $conteggi['nuova'] === 1 ? 'tessera' : 'tessere' }}</button>
                </form>
            @endif
        </div>
    @endif
@endsection
