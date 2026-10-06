{{-- Elenco delle domande di adesione (tavola "Domande — elenco"). --}}
@extends('layouts.app')

@section('title', 'Domande di adesione')

@section('content')
    @php $puoModificare = auth()->user()->puoModificare('domande'); @endphp

    <x-page-header titolo="Domande di" evidenza="adesione" sottotitolo="Voto del Consiglio Direttivo, esiti e iscrizione nel libro soci" />

    <x-sola-lettura sezione="domande" />

    <div class="kpi-row">
        <x-kpi label="Da fare" :valore="$kpi['da_fare']" :variante="$kpi['da_fare'] ? 'warn' : null"
               sub="Dinieghi da inviare e parità da decidere"
               :href="route('domande.index', ['stato' => 'da_fare'])" :attivo="$filtri['stato'] === 'da_fare'" />
        <x-kpi label="In votazione" :valore="$kpi['in_votazione']" sub="Esito entro 5 giorni dalla presentazione"
               :href="route('domande.index', ['stato' => 'in_votazione'])" :attivo="$filtri['stato'] === 'in_votazione'" />
        <x-kpi label="Quota da versare" :valore="$kpi['quota']" :variante="$kpi['quota'] ? 'warn' : null" sub="Accolte, in attesa del versamento"
               :href="route('domande.index', ['stato' => 'accolte'])" :attivo="$filtri['stato'] === 'accolte'" />
        <x-kpi :label="'Nuovi soci ' . $annoCorrente" :valore="$kpi['nuovi_soci']" variante="ok" sub="Iscritti da domanda"
               :href="route('domande.index', ['stato' => 'iscritte', 'anno' => $annoCorrente])" :attivo="$filtri['stato'] === 'iscritte'" />
    </div>

    <form method="GET" action="{{ route('domande.index') }}" class="filter-bar" x-data="{ aperti: false }">
        <div class="form-field filtro-cerca">
            <label class="form-label" for="f-q">Cerca</label>
            <div class="form-input-wrap">
                <input id="f-q" name="q" type="search" value="{{ $filtri['q'] }}" class="form-input"
                       placeholder="Nome, cognome, codice fiscale…" autocomplete="off">
            </div>
        </div>

        <button type="button" class="btn btn-secondary filtri-toggle" @click="aperti = !aperti" :aria-expanded="aperti.toString()" aria-controls="filtri-extra">
            <x-icona nome="filtro" width="14" height="14" /> Filtri
        </button>

        <div id="filtri-extra" class="filtri-extra" :class="{ 'aperti': aperti }">
            <div class="form-field">
                <label class="form-label" for="f-stato">Stato</label>
                <select id="f-stato" name="stato" class="form-select">
                    @foreach ($opzioniStato as $valore => $etichetta)
                        <option value="{{ $valore }}" @selected($filtri['stato'] === $valore)>{{ $etichetta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="f-anno">Anno</label>
                <select id="f-anno" name="anno" class="form-select">
                    <option value="">Tutti</option>
                    @foreach ($anni as $anno)
                        <option value="{{ $anno }}" @selected($filtri['anno'] === (int) $anno)>{{ $anno }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Filtra</button>
    </form>

    <x-card senza-padding>
        @if ($domande->isEmpty())
            <x-vuoto>
                @if ($filtri['q'] !== '' || $filtri['anno'] || $filtri['stato'] !== 'aperte')
                    Nessuna domanda corrisponde ai filtri. <a href="{{ route('domande.index') }}">Mostra le domande aperte</a>
                @else
                    Nessuna domanda aperta. Le nuove domande arrivano dal modulo pubblico di iscrizione.
                @endif
            </x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive">
                    <thead>
                        <tr>
                            <th scope="col">Presentata</th>
                            <th scope="col">Richiedente</th>
                            <th scope="col">Categoria</th>
                            <th scope="col">Stato</th>
                            <th scope="col">Voto del CD</th>
                            <th scope="col">Prossima scadenza</th>
                            <th scope="col"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($domande as $domanda)
                            @php
                                $totale = $domanda->voti->count();
                                $scadenza = match (true) {
                                    $domanda->isInAttesa() => 'Voto entro ' . $domanda->scadenza_cd?->format('d/m H:i'),
                                    $domanda->isDaRifiutare() => 'Diniego entro ' . $domanda->scadenzaDiniego()?->format('d/m/Y'),
                                    $domanda->isInParita() => 'Decisione del Presidente',
                                    $domanda->isApprovata() && ! $domanda->socio_id => 'Quota entro ' . $domanda->quota_scadenza?->format('d/m/Y'),
                                    $domanda->isInVerifica() => 'Conferma entro ' . $domanda->token_conferma_scadenza?->format('d/m H:i'),
                                    default => null,
                                };
                                $urgente = $domanda->isDaRifiutare() || $domanda->isInParita();
                            @endphp
                            <tr>
                                <td data-label="Presentata">{{ $domanda->created_at->format('d/m/Y') }}</td>
                                <td class="td-primario">
                                    <a href="{{ route('domande.show', $domanda) }}" class="td-name">{{ $domanda->cognome }} {{ $domanda->nome }}</a>
                                    @if ($domanda->tipo_socio === 'minorenne' && $domanda->genitore_nome)
                                        <span class="td-sub">Presentata da {{ $domanda->genitore_nome }}</span>
                                    @endif
                                </td>
                                <td data-label="Categoria">{{ $domanda->tipo_socio === 'minorenne' ? 'Giovane' : 'Ordinario' }}</td>
                                <td data-label="Stato"><div class="badge-riga stretta">@include('domande._stato')</div></td>
                                <td data-label="Voto del CD">
                                    @if ($totale === 0)
                                        —
                                    @elseif ($domanda->isInAttesa())
                                        {{ $domanda->votiEspressi() }} di {{ $totale }} voti
                                    @else
                                        {{ $domanda->votiFavorevoli() }} fav. · {{ $domanda->votiContrari() }} contr.
                                    @endif
                                </td>
                                <td data-label="Scadenza">
                                    @if ($scadenza)
                                        <span @class(['testo-urgente' => $urgente])>{{ $scadenza }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="td-azioni">
                                    <a href="{{ route('domande.show', $domanda) }}" class="action-btn" aria-label="Apri la domanda di {{ $domanda->nome }} {{ $domanda->cognome }}"><x-icona nome="avanti" /></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-paginazione :elementi="$domande" nome="domande" />
        @endif
    </x-card>

    <x-card titolo="Promozione ottobre–dicembre">
        <div class="zona-pericolo">
            <p>Chi è accolto da ottobre a dicembre versa la quota dell'anno dopo: diventa socio subito e la quota vale fino al 31 dicembre dell'anno successivo. Si può cambiare per la singola domanda al momento dell'iscrizione.</p>
            <div class="riga-azioni">
                <x-badge :variante="$promozione ? 'ok' : 'neutral'">{{ $promozione ? 'Attiva' : 'Non attiva' }}</x-badge>
                @if ($puoModificare)
                    <form method="POST" action="{{ route('domande.promozione') }}">
                        @csrf
                        <input type="hidden" name="attiva" value="{{ $promozione ? 0 : 1 }}">
                        <button type="submit" class="btn btn-secondary btn-sm">{{ $promozione ? 'Disattiva' : 'Attiva' }}</button>
                    </form>
                @endif
            </div>
        </div>
    </x-card>
@endsection
