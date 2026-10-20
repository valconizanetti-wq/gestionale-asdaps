{{--
    Libri dei verbali (v0.44.0, tavola VB-1, specifiche/verbali.md §3).
    Due schede, Consiglio Direttivo e Assemblee; l'anno; i numeri (bozze, da
    firmare, firmati); nel libro del CD le riunioni e le consultazioni
    aperte; poi il libro: numero nell'anno, data, verbale, stato, copia
    firmata. I verbali delle domande chiusi si rendono definitivi insieme
    (V11).
    Variabili: $libro, $anno, $anni, $verbali, $ammissioniChiuse, $riunioni,
    $consultazioni, $conti.
--}}
@extends('layouts.app')

@section('title', 'Verbali')

@section('content')
    @php $puoModificare = auth()->user()->puoModificare('verbali'); @endphp

    <x-page-header titolo="Libri dei" evidenza="verbali"
                   sottotitolo="Ogni verbale prende il numero nel suo libro quando diventa definitivo; da lì non cambia più (statuto art. 23.2).">
        @if ($puoModificare)
            <x-slot:azioni>
                <a href="{{ route('verbali.registra', ['libro' => $libro]) }}" class="btn btn-secondary">Registra un verbale già redatto</a>
                @if ($libro === 'cd')
                    <a href="{{ route('verbali.consultazioni.create') }}" class="btn btn-secondary"><x-icona nome="piu" width="14" height="14" /> Consultazione</a>
                    <a href="{{ route('verbali.riunioni.create') }}" class="btn btn-primary"><x-icona nome="piu" width="14" height="14" /> Riunione del CD</a>
                @endif
            </x-slot:azioni>
        @endif
    </x-page-header>

    <x-sola-lettura sezione="verbali" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Operazione non eseguita.">{{ $errors->first() }}</x-avviso>
    @endif

    <nav class="vb-schede" aria-label="Libri">
        @foreach (\App\Models\Verbale::LIBRI as $codice => $nome)
            <a href="{{ route('verbali.index', ['libro' => $codice]) }}" @class(['vb-scheda', 'attiva' => $libro === $codice])
               @if ($libro === $codice) aria-current="page" @endif>{{ $nome }}</a>
        @endforeach
    </nav>

    @if ($libro === 'cd' && $ammissioniChiuse > 0)
        <x-avviso tipo="warn" :titolo="$ammissioniChiuse === 1 ? 'Un verbale di domanda è chiuso.' : $ammissioniChiuse . ' verbali di domande sono chiusi.'">
            La consultazione sulla domanda è decisa: il verbale va reso definitivo, con il numero, e poi firmato.
            @if ($puoModificare)
                <form method="POST" action="{{ route('verbali.ammissioni.definitivi') }}" class="vb-avviso-azione" x-data="{ inviato: false }" x-on:submit="inviato = true">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm" x-bind:disabled="inviato">{{ $ammissioniChiuse === 1 ? 'Rendi definitivo' : 'Rendi definitivi i ' . $ammissioniChiuse . ' verbali' }}</button>
                </form>
            @endif
        </x-avviso>
    @endif

    @if ($libro === 'cd' && ($riunioni->isNotEmpty() || $consultazioni->isNotEmpty()))
        <h2 class="an-titolo">In corso</h2>
        <x-card senza-padding>
            <ul class="vb-aperte">
                @foreach ($riunioni as $s)
                    <li>
                        <a href="{{ route('verbali.riunioni.show', $s) }}" class="vb-aperta">
                            <span class="vb-aperta-testo">
                                <strong>Riunione del {{ $s->data->format('d/m/Y') }}</strong>
                                <span class="td-sub">ore {{ substr((string) $s->ora_inizio, 0, 5) }}{{ $s->luogo ? ' · ' . $s->luogo : '' }}{{ $s->videoconferenza ? ' · videoconferenza' : '' }} · {{ count($s->punti()) }} {{ count($s->punti()) === 1 ? 'punto' : 'punti' }}</span>
                            </span>
                            <span class="badge-riga">
                                @if ($s->urgente)<x-badge variante="warn">Urgente</x-badge>@endif
                                <x-badge :variante="$s->statoVariante()">{{ $s->stato === 'svolta' ? 'Presenze registrate' : $s->statoTesto() }}</x-badge>
                            </span>
                        </a>
                    </li>
                @endforeach
                @foreach ($consultazioni as $c)
                    <li>
                        <a href="{{ route('verbali.consultazioni.show', $c) }}" class="vb-aperta">
                            <span class="vb-aperta-testo">
                                <strong>Consultazione · {{ $c->oggetto }}</strong>
                                <span class="td-sub">
                                    @if ($c->isInCorso())
                                        termine {{ $c->termine->format('d/m/Y H:i') }} · votato {{ $c->votati_count }} su {{ $c->voti_count }}
                                    @else
                                        termine proposto {{ $c->termine->format('d/m/Y H:i') }} · la avvia il Presidente
                                    @endif
                                </span>
                            </span>
                            <x-badge :variante="$c->statoVariante()">{{ $c->statoTesto() }}</x-badge>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <div class="an-titolo-riga">
        <h2 class="an-titolo">{{ $libro === 'cd' ? 'Libro del Consiglio Direttivo' : 'Libro delle Assemblee' }} · {{ $anno }}</h2>
        @if ($anni->count() > 1)
            <form method="GET" action="{{ route('verbali.index') }}" class="an-anno" x-data>
                <input type="hidden" name="libro" value="{{ $libro }}">
                <label for="anno-libro" class="sr-only">Anno</label>
                <select id="anno-libro" name="anno" class="form-select" x-on:change="$el.form.submit()">
                    @foreach ($anni as $a)
                        <option value="{{ $a }}" @selected($a === $anno)>{{ $a }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-secondary btn-sm">Mostra</button></noscript>
            </form>
        @endif
    </div>

    <div class="kpi-row">
        <x-kpi label="Bozze" :valore="$conti['bozze']" :variante="$conti['bozze'] ? 'warn' : null" sub="Senza numero" />
        <x-kpi label="Da firmare" :valore="$conti['da_firmare']" :variante="$conti['da_firmare'] ? 'warn' : null" sub="Definitivi senza copia firmata" />
        <x-kpi label="Firmati" :valore="$conti['firmati']" :variante="$conti['firmati'] ? 'ok' : null" sub="Con la firma digitale" />
    </div>

    <x-card senza-padding>
        @if ($verbali->isEmpty())
            <x-vuoto>Nessun verbale nel {{ $anno }}.</x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive">
                    <thead>
                        <tr>
                            <th scope="col">N.</th>
                            <th scope="col">Data</th>
                            <th scope="col">Verbale</th>
                            <th scope="col">Stato</th>
                            <th scope="col">Copia firmata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($verbali as $v)
                            <tr class="riga-link" data-href="{{ route('verbali.show', $v) }}">
                                <td data-label="N." class="vb-numero">
                                    @if ($v->numero_libro)<strong>{{ $v->numeroTesto() }}</strong>@else<span class="testo-secondario">—</span>@endif
                                </td>
                                <td data-label="Data">{{ $v->data_seduta?->format('d/m/Y') ?? '—' }}</td>
                                <td data-label="Verbale" class="td-primario">
                                    <a href="{{ route('verbali.show', $v) }}">{{ $v->titolo ?: $v->tipoTesto() }}</a>
                                    <span class="td-sub">{{ $v->tipoTesto() }}@if ($v->rettifiche_count) · con rettifica @endif</span>
                                </td>
                                <td data-label="Stato"><x-badge :variante="$v->statoVariante()">{{ $v->statoTesto() }}</x-badge></td>
                                <td data-label="Copia firmata">
                                    @if ($v->isFirmato())
                                        {{ $v->firmaTesto() }}
                                        @if ($v->mancaFirmaPresidente())<span class="td-sub testo-ko">manca la firma del presidente della seduta</span>@endif
                                    @else
                                        <span class="testo-secondario">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <p class="form-help an-piede">
        @if ($libro === 'cd')
            Nel libro del CD entrano le riunioni, le consultazioni telematiche e i verbali delle domande di adesione. Le domande di prova non entrano.
        @else
            Le Assemblee si convocano e si verbalizzano nel gestionale dalla prossima versione; intanto si registrano qui i verbali già redatti e firmati.
        @endif
    </p>
@endsection
