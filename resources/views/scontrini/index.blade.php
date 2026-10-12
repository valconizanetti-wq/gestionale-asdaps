{{--
    Elenco degli scontrini (tavola SN-5): da verificare, da quadrare,
    verificati, spesa dell'anno; filtri per stato, chi li ha caricati e
    testo. Presidente e Tesoriere aprono la scheda per verificare e dividere.
--}}
@extends('layouts.app')

@section('title', 'Scontrini ' . $anno)

@use('App\Support\Importo')

@section('content')
    @php
        $n = fn ($stato) => (int) ($conteggi[$stato]->n ?? 0);
        $daSistemare = $n('da_verificare') + $n('da_quadrare');
    @endphp

    <x-page-header titolo="Scontrini" :evidenza="(string) $anno"
                   :sottotitolo="$puoVerificare ? 'Caricati dal telefono dai consiglieri: verifica, categorie e divisione' : 'Caricati dal telefono dal Consiglio Direttivo'">
        <x-slot:azioni>
            @if ($puoCaricare && ! $chiuso)
                <a href="{{ route('scontrini.create') }}" class="btn btn-primary"><x-icona nome="fotocamera" width="14" height="14" /> Nuovo scontrino</a>
            @endif
            @if ($puoCaricare)
                <a href="{{ route('scontrini.miei') }}" class="btn btn-secondary">I miei</a>
            @endif
            <a href="{{ route('scontrini.fornitori.index') }}" class="btn btn-secondary">Fornitori</a>
        </x-slot:azioni>
    </x-page-header>

    <div class="kpi-row">
        <x-kpi label="Da verificare" :valore="$n('da_verificare')" :variante="$n('da_verificare') ? 'warn' : null" sub="caricati, ancora da classificare"
               :href="route('scontrini.index', ['anno' => $anno, 'stato' => 'da_verificare'])" :attivo="$filtri['stato'] === 'da_verificare'" />
        <x-kpi label="Da quadrare" :valore="$n('da_quadrare')" :variante="$n('da_quadrare') ? 'ko' : null" sub="righe che non tornano al totale"
               :href="route('scontrini.index', ['anno' => $anno, 'stato' => 'da_quadrare'])" :attivo="$filtri['stato'] === 'da_quadrare'" />
        <x-kpi label="Verificati" :valore="$n('verificato')" :variante="$n('verificato') ? 'ok' : null" sub="categorie e totali a posto"
               :href="route('scontrini.index', ['anno' => $anno, 'stato' => 'verificato'])" :attivo="$filtri['stato'] === 'verificato'" />
        <x-kpi :label="'Spesa ' . $anno" :valore="Importo::it($totale, true)"
               :sub="$numero . ' ' . ($numero === 1 ? 'scontrino' : 'scontrini') . ($n('da_verificare') ? ' · ' . $n('da_verificare') . ' da classificare' : '')" />
    </div>

    @if ($daSistemare && ! $chiuso)
        <x-avviso tipo="warn" :titolo="'Anno ' . $anno . ': ' . $daSistemare . ' ' . ($daSistemare === 1 ? 'scontrino' : 'scontrini') . ' da sistemare prima della chiusura.'">
            Finché uno scontrino è da verificare o da quadrare, l'anno contabile non si chiude.
        </x-avviso>
    @endif

    <x-card senza-padding>
        <form method="GET" action="{{ route('scontrini.index') }}" class="filter-bar sn-filtri" x-data>
            @if ($anni->count() > 1)
                <div class="form-field">
                    <label class="form-label sr-only" for="f-anno">Anno</label>
                    <select id="f-anno" name="anno" class="form-select" x-on:change="$el.form.submit()">
                        @foreach ($anni as $a)<option value="{{ $a }}" @selected($a === $anno)>{{ $a }}</option>@endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="anno" value="{{ $anno }}">
            @endif
            <div class="form-field">
                <label class="form-label sr-only" for="f-stato">Stato</label>
                <select id="f-stato" name="stato" class="form-select" x-on:change="$el.form.submit()">
                    <option value="">Tutti gli stati</option>
                    @foreach (\App\Models\PrimaNotaScontrino::STATI as $v => $l)<option value="{{ $v }}" @selected($filtri['stato'] === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label sr-only" for="f-chi">Caricato da</label>
                <select id="f-chi" name="chi" class="form-select" x-on:change="$el.form.submit()">
                    <option value="">Tutti i consiglieri</option>
                    @foreach ($caricatori as $u)<option value="{{ $u->id }}" @selected($filtri['chi'] === $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div class="form-field filtro-cerca">
                <label class="form-label sr-only" for="f-q">Cerca</label>
                <input id="f-q" name="q" type="search" value="{{ $filtri['q'] }}" class="form-input" placeholder="Cerca fornitore o nota" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-secondary">Cerca</button>
        </form>

        @if ($scontrini->isEmpty())
            <x-vuoto>
                @if ($filtri['stato'] || $filtri['chi'] || $filtri['q'] !== '')
                    Nessuno scontrino corrisponde. <a href="{{ route('scontrini.index', ['anno' => $anno]) }}">Mostra tutti</a>
                @else
                    Nessuno scontrino nel {{ $anno }}.
                @endif
            </x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive sn-tabella">
                    <thead>
                        <tr>
                            <th scope="col">N.</th>
                            <th scope="col">Data</th>
                            <th scope="col">Fornitore e nota</th>
                            <th scope="col">Caricato da</th>
                            <th scope="col">Conto</th>
                            <th scope="col" class="td-num">Totale €</th>
                            <th scope="col">Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($scontrini as $s)
                            <tr class="riga-link" data-href="{{ route('scontrini.show', $s) }}">
                                <td data-label="N." class="td-solo-desktop">
                                    <span class="sn-numero">
                                        @if ($s->miniatura_path)
                                            <img src="{{ route('scontrini.anteprima', [$s, 'mini' => 1]) }}" alt="" class="sn-mini" loading="lazy">
                                        @else
                                            <span class="sn-mini"><x-icona nome="scontrino" width="14" height="14" /></span>
                                        @endif
                                        {{ $s->numero }}
                                    </span>
                                </td>
                                <td data-label="Data">{{ $s->data_scontrino->format('d/m/Y') }}</td>
                                <td class="td-primario">
                                    <a href="{{ route('scontrini.show', $s) }}">{{ $s->fornitoreNome() ?? 'Fornitore da scrivere' }}</a>
                                    <span class="td-sub">{{ $s->nota }}</span>
                                </td>
                                <td data-label="Caricato da">{{ $s->registratoDa?->name ?? '—' }}</td>
                                <td data-label="Conto">{{ $s->contoLabel() }}</td>
                                <td data-label="Totale €" class="td-num"><strong>{{ Importo::it($s->importo_totale) }}</strong></td>
                                <td data-label="Stato"><x-badge :variante="$s->statoVariante()">{{ $s->statoTesto() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-paginazione :elementi="$scontrini" nome="scontrini" />
        @endif
    </x-card>

    @if ($puoVerificare)
        <p class="form-help sc-nota">
            Lettura automatica delle foto (OCR.space):
            @if (! $ocrConfigurato)
                <strong>non configurata</strong>: manca la chiave OCR_SPACE_KEY nel file .env del server; gli scontrini si caricano lo stesso, con i dati scritti a mano.
            @elseif ($ultimaLettura)
                attiva; ultima lettura il {{ $ultimaLettura->created_at->format('d/m/Y H:i') }}: {{ strtolower(\App\Models\PrimaNotaScontrino::ESITI_OCR[$ultimaLettura->ocr_esito] ?? $ultimaLettura->ocr_esito) }}.
            @else
                attiva, nessuna lettura ancora.
            @endif
        </p>
    @endif
@endsection
