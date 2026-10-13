@extends('layouts.app')

@section('title', 'Importa soci da Excel')

@section('content')
    @if (! $anteprima)
        <x-page-header titolo="Importa soci" evidenza="da Excel"
                       sottotitolo="Dal modello compilato: i soci entrano nel libro soci tutti insieme, dopo un'anteprima"
                       :indietro="route('soci.index')" indietro-label="Soci" />

        <div class="pn-colonne">
            <x-card titolo="File dei soci">
                <form method="POST" action="{{ route('soci.importa.leggi') }}" enctype="multipart/form-data" class="form-grid"
                      x-data="{ inviato: false }" x-on:submit="inviato = true">
                    @csrf
                    <div @class(['form-field', 'ut-intero', 'has-error' => $errors->has('file')])>
                        <label class="form-label" for="campo-file">Modello compilato (.xlsx)<span class="obbligatorio" aria-hidden="true">*</span></label>
                        <input id="campo-file" name="file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required class="form-input" aria-describedby="campo-file-aiuto">
                        <div class="form-help" id="campo-file-aiuto">Il foglio «Soci» del modello, una riga per socio. Al massimo 2 MB.</div>
                        @error('file')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-actions ut-intero">
                        <a href="{{ route('soci.importa.modello') }}" class="btn btn-secondary"><x-icona nome="download" width="14" height="14" /> Scarica il modello</a>
                        <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Leggi il file</button>
                    </div>
                </form>
            </x-card>
            <aside class="pn-colonna">
                <x-card titolo="Cosa succede" class="pn-aiuto">
                    <p>Prima un'<strong>anteprima</strong> con gli errori riga per riga: niente si registra finché non confermi, e si conferma solo senza errori.</p>
                    <p>Data e luogo di nascita e sesso si ricavano dal <strong>codice fiscale</strong>; l'età decide se servono i dati del genitore.</p>
                    <p>Privacy e foto/video <strong>non</strong> si importano: dopo, manda il link privacy a tutti. Le tessere CSEN si caricano dal file del portale.</p>
                    <p>La riga d'esempio («ESEMPIO» nel cognome) si scarta da sola. Il file si cancella dopo la conferma.</p>
                </x-card>
            </aside>
        </div>
    @else
        @php $c = $anteprima['conteggi']; $totale = array_sum($c); $importabili = $c['pronte'] + $c['avvisi']; @endphp
        <x-page-header titolo="Importa soci" evidenza="anteprima"
                       :sottotitolo="$anteprima['nome'] . ($anteprima['letto_il'] ? ' · caricato alle ' . $anteprima['letto_il'] : '')"
                       :indietro="route('soci.index')" indietro-label="Soci" />

        @if ($anteprima['errore'])
            <x-avviso tipo="ko" titolo="File non letto.">{{ $anteprima['errore'] }}</x-avviso>
        @else
            <div class="kpi-row">
                <x-kpi label="Righe lette" :valore="$totale" :sub="$anteprima['esempio'] ? 'Riga d\'esempio scartata' : null" />
                <x-kpi label="Pronte" :valore="$c['pronte']" :variante="$c['pronte'] ? 'ok' : null" />
                <x-kpi label="Con avvisi" :valore="$c['avvisi']" :variante="$c['avvisi'] ? 'warn' : null" sub="Si importano" />
                <x-kpi label="Con errori" :valore="$c['errori']" :variante="$c['errori'] ? 'ko' : null" :sub="$c['errori'] ? 'Da correggere nel foglio' : null" />
            </div>

            @if ($c['errori'])
                <x-avviso tipo="ko" :titolo="$c['errori'] === 1 ? '1 riga con errori.' : $c['errori'] . ' righe con errori.'">
                    Correggile nel foglio e ricarica il file: si importa solo un foglio senza errori.
                </x-avviso>
            @else
                <x-avviso tipo="ok" titolo="Nessun errore.">Controlla gli avvisi, poi conferma: entrano {{ $importabili }} soci.</x-avviso>
            @endif

            <div x-data="{ tutte: {{ $c['errori'] + $c['avvisi'] ? 'false' : 'true' }} }">
                <x-card titolo="Anteprima" senza-padding>
                    <x-slot:azioni>
                        @if ($c['errori'] + $c['avvisi'] && $c['pronte'])
                            <label class="form-check"><input type="checkbox" x-model="tutte"> Mostra anche le righe pronte</label>
                        @endif
                    </x-slot:azioni>
                    <div class="table-wrap" :class="{ 'is-tutte': tutte }">
                        <table class="data-table responsive">
                            <thead>
                                <tr>
                                    <th scope="col">Riga</th>
                                    <th scope="col">Socio</th>
                                    <th scope="col">Nascita (dal codice fiscale)</th>
                                    <th scope="col">Tipo</th>
                                    <th scope="col">Ammissione</th>
                                    <th scope="col">Quota fino al</th>
                                    <th scope="col">Esito</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($anteprima['righe'] as $r)
                                    @php $n = $r['nascita']; @endphp
                                    <tr @class(['is-riga-ok' => $r['esito'] === 'pronta'])>
                                        <td data-label="Riga">{{ $r['numero'] }}</td>
                                        <td class="td-primario">{{ trim($r['cognome'] . ' ' . $r['nome']) ?: '—' }}<span class="td-sub code">{{ $r['codice_fiscale'] ?: '—' }}</span></td>
                                        <td data-label="Nascita">
                                            @if ($n['data'])
                                                {{ \Illuminate\Support\Carbon::parse($n['data'])->format('d/m/Y') }} · {{ $n['luogo'] }} · {{ $n['sesso'] }}
                                                <span class="td-sub">{{ $n['eta'] }} anni</span>
                                            @else — @endif
                                        </td>
                                        <td data-label="Tipo">
                                            @if ($n['data']){{ $r['minorenne'] ? 'Minorenne' : 'Ordinario' }}@else — @endif
                                            @if ($r['fondatore'])<span class="td-sub">{{ $n['sesso'] === 'F' ? 'Fondatrice' : 'Fondatore' }}</span>@endif
                                        </td>
                                        <td data-label="Ammissione">{{ $r['ammissione'] ? \Illuminate\Support\Carbon::parse($r['ammissione'])->format('d/m/Y') : '—' }}</td>
                                        <td data-label="Quota fino al">{{ $r['quota_fino'] ? \Illuminate\Support\Carbon::parse($r['quota_fino'])->format('d/m/Y') : '—' }}</td>
                                        <td data-label="Esito">
                                            @if ($r['esito'] === 'errore')
                                                <x-badge variante="ko">Errore</x-badge>
                                            @elseif ($r['esito'] === 'avviso')
                                                <x-badge variante="warn">Avviso</x-badge>
                                            @else
                                                <x-badge variante="ok">Pronta</x-badge>
                                            @endif
                                            @foreach ($r['errori'] as $m)<span class="is-msg ko">{{ $m }}</span>@endforeach
                                            @foreach ($r['avvisi'] as $m)<span class="is-msg warn">{{ $m }}</span>@endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>
        @endif

        <div class="form-actions">
            <form method="POST" action="{{ route('soci.importa.annulla') }}">
                @csrf
                <button type="submit" class="btn btn-ghost">{{ $anteprima['errore'] || $c['errori'] ? 'Carica il file corretto' : 'Annulla' }}</button>
            </form>
            @if (! $anteprima['errore'] && ! $c['errori'] && $importabili > 0)
                <form method="POST" action="{{ route('soci.importa.conferma') }}" x-data="{ inviato: false }" x-on:submit="inviato = true">
                    @csrf
                    <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Importa {{ $importabili }} {{ $importabili === 1 ? 'socio' : 'soci' }}</button>
                </form>
            @endif
        </div>
    @endif
@endsection
