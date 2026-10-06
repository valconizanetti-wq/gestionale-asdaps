{{--
    Audit Log — elenco (tavole "Audit Log 1–2"; audit-log.md §9). Fascia
    sullo stato della catena (ultima verifica salvata e certificato
    dell'anno, senza ricalcolare a ogni apertura), indicatori, filtri,
    righe che si aprono con i valori prima e dopo.
--}}
@extends('layouts.app')

@section('title', 'Audit Log')

@section('content')
    @use('App\Services\AuditConsultazione', 'C')
    @php
        $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
        $verifica = $stato['verifica'];
        $verificaIl = $verifica ? \Carbon\Carbon::parse($verifica['il']) : null;
        $cert = $stato['certificato'];
        $query = request()->query();
    @endphp

    <x-page-header titolo="Audit" evidenza="Log" sottotitolo="Registro delle operazioni, con catena di integrità SHA-256">
        <x-slot:azioni>
            {{-- Esportano le righe filtrate: senza righe i pulsanti sono spenti. --}}
            @if ($righe->total() > 0)
                <a href="{{ route('audit-log.export-csv', $query) }}" class="btn btn-secondary"><x-icona nome="download" width="14" height="14" /> CSV</a>
                <a href="{{ route('audit-log.export-pdf', $query) }}" class="btn btn-secondary solo-desktop"><x-icona nome="download" width="14" height="14" /> PDF</a>
            @else
                <button type="button" class="btn btn-secondary" disabled title="Nessuna riga da esportare con questi filtri"><x-icona nome="download" width="14" height="14" /> CSV</button>
                <button type="button" class="btn btn-secondary solo-desktop" disabled title="Nessuna riga da esportare con questi filtri"><x-icona nome="download" width="14" height="14" /> PDF</button>
            @endif
            <a href="{{ route('audit-log.verifica', ['anno' => $filtri['anno']]) }}" class="btn btn-primary">Verifica integrità</a>
        </x-slot:azioni>
    </x-page-header>

    <div @class(['audit-fascia', $stato['stato']]) role="status">
        <div class="audit-fascia-testo">
            @if ($stato['stato'] === 'anomalia')
                <strong>Attenzione: la catena risulta alterata</strong>
                <span>Verifica del {{ $verificaIl->format('d/m/Y') }} alle {{ $verificaIl->format('H:i') }}: {{ $verifica['anomalie'] }} {{ $verifica['anomalie'] === 1 ? 'riga anomala' : 'righe anomale' }} (dalla n. {{ $verifica['primo_anomalo'] }}). Apri la verifica per il dettaglio.</span>
            @elseif ($stato['stato'] === 'certificato')
                <strong>Registro integro e certificato</strong>
                <span>
                    Anno {{ $filtri['anno'] }}: certificato del {{ $cert->computed_at->format('d/m/Y') }} alle {{ $cert->computed_at->format('H:i') }} · {{ number_format($cert->records_count, 0, ',', '.') }} righe.
                    @if ($verifica)
                        Ultima verifica ({{ $verifica['modo'] }}) del {{ $verificaIl->format('d/m/Y') }}: nessuna anomalia.
                    @endif
                </span>
            @else
                <strong>Certificato dell'anno non ancora generato</strong>
                <span>
                    @if ($verifica)
                        Ultima verifica ({{ $verifica['modo'] }}) del {{ $verificaIl->format('d/m/Y') }} alle {{ $verificaIl->format('H:i') }}: catena integra.
                    @else
                        La catena non è ancora stata verificata.
                    @endif
                    Il certificato si genera dalla pagina di verifica.
                </span>
            @endif
        </div>
        <div class="audit-fascia-azioni">
            @if ($hashUltima)
                <span class="code audit-hash" title="Impronta dell'ultima riga dell'anno">SHA-256 {{ substr($hashUltima, 0, 12) }}…</span>
            @endif
            <a href="{{ route('audit-log.verifica', ['anno' => $filtri['anno']]) }}" class="btn btn-secondary btn-sm">Vai alla verifica</a>
        </div>
    </div>

    <div class="kpi-row kpi-row-5">
        <x-kpi label="Operazioni" :valore="number_format($stat['totale'], 0, ',', '.')" :sub="'Anno ' . $filtri['anno']" />
        <x-kpi label="Rischio alto" :valore="$stat['HIGH']" :variante="$stat['HIGH'] ? 'ko' : null" sub="Ruoli, password, IBAN, codici fiscali"
               :href="route('audit-log.index', ['anno' => $filtri['anno'], 'rischio' => 'HIGH'])" :attivo="$filtri['rischio'] === 'HIGH'" />
        <x-kpi label="Rischio medio" :valore="$stat['MEDIUM']" :variante="$stat['MEDIUM'] ? 'warn' : null" sub="Stati, importi, soci, esportazioni"
               :href="route('audit-log.index', ['anno' => $filtri['anno'], 'rischio' => 'MEDIUM'])" :attivo="$filtri['rischio'] === 'MEDIUM'" />
        <x-kpi label="Rischio basso" :valore="$stat['LOW']" sub="Operazioni ordinarie"
               :href="route('audit-log.index', ['anno' => $filtri['anno'], 'rischio' => 'LOW'])" :attivo="$filtri['rischio'] === 'LOW'" />
        <x-kpi label="Operatori" :valore="$stat['operatori']" sub="Più «sistema» per i comandi e i link" />
    </div>

    <form method="GET" action="{{ route('audit-log.index') }}" class="filter-bar" x-data="{ aperti: false }">
        <div class="form-field filtro-cerca">
            <label class="form-label" for="f-cerca">Cerca</label>
            <div class="form-input-wrap">
                <input id="f-cerca" name="cerca" type="search" value="{{ $filtri['cerca'] }}" class="form-input" placeholder="Riepilogo o numero" autocomplete="off">
            </div>
        </div>

        <button type="button" class="btn btn-secondary filtri-toggle" @click="aperti = !aperti" :aria-expanded="aperti.toString()" aria-controls="filtri-extra">
            <x-icona nome="filtro" width="14" height="14" /> Filtri
        </button>

        <div id="filtri-extra" class="filtri-extra" :class="{ 'aperti': aperti }">
            <div class="form-field">
                <label class="form-label" for="f-anno">Anno</label>
                <select id="f-anno" name="anno" class="form-select">
                    @foreach ($anni as $a)
                        <option value="{{ $a }}" @selected($filtri['anno'] === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="f-mese">Mese</label>
                <select id="f-mese" name="mese" class="form-select">
                    <option value="">Tutti</option>
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected($filtri['mese'] === $m)>{{ $mesi[$m] }}</option>
                    @endfor
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="f-modello">Modulo</label>
                <select id="f-modello" name="modello" class="form-select">
                    <option value="">Tutti</option>
                    @foreach ($opzioni['modelli'] as $codice => $label)
                        <option value="{{ $codice }}" @selected($filtri['modello'] === $codice)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="f-rischio">Rischio</label>
                <select id="f-rischio" name="rischio" class="form-select">
                    <option value="">Tutti</option>
                    @foreach (config('audit.rischi') as $codice => [$label])
                        <option value="{{ $codice }}" @selected($filtri['rischio'] === $codice)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="f-evento">Evento</label>
                <select id="f-evento" name="evento" class="form-select">
                    <option value="">Tutti</option>
                    @foreach ($opzioni['eventi'] as $codice => $label)
                        <option value="{{ $codice }}" @selected($filtri['evento'] === $codice)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="f-utente">Operatore</label>
                <select id="f-utente" name="utente" class="form-select">
                    <option value="">Tutti</option>
                    @foreach ($opzioni['operatori'] as $nome)
                        <option value="{{ $nome }}" @selected($filtri['utente'] === $nome)>{{ $nome }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Filtra</button>
    </form>

    {{-- Prima apertura da telefono senza una scelta salvata: 10 righe per pagina. --}}
    @unless ($perPaginaScelta)
        <div x-data x-init="if (window.innerWidth <= 768 && ! new URL(location.href).searchParams.has('per_pagina')) { const u = new URL(location.href); u.searchParams.set('per_pagina', '10'); location.replace(u.toString()) }"></div>
    @endunless

    <x-card titolo="Registro delle operazioni" senza-padding>
        @if ($filtriAttivi)
            <x-slot:azioni>
                <a href="{{ route('audit-log.index', ['anno' => $filtri['anno']]) }}" class="btn btn-ghost btn-sm">Togli i filtri</a>
            </x-slot:azioni>
        @endif
        @if ($righe->isEmpty())
            <x-vuoto>{{ $filtriAttivi ? 'Nessuna operazione corrisponde ai filtri.' : 'Nessuna operazione registrata nel ' . $filtri['anno'] . '.' }}</x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive audit-tabella" x-data="{ aperta: null }">
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Dettaglio</span></th>
                            <th scope="col">N.</th>
                            <th scope="col">Data e ora</th>
                            <th scope="col">Modulo</th>
                            <th scope="col">Operazione</th>
                            <th scope="col">Rischio</th>
                            <th scope="col">Riepilogo</th>
                            <th scope="col">Operatore</th>
                        </tr>
                    </thead>
                    @foreach ($righe as $r)
                        @php
                            $quando = \Carbon\Carbon::parse($r->created_at);
                            [$rischio, $variante] = C::rischio($r->livello_rischio);
                            $prima = $r->old_values ? (json_decode($r->old_values, true) ?: []) : [];
                            $dopo = $r->new_values ? (json_decode($r->new_values, true) ?: []) : [];
                            $campi = $r->campi_modificati ? (json_decode($r->campi_modificati, true) ?: []) : [];
                            $chiavi = array_values(array_unique(array_merge(array_keys($prima), array_keys($dopo))));
                        @endphp
                        {{-- Una riga aperta alla volta: aprirne un'altra chiude la precedente. --}}
                        <tbody>
                            <tr :class="{ 'audit-aperta': aperta === {{ $r->id }} }">
                                <td class="td-azioni audit-apri">
                                    <button type="button" class="action-btn" @click="aperta = aperta === {{ $r->id }} ? null : {{ $r->id }}" :aria-expanded="(aperta === {{ $r->id }}).toString()"
                                            aria-controls="audit-dett-{{ $r->id }}" aria-label="Dettaglio della riga n. {{ $r->id }}">
                                        <x-icona nome="giu" class="audit-freccia" />
                                    </button>
                                </td>
                                <td class="td-solo-desktop"><span class="code audit-numero">{{ $r->id }}</span></td>
                                <td class="td-primario audit-quando">
                                    <span class="code audit-numero solo-mobile">n. {{ $r->id }}</span>
                                    <span>{{ $quando->format('d/m/Y') }}</span>
                                    <span class="td-sub">{{ $quando->format('H:i:s') }}</span>
                                </td>
                                <td data-label="Modulo"><span>{{ C::modello($r->modello ?? $r->entita_tipo) }} <span class="code">{{ $r->entita_id ? '#' . $r->entita_id : '' }}</span></span></td>
                                <td data-label="Operazione">{{ C::operazione($r) }}</td>
                                <td data-label="Rischio"><x-badge :variante="$variante">{{ $rischio }}</x-badge></td>
                                <td data-label="Riepilogo" class="audit-riepilogo">{{ $r->dettaglio ? \Illuminate\Support\Str::limit($r->dettaglio, 110) : '—' }}</td>
                                <td data-label="Operatore">{{ $r->utente_nome }}</td>
                            </tr>
                            <tr class="audit-dettaglio" id="audit-dett-{{ $r->id }}" x-show="aperta === {{ $r->id }}" x-cloak>
                                <td colspan="8">
                                    @if ($chiavi)
                                        <div class="audit-diff">
                                            @foreach (['Prima' => $prima, 'Dopo' => $dopo] as $titolo => $valori)
                                                <div class="audit-diff-col">
                                                    <span class="audit-diff-titolo">{{ $titolo }}</span>
                                                    @foreach ($chiavi as $k)
                                                        <div @class(['audit-diff-riga', 'cambiato' => in_array($k, $campi, true), 'nuovo' => $titolo === 'Dopo'])>
                                                            <span class="code">{{ $k }}</span>
                                                            <span class="audit-diff-valore">{{ C::valore($valori[$k] ?? null) }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif ($campi)
                                        <p class="testo-secondario">Campi modificati: {{ implode(', ', $campi) }} (valori non registrati per questo modulo).</p>
                                    @endif
                                    <dl class="audit-meta">
                                        <x-dato label="Riga" :valore="'n. ' . $r->id" codice />
                                        <x-dato label="Evento" :valore="$r->evento" codice />
                                        <x-dato label="Indirizzo IP" :valore="$r->ip_address" codice />
                                        <x-dato label="Hash" :valore="$r->hash ? substr($r->hash, 0, 16) . '…' : 'non presente'" codice />
                                    </dl>
                                    @if ($r->dettaglio)
                                        <p class="audit-dettaglio-testo">{{ $r->dettaglio }}</p>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    @endforeach
                </table>
            </div>
            <x-paginazione :elementi="$righe" nome="operazioni" :scelte="$scelte" :scelta="$perPagina" />
        @endif
    </x-card>
@endsection
