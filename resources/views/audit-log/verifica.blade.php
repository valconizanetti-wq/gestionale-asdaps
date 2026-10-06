{{--
    Audit Log — verifica di integrità, certificati, attestazioni tecniche
    (tavola "Audit Log 3"; audit-log.md §9). La verifica è in sola lettura
    sul registro: ricalcola tutta la catena dalla prima riga e mostra le
    anomalie dell'anno scelto. Certificato e attestazione: Presidente e,
    se c'è, Revisore.
--}}
@extends('layouts.app')

@section('title', 'Verifica di integrità')

@section('content')
    @php
        $ultimaIl = $ultima ? \Carbon\Carbon::parse($ultima['il']) : null;
    @endphp

    <x-page-header titolo="Verifica" evidenza="integrità" sottotitolo="Catena SHA-256 del registro, certificati annuali e attestazioni tecniche"
                   :indietro="route('audit-log.index', ['anno' => $anno])" indietro-label="Audit Log" />

    <x-avviso tipo="info">Ogni riga del registro contiene l'impronta della precedente: chi togliesse, modificasse o inserisse una riga a mano romperebbe la catena. La verifica la ricalcola tutta, dalla prima riga, e indica dove si rompe. Ogni mese la fa da sola e avvisa il Presidente se trova anomalie.</x-avviso>

    <x-card titolo="Verifica">
        <form method="POST" action="{{ route('audit-log.verifica.esegui') }}" class="audit-verifica-form" x-data="{ inviato: false }" x-on:submit="inviato = true">
            @csrf
            <div class="form-field">
                <label class="form-label" for="v-anno">Anno</label>
                <select id="v-anno" name="anno" class="form-select">
                    @foreach ($anni as $a)
                        <option value="{{ $a }}" @selected($anno === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Verifica adesso</button>
            <p class="form-help">
                Solo lettura: la verifica non modifica il registro.
                @if ($ultima)
                    Ultima verifica ({{ $ultima['modo'] }}): {{ $ultimaIl->format('d/m/Y') }} alle {{ $ultimaIl->format('H:i') }},
                    {{ $ultima['anomalie'] ? $ultima['anomalie'] . ' anomalie' : 'nessuna anomalia' }}.
                @endif
            </p>
        </form>
    </x-card>

    @if ($esito)
        @php
            $r = $esito['riepilogo'];
            $integra = $esito['anomalie_anno'] === 0 && $esito['anomalie_totali'] === 0;
        @endphp
        <x-card :titolo="'Risultato · anno ' . $esito['anno']">
            <div class="audit-risultato">
                @if (! $r)
                    <x-avviso tipo="info">Nessuna riga nel registro per il {{ $esito['anno'] }}. Righe controllate in tutto il registro: {{ number_format($esito['righe_totali'], 0, ',', '.') }}, {{ $esito['anomalie_totali'] ? $esito['anomalie_totali'] . ' anomalie' : 'nessuna anomalia' }}.</x-avviso>
                @elseif ($integra)
                    <x-avviso tipo="ok"><strong>Catena integra.</strong> {{ number_format($r['totale'], 0, ',', '.') }} righe del {{ $esito['anno'] }} controllate; la catena è ricalcolata dalla prima riga del registro.</x-avviso>
                @else
                    <x-avviso tipo="ko">
                        <strong>{{ $esito['anomalie_anno'] }} {{ $esito['anomalie_anno'] === 1 ? 'anomalia' : 'anomalie' }} su {{ number_format($r['totale'], 0, ',', '.') }} righe del {{ $esito['anno'] }}</strong>
                        @if ($esito['anomalie_anno'] !== $esito['anomalie_totali'])
                            ({{ $esito['anomalie_totali'] }} in tutto il registro)
                        @endif
                        <br>Il certificato non si può generare finché le anomalie non sono spiegate.
                    </x-avviso>
                @endif

                @if ($r)
                    <dl class="audit-risultato-dati">
                        <x-dato label="Righe dell'anno" :valore="number_format($r['totale'], 0, ',', '.') . ' (dalla n. ' . $r['prima_riga'] . ' alla n. ' . $r['ultima_riga'] . ')'" />
                        <x-dato label="Righe controllate" :valore="number_format($esito['righe_totali'], 0, ',', '.') . ' in tutto il registro'" />
                        <x-dato label="Anomalie" :valore="$esito['anomalie_anno']" />
                        <x-dato label="Impronta dell'ultima riga dell'anno" :valore="$r['hash_ultima'] ? substr($r['hash_ultima'], 0, 20) . '…' : '—'" codice />
                    </dl>
                @endif

                @if ($esito['anomalie'])
                    <div class="table-wrap">
                        <table class="data-table responsive">
                            <thead><tr><th scope="col">Riga</th><th scope="col">Data</th><th scope="col">Evento</th><th scope="col">Operatore</th><th scope="col">Tipo di anomalia</th></tr></thead>
                            <tbody>
                                @foreach ($esito['anomalie'] as $a)
                                    <tr>
                                        <td class="td-primario"><span class="code">n. {{ $a['id'] }}</span></td>
                                        <td data-label="Data">{{ \Carbon\Carbon::parse($a['created_at'])->format('d/m/Y H:i') }}</td>
                                        <td data-label="Evento"><span class="code">{{ $a['evento'] }}</span></td>
                                        <td data-label="Operatore">{{ $a['utente_nome'] }}</td>
                                        <td data-label="Tipo">{{ $a['tipo'] === 'collegamento' ? 'Collegamento: la riga precedente non corrisponde (riga inserita, tolta o spostata?)' : 'Contenuto: l\'impronta non si ricalcola dalla formula' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="form-help">Nessuna correzione automatica. Se un'anomalia ha una spiegazione nota (per esempio una manutenzione fatta a mano), si documenta con un'attestazione tecnica firmata.</p>
                @endif

                @if ($r && $integra && $puoCertificare)
                    <form method="POST" action="{{ route('audit-log.certificati.genera') }}" class="audit-certifica" x-data="{ inviato: false }" x-on:submit="inviato = true">
                        @csrf
                        <input type="hidden" name="anno" value="{{ $esito['anno'] }}">
                        <p class="form-help">Il certificato fotografa la catena di oggi: righe per livello di rischio, impronta dell'ultima riga e codice univoco del documento.</p>
                        <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Genera il certificato {{ $esito['anno'] }}</button>
                    </form>
                @endif
            </div>
        </x-card>
    @endif

    <div class="audit-colonne">
        <x-card titolo="Certificati di integrità" senza-padding>
            @if ($certificati->isEmpty())
                <x-vuoto>Nessun certificato generato. Si genera dopo una verifica senza anomalie.</x-vuoto>
            @else
                <div class="table-wrap">
                    <table class="data-table responsive">
                        {{-- Quattro colonne, senza scorrimento: impronta e righe sotto l'anno e la data. --}}
                        <thead><tr><th scope="col">Anno</th><th scope="col">Generato</th><th scope="col">Da</th><th scope="col"><span class="sr-only">Azioni</span></th></tr></thead>
                        <tbody>
                            @foreach ($certificati as $c)
                                @php
                                    // generato_da = "Nome Cognome (Ruolo)"
                                    preg_match('/^(.*?)\s*\(([^()]*)\)$/', (string) $c->generato_da, $chi);
                                @endphp
                                <tr>
                                    <td class="td-primario audit-cert">
                                        <span>{{ $c->anno }}</span>
                                        <span class="td-sub code" title="Impronta dell'ultima riga dell'anno">{{ substr($c->hash_chain, 0, 10) }}…</span>
                                    </td>
                                    <td data-label="Generato" class="audit-cert">
                                        <span class="td-pila">
                                            <span>{{ $c->computed_at->format('d/m/Y H:i') }}</span>
                                            <span class="td-sub">{{ number_format($c->records_count, 0, ',', '.') }} righe</span>
                                        </span>
                                    </td>
                                    <td data-label="Da" class="audit-chi">
                                        <span class="td-pila">
                                            <span>{{ $chi[1] ?? $c->generato_da }}</span>
                                            @if (! empty($chi[2]))
                                                <span class="td-sub">{{ $chi[2] }}</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="td-azioni">
                                        @if ($c->uuid_documento)
                                            <a href="{{ route('audit-log.certificati.scarica', $c) }}" class="btn btn-ghost btn-sm"><x-icona nome="download" width="14" height="14" /> PDF</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card titolo="Attestazioni tecniche" senza-padding>
            @if ($puoCertificare)
                <x-slot:azioni>
                    <button type="button" class="btn btn-secondary btn-sm" @click="$dispatch('apri-modale', 'attestazione')"><x-icona nome="piu" width="14" height="14" /> Nuova attestazione</button>
                </x-slot:azioni>
            @endif
            @if ($attestazioni->isEmpty())
                <x-vuoto>Nessuna attestazione caricata. {{ $eccezioni ? '' : 'Oggi non ci sono eccezioni da documentare.' }}</x-vuoto>
            @else
                <div class="table-wrap">
                    <table class="data-table responsive">
                        <thead><tr><th scope="col">Firmata il</th><th scope="col">Oggetto</th><th scope="col"><span class="sr-only">Azioni</span></th></tr></thead>
                        <tbody>
                            @foreach ($attestazioni as $att)
                                <tr>
                                    <td class="td-primario audit-chi">
                                        <span>{{ \Carbon\Carbon::parse($att->data_firma)->format('d/m/Y') }}</span>
                                        <span class="td-sub">{{ $att->caricato_da }}</span>
                                    </td>
                                    <td data-label="Oggetto">
                                        <span class="td-pila">
                                            <span>{{ $att->oggetto }}</span>
                                            <span class="td-sub">{{ $att->anomalie_count ? $att->anomalie_count . ' anomalie alla firma' : 'Nessuna anomalia alla firma' }}</span>
                                        </span>
                                    </td>
                                    <td class="td-azioni">
                                        <a href="{{ route('audit-log.attestazioni.scarica', $att->id) }}" class="btn btn-ghost btn-sm"><x-icona nome="download" width="14" height="14" /> Firmata</a>
                                        @if ($att->relazione_path)
                                            <a href="{{ route('audit-log.attestazioni.scarica', [$att->id, 'relazione']) }}" class="btn btn-ghost btn-sm"><x-icona nome="download" width="14" height="14" /> Relazione</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection

@push('modals')
    @if ($puoCertificare)
        <x-modale nome="attestazione" titolo="Nuova attestazione tecnica">
            <ol class="audit-passi">
                <li>Scarica la bozza: riporta i numeri della verifica di oggi e le eccezioni note.</li>
                <li>Firmala con firma digitale o CIE, fuori dal gestionale (PDF firmato o file .p7m).</li>
                <li>Carica qui il file firmato, con l'eventuale relazione tecnica.</li>
            </ol>
            <a href="{{ route('audit-log.attestazioni.bozza') }}" class="btn btn-secondary btn-sm audit-bozza"><x-icona nome="download" width="14" height="14" /> Scarica la bozza (PDF)</a>
            <form method="POST" action="{{ route('audit-log.attestazioni.carica') }}" enctype="multipart/form-data" id="form-attestazione" class="form-modale">
                @csrf
                <x-campo name="oggetto" label="Oggetto" required maxlength="255" :value="'Attestazione tecnica sullo stato del registro delle operazioni al ' . now()->format('d/m/Y')" />
                <x-campo name="data_firma" label="Data della firma" tipo="date" required :value="now()" />
                <x-campo name="note" label="Note" tipo="textarea" rows="2" maxlength="2000" />
                <x-campo name="file" label="Attestazione firmata" tipo="file" required accept=".pdf,.p7m,application/pdf" aiuto="PDF con firma digitale o file .p7m, massimo 20 MB. Si controlla che la firma ci sia, non la validità del certificato." />
                <x-campo name="relazione" label="Relazione tecnica" tipo="file" accept=".pdf,.doc,.docx" aiuto="Facoltativa: PDF o Word, massimo 20 MB." />
            </form>
            <x-slot:piede>
                <button type="button" class="btn btn-ghost" @click="aperta = false">Annulla</button>
                <button type="submit" form="form-attestazione" class="btn btn-primary">Carica</button>
            </x-slot:piede>
        </x-modale>
    @endif
@endpush

@if ($errors->hasAny(['oggetto', 'data_firma', 'note', 'file', 'relazione']))
    @push('scripts')
        <script>document.addEventListener('alpine:initialized', () => window.dispatchEvent(new CustomEvent('apri-modale', { detail: 'attestazione' })));</script>
    @endpush
@endif
