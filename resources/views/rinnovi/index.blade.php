@extends('layouts.app')

@section('title', 'Rinnovi quota')

@use('App\Support\Importo')
@use('App\Services\RinnoviQuota')
@use('App\Models\Rinnovo')

@section('content')
    @php
        $parametri = request()->query();
        $filtroAttivo = $filtri['situazione'] !== '' || $filtri['categoria'] !== '' || $filtri['q'] !== '';
        $vediSoci = auth()->user()->puoAccedere('soci');
        $oggi = now();
        $percentuale = $kpi['attivi'] ? (int) round($kpi['in_regola'] / $kpi['attivi'] * 100) : 0;
    @endphp

    <x-page-header titolo="Rinnovi" evidenza="quota"
                   :sottotitolo="'Anno sociale ' . $anno . ' · quota entro il 31 gennaio (statuto art. 8) · decadenza dopo il 30 aprile (art. 9)'">
        <x-slot:azioni>
            @if (auth()->user()->puoAccedere('promemoria'))
                <a href="{{ route('promemoria.index') }}" class="btn btn-secondary">Promemoria</a>
            @endif
            <a href="{{ route('rinnovi.export-csv', $parametri + ['anno' => $anno]) }}" class="btn btn-secondary solo-desktop">
                <x-icona nome="download" width="14" height="14" /> CSV
            </a>
            @if ($puoModificare)
                <a href="{{ route('rinnovi.create') }}" class="btn btn-primary"><x-icona nome="piu" width="14" height="14" /> Registra quota</a>
            @endif
        </x-slot:azioni>
    </x-page-header>

    <x-sola-lettura sezione="rinnovi_quota" />

    @include('tessere._suggerita')

    {{-- Fase dell'anno (RQ-1): solo se qualcuno non ha ancora versato --}}
    @if ($kpi['mancanti'] > 0 && $nonVersata !== RinnoviQuota::IN_ATTESA)
        @php $link = route('rinnovi.index', ['anno' => $anno, 'situazione' => 'non_versata']); @endphp
        @if ($nonVersata === RinnoviQuota::SOSPESO)
            <x-avviso tipo="warn" titolo="Termine del 31 gennaio passato.">
                {{ $kpi['mancanti'] }} {{ $kpi['mancanti'] === 1 ? 'socio non ha' : 'soci non hanno' }} ancora versato: non votano in Assemblea finché non versano.
                Dal 1° maggio compaiono tra i «da dichiarare decaduti» (presa d'atto del CD).
                <a href="{{ $link }}">Mostra</a>
            </x-avviso>
        @else
            <x-avviso tipo="ko" titolo="Da dichiarare decaduti.">
                {{ $kpi['mancanti'] }} {{ $kpi['mancanti'] === 1 ? 'socio non ha' : 'soci non hanno' }} versato la quota {{ $anno }} entro il 30 aprile:
                decadono per morosità (statuto art. 9). La presa d'atto del CD, con il verbale, arriverà con il modulo Verbali.
                <a href="{{ $link }}">Mostra</a>
            </x-avviso>
        @endif
    @endif

    <div class="kpi-row kpi-row-5">
        <x-kpi label="Soci" :valore="$kpi['attivi']" :sub="($kpi['attivi'] - $kpi['giovani']) . ' ordinari · ' . $kpi['giovani'] . ' giovani'" />
        <x-kpi label="In regola" :valore="$kpi['in_regola']" variante="ok" :sub="$percentuale . '% · ' . Importo::it($kpi['incassato'], true)"
               :href="route('rinnovi.index', ['anno' => $anno, 'situazione' => 'versata'])" :attivo="$filtri['situazione'] === 'versata'" />
        {{-- Dopo il 30 aprile chi non ha versato è tra i «da dichiarare decaduti», non più «in ritardo» --}}
        @php $ritardo = $nonVersata === RinnoviQuota::DA_DICHIARARE_DECADUTO ? 0 : $kpi['mancanti']; @endphp
        <x-kpi :label="$nonVersata === RinnoviQuota::IN_ATTESA ? 'Da versare' : 'In ritardo'" :valore="$ritardo"
               :variante="$ritardo && $nonVersata === RinnoviQuota::SOSPESO ? 'warn' : null"
               :sub="match ($nonVersata) { RinnoviQuota::IN_ATTESA => 'entro il 31/01/' . $anno, RinnoviQuota::SOSPESO => 'voto sospeso fino al versamento', default => 'termine passato' }"
               :href="$ritardo ? route('rinnovi.index', ['anno' => $anno, 'situazione' => 'non_versata']) : null" :attivo="$ritardo && $filtri['situazione'] === 'non_versata'" />
        <x-kpi label="Da dichiarare decaduti" :valore="$nonVersata === RinnoviQuota::DA_DICHIARARE_DECADUTO ? $kpi['mancanti'] : 0"
               :variante="$nonVersata === RinnoviQuota::DA_DICHIARARE_DECADUTO && $kpi['mancanti'] ? 'ko' : null" :sub="'dal 1° maggio ' . $anno" />
        <x-kpi label="Ricevute" :valore="$kpi['ricevute']" :sub="$kpi['inviate'] . ' inviate · ' . ($kpi['ricevute'] - $kpi['inviate']) . ' da inviare'" />
    </div>

    <form method="GET" action="{{ route('rinnovi.index') }}" class="filter-bar" x-data="{ aperti: false }">
        <div class="form-field">
            <label class="form-label" for="f-anno">Anno sociale</label>
            <select id="f-anno" name="anno" class="form-select">
                @foreach ($anni as $a)
                    <option value="{{ $a }}" @selected($a === $anno)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-field filtro-cerca">
            <label class="form-label" for="f-q">Cerca</label>
            <input id="f-q" name="q" type="search" value="{{ $filtri['q'] }}" class="form-input" placeholder="Cognome, nome, n. ricevuta" autocomplete="off">
        </div>
        <button type="button" class="btn btn-secondary filtri-toggle" @click="aperti = !aperti" :aria-expanded="aperti.toString()" aria-controls="filtri-extra">
            <x-icona nome="filtro" width="14" height="14" /> Filtri
        </button>
        <div id="filtri-extra" class="filtri-extra" :class="{ 'aperti': aperti }">
            <div class="form-field">
                <label class="form-label" for="f-situazione">Situazione</label>
                <select id="f-situazione" name="situazione" class="form-select">
                    <option value="">Tutti</option>
                    @foreach (\App\Http\Controllers\RinnoviController::FILTRI_SITUAZIONE as $v => $l)
                        <option value="{{ $v }}" @selected($filtri['situazione'] === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="f-categoria">Categoria</label>
                <select id="f-categoria" name="categoria" class="form-select">
                    <option value="">Tutte</option>
                    @foreach (Rinnovo::CATEGORIE as $v => $l)
                        <option value="{{ $v }}" @selected($filtri['categoria'] === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Filtra</button>
    </form>

    <x-card :titolo="'Soci e quote ' . $anno" senza-padding>
        <x-slot:azioni>
            @if ($filtroAttivo)
                <a href="{{ route('rinnovi.index', ['anno' => $anno]) }}" class="btn btn-ghost btn-sm">Togli i filtri</a>
            @endif
        </x-slot:azioni>

        @if ($soci->isEmpty())
            <x-vuoto>
                @if ($filtroAttivo)
                    Nessun socio corrisponde ai filtri. <a href="{{ route('rinnovi.index', ['anno' => $anno]) }}">Mostra tutti</a>
                @else
                    Nessun socio attivo per il {{ $anno }}.
                @endif
            </x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive rq-tabella">
                    <thead>
                        <tr>
                            <th scope="col">Socio</th>
                            <th scope="col">Categoria</th>
                            <th scope="col" class="td-num">Quota €</th>
                            <th scope="col">Situazione</th>
                            <th scope="col">Versata</th>
                            <th scope="col">Ricevuta</th>
                            <th scope="col">Tessera CSEN</th>
                            <th scope="col"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($soci as $s)
                            @php
                                $r = $s->rinnovi->first();
                                $cat = $r?->categoria ?? RinnoviQuota::categoria($s, $anno);
                                [$sitTesto, $sitVar] = RinnoviQuota::SITUAZIONI[RinnoviQuota::situazione((bool) $r, $anno)];
                                $eta = RinnoviQuota::eta($s, $oggi);
                                $sotto = array_filter([
                                    $cat === 'giovane' && $eta !== null ? $eta . ' anni' : null,
                                    $s->fondatore ? ($s->sesso === 'F' ? 'Fondatrice' : 'Fondatore') : null,
                                    $cat === 'giovane' && $s->tutore_nome ? 'genitore ' . $s->tutore_nome : null,
                                ]);
                            @endphp
                            <tr @if ($vediSoci) class="riga-link" data-href="{{ route('soci.show', $s) }}#quote" @endif>
                                <td class="td-primario">
                                    @if ($vediSoci)
                                        <a href="{{ route('soci.show', $s) }}#quote" class="pn-desc">{{ $s->cognome }} {{ $s->nome }}</a>
                                    @else
                                        <span class="pn-desc">{{ $s->cognome }} {{ $s->nome }}</span>
                                    @endif
                                    @if ($sotto)<span class="td-sub">{{ implode(' · ', $sotto) }}</span>@endif
                                </td>
                                <td data-label="Categoria">{{ Rinnovo::CATEGORIE[$cat] }}</td>
                                <td data-label="Quota €" class="td-num">{{ Importo::it($r?->importo ?? RinnoviQuota::importo($cat)) }}</td>
                                <td data-label="Situazione"><x-badge :variante="$sitVar">{{ $sitTesto }}</x-badge></td>
                                <td data-label="Versata" @class(['td-solo-desktop' => ! $r])>
                                    @if ($r){{ $r->data_pagamento->format('d/m/Y') }} <span class="td-sub">{{ $r->metodoLabel() }} · {{ \App\Models\PrimaNota::CONTI[Rinnovo::contoPer($r->metodo_pagamento)] }}</span>@else — @endif
                                </td>
                                <td data-label="Ricevuta" @class(['td-solo-desktop' => ! $r])>
                                    @if ($r && $r->senzaRicevuta())
                                        — <span class="td-sub">vecchio gestionale</span>
                                    @elseif ($r)
                                        <a href="{{ route('rinnovi.ricevuta', $r) }}" target="_blank" rel="noopener" class="rq-numero">{{ $r->numero_ricevuta }}</a>
                                        <span class="td-sub">{{ $r->ricevuta_stato === 'inviata' ? 'inviata' : ($r->ricevuta_stato === 'errore' ? 'invio non riuscito' : 'da inviare') }}</span>
                                    @else — @endif
                                </td>
                                <td data-label="Tessera CSEN"><x-badge :variante="$tessere[$s->id]['variante']">{{ $tessere[$s->id]['testo'] }}</x-badge></td>
                                <td class="td-azioni">
                                    @if ($r)
                                        @unless ($r->senzaRicevuta())
                                            <a href="{{ route('rinnovi.ricevuta', [$r, 'scarica' => 1]) }}" class="action-btn" aria-label="Scarica la ricevuta {{ $r->numero_ricevuta }}"><x-icona nome="download" /></a>
                                        @endunless
                                        @if ($puoModificare)
                                            @unless ($r->senzaRicevuta())
                                                <form method="POST" action="{{ route('rinnovi.invia', $r) }}">
                                                    @csrf
                                                    <button type="submit" class="action-btn" aria-label="Invia la ricevuta {{ $r->numero_ricevuta }}"><x-icona nome="mail" /></button>
                                                </form>
                                            @endunless
                                            {{-- Annullamento anche da qui: il Tesoriere non accede alla scheda del socio --}}
                                            <button type="button" class="action-btn" aria-label="Annulla la {{ $r->riferimento() }}"
                                                    @click="$dispatch('apri-modale', 'annulla-{{ $r->id }}')"><x-icona nome="cestino" /></button>
                                        @endif
                                    @elseif ($puoModificare)
                                        <a href="{{ route('rinnovi.create', ['socio' => $s->id, 'anno' => $anno]) }}" class="btn btn-secondary btn-sm">Registra</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-paginazione :elementi="$soci" nome="soci" />
            <div class="table-footer">
                <span class="pn-totali">Incassato nel filtro: <strong class="entrate">{{ Importo::it($totaleFiltro, true) }}</strong></span>
                {{-- Quota netta (v0.37.0): incassato dell'anno meno il costo delle tessere CSEN emesse nell'anno --}}
                <span class="pn-totali">Quota netta {{ $anno }}: <strong>{{ Importo::it($costiTessere['netta'], true) }}</strong>
                    <span class="td-sub">{{ Importo::it($costiTessere['incassato'], true) }} incassati − {{ Importo::it($costiTessere['costo'], true) }} di tessere CSEN ({{ $costiTessere['emesse'] }})</span></span>
            </div>
        @endif
    </x-card>

    @if (auth()->user()->puoAccedere('promemoria'))
        <x-card titolo="Promemoria delle quote">
            <x-slot:azioni><a href="{{ route('promemoria.index') }}" class="btn btn-secondary btn-sm">Apri i promemoria</a></x-slot:azioni>
            @if ($promemoria->isEmpty())
                <p class="form-help">Nessun promemoria per le quote.</p>
            @else
                <div class="rq-promemoria">
                    @foreach ($promemoria as $p)
                        <div class="rq-promemoria-box">
                            <div class="rq-promemoria-testa"><strong>{{ $p->nome }}</strong>
                                <x-badge :variante="$p->attivo ? 'ok' : 'neutral'">{{ $p->attivo ? 'Attivo' : 'Spento' }}</x-badge></div>
                            <span>{{ $p->quandoTesto() }}</span>
                            <span class="td-sub">{{ $p->ultimoInvioTesto() }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
            <p class="form-help">Destinatari calcolati il giorno dell'invio: solo chi non ha ancora versato. Per i soci giovani l'email va al genitore.</p>
        </x-card>
    @endif

    @if ($puoModificare)
        @push('modals')
            @foreach ($soci as $s)
                @if ($s->rinnovi->first())
                    @include('rinnovi._annulla-modale', ['r' => $s->rinnovi->first()])
                @endif
            @endforeach
        @endpush
    @endif
@endsection
