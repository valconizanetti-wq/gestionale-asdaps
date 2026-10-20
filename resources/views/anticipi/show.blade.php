{{--
    Scheda della nota di rimborso (tavola AN-4) e della rinuncia (AN-4b).
    Tre passi: nota, firma (si carica la copia firmata), pagamento o
    registrazione della donazione. Il conto del pagamento è quello scritto
    nella nota (E3). Pagata: «Annulla il pagamento»; emessa o firmata:
    «Annulla la nota» (T6). Annullata: fascia grigia con motivo, chi e quando.
    Le spese si mostrano dalla fotografia della nota (le stesse del PDF).
    Variabili: $n, $movimenti (per id), $donazione, $chiusoPagamento.
--}}
@extends('layouts.app')

@use('App\Support\Importo')
@use('App\Models\PrimaNota')

@section('title', $n->etichetta() . ' ' . $n->numero)

@section('content')
    @php
        $puoModificare = auth()->user()->puoModificare('prima_nota');
        $rinuncia = $n->isRinuncia();
        $righe = collect($n->dati['righe'] ?? []);
        $socioDati = $n->dati['socio'] ?? [];
        $visto = $n->dati['visto'] ?? null;
        $nomi = [
            ($rinuncia ? 'Dichiarazione' : 'Nota') . ' · ' . $n->data_nota->format('d/m/Y'),
            'Firma' . ($n->firmata_il ? ' · caricata il ' . $n->firmata_il->format('d/m/Y') : ''),
            ($rinuncia ? 'Registrazione' : 'Pagamento') . ($n->pagata_il ? ' · ' . $n->pagata_il->format('d/m/Y') : ''),
        ];
        $contoPrevisto = $n->contoPrevisto();
    @endphp

    <x-page-header :titolo="$n->etichetta()" :evidenza="$n->numero"
                   :sottotitolo="$n->socioNome() . ' · ' . \App\Services\NoteRimborso::spese($n->numero_spese) . ' · ' . Importo::it($n->totale, true) . ($rinuncia ? ' · donazione alla Compagnia' : '')"
                   :indietro="route('prima-nota.anticipi.index', ['anno' => $n->anno])" indietro-label="Anticipi e note di rimborso">
        <div class="badge-riga">
            <x-badge :variante="$n->statoVariante()">{{ $n->statoTesto() }}</x-badge>
        </div>
        <x-slot:azioni>
            <a href="{{ route('prima-nota.anticipi.pdf', $n) }}" target="_blank" rel="noopener" class="btn btn-secondary">
                <x-icona nome="download" width="14" height="14" /> {{ $rinuncia ? 'PDF della rinuncia' : 'PDF della nota' }}
            </a>
            @if ($puoModificare && $n->annullabile())
                <button type="button" class="btn btn-danger-outline" x-data x-on:click="$dispatch('apri-modale', 'annulla-nota')">{{ $rinuncia ? 'Annulla' : 'Annulla la nota' }}</button>
            @endif
        </x-slot:azioni>
    </x-page-header>

    <x-sola-lettura sezione="prima_nota" />

    @foreach (['firmata', 'pagamento'] as $bag)
        @if ($errors->$bag->any())
            <x-avviso tipo="ko" titolo="Operazione non eseguita.">{{ $errors->$bag->first() }}</x-avviso>
        @endif
    @endforeach

    @if ($n->isAnnullata())
        <x-avviso tipo="info" titolo="Annullata il {{ $n->annullata_il->format('d/m/Y \a\l\l\e H:i') }}{{ $n->annullataDa ? ' da ' . $n->annullataDa->name : '' }}.">
            Motivo: {{ $n->motivo_annullamento }}. Le spese sono tornate tra quelle senza nota; il PDF resta consultabile con la scritta ANNULLATA e il numero non si riusa.
        </x-avviso>
    @else
        <x-passi :corrente="$n->passo()" :nomi="$nomi" etichetta="Passi della nota" class="an-passi" />
    @endif

    <div class="pn-colonne">
        <div class="pn-colonna">
            @if ($n->stato === 'emessa')
                <x-card titolo="2 · Firma">
                    <p class="form-intro">
                        Stampa il <a href="{{ route('prima-nota.anticipi.pdf', $n) }}" target="_blank" rel="noopener">PDF</a> e fallo firmare a {{ $n->socioNome() }}@if ($visto) e, per il visto, a {{ $visto['carica'] }}@endif.
                        @if (! $rinuncia && $n->modalita === 'cassa') Con i contanti il socio firma anche la riga «Per quietanza» quando riceve i soldi. @endif
                        Poi carica la scansione o la foto: diventa il giustificativo {{ $rinuncia ? 'della donazione' : 'del pagamento' }}.
                    </p>
                    @if ($puoModificare)
                        <form method="POST" action="{{ route('prima-nota.anticipi.firmata', $n) }}" enctype="multipart/form-data" class="form-modale form-riga"
                              x-data="{ inviato: false }" x-on:submit="inviato = true">
                            @csrf
                            <div @class(['form-field', 'has-error' => $errors->firmata->has('firmata')])>
                                <label class="form-label" for="firmata">Copia firmata<span class="obbligatorio" aria-hidden="true">*</span></label>
                                <input id="firmata" name="firmata" type="file" class="form-input" accept="image/jpeg,image/png,application/pdf,.pdf" required aria-describedby="firmata-aiuto">
                                <div class="form-help" id="firmata-aiuto">PDF o foto (JPG, PNG), fino a 8 MB. Dal telefono si può scattare una foto.</div>
                                @foreach ($errors->firmata->get('firmata') as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                            </div>
                            <div><button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Carica la nota firmata</button></div>
                        </form>
                    @endif
                </x-card>
            @elseif ($n->stato === 'firmata')
                <x-card :titolo="$rinuncia ? '3 · Registra la donazione' : '3 · Pagamento'">
                    @if ($puoModificare)
                        @php $campo = $rinuncia ? 'data_rinuncia' : 'data_pagamento'; @endphp
                        <form method="POST" action="{{ route('prima-nota.anticipi.pagamento', $n) }}" class="form-modale" x-data="{ inviato: false }" x-on:submit="inviato = true">
                            @csrf
                            <div @class(['form-field', 'has-error' => $errors->pagamento->has($campo)])>
                                <label class="form-label" for="{{ $campo }}">{{ $rinuncia ? 'Data' : 'Data del pagamento' }}<span class="obbligatorio" aria-hidden="true">*</span></label>
                                <input id="{{ $campo }}" name="{{ $campo }}" type="date" class="form-input" required
                                       min="{{ $n->data_nota->toDateString() }}" max="{{ today()->toDateString() }}"
                                       value="{{ $errors->pagamento->any() ? old($campo) : today()->toDateString() }}">
                                @foreach ($errors->pagamento->get($campo) as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                            </div>
                            @if ($rinuncia)
                                <p class="form-help">Si registrano due movimenti in <strong>Cassa</strong>, nella stessa data, collegati alla rinuncia: il saldo non cambia.</p>
                                <div class="table-wrap">
                                    <table class="data-table responsive">
                                        <thead><tr><th scope="col">Movimento</th><th scope="col">Categoria</th><th scope="col" class="td-num">€</th></tr></thead>
                                        <tbody>
                                            @foreach ($righe as $r)
                                                <tr>
                                                    <td data-label="Movimento">Uscita · {{ $r['descrizione'] }}</td>
                                                    <td data-label="Categoria">{{ $r['categoria'] }}</td>
                                                    <td data-label="€" class="td-num"><span class="importo-uscita">− {{ Importo::it($r['importo']) }}</span></td>
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td data-label="Movimento">Entrata · Donazione di {{ trim(($socioDati['nome'] ?? '') . ' ' . ($socioDati['cognome'] ?? '')) }} (rinuncia {{ $n->numero }})</td>
                                                <td data-label="Categoria">Contributi e donazioni da terzi</td>
                                                <td data-label="€" class="td-num"><span class="importo-entrata">+ {{ Importo::it($n->totale) }}</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <fieldset class="scelta-gruppo">
                                    <legend>Pagato da</legend>
                                    <div class="scelta-opzioni">
                                        @foreach (['cassa' => 'Contanti', 'banca' => 'Bonifico all\'IBAN della nota'] as $c => $sub)
                                            <label @class(['scelta', 'spenta' => $c !== $contoPrevisto])>
                                                <input type="radio" name="conto_visto" value="{{ $c }}" @checked($c === $contoPrevisto) @disabled($c !== $contoPrevisto) x-on:click.prevent>
                                                <span class="scelta-testo">{{ PrimaNota::CONTI[$c] }} <span class="scelta-sub">{{ $sub }}</span></span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                <p class="form-help">
                                    {{ $n->numero_spese === 1 ? 'La spesa entra' : 'Le spese entrano' }} in Prima Nota con la data del pagamento e il conto scritto nella nota firmata; la data dello scontrino resta come data della spesa.
                                    La data non può venire prima della nota, né cadere in un anno chiuso. Per un altro conto: annulla la nota e preparane un'altra.
                                </p>
                            @endif
                            <div class="form-actions form-actions-fine">
                                <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">{{ $rinuncia ? 'Registra la donazione' : 'Registra il pagamento' }}</button>
                            </div>
                        </form>
                    @else
                        <p class="form-help">Il pagamento lo registrano il Presidente o il Tesoriere.</p>
                    @endif
                </x-card>
            @elseif ($n->isPagata())
                <x-card :titolo="$rinuncia ? 'Donazione registrata' : 'Pagamento'">
                    <p>
                        @if ($rinuncia)
                            Registrata il <strong>{{ $n->pagata_il->format('d/m/Y') }}</strong> in cassa: le spese e l'entrata della donazione sono in Prima Nota, il saldo non cambia.
                        @else
                            Pagata il <strong>{{ $n->pagata_il->format('d/m/Y') }}</strong> dalla <strong>{{ mb_strtolower(PrimaNota::CONTI[$n->conto] ?? '') }}</strong>: le spese sono in Prima Nota con questa data.
                        @endif
                        <span class="form-help">Registrato il {{ $n->pagamento_registrato_il?->format('d/m/Y \a\l\l\e H:i') }}{{ $n->pagataDa ? ' da ' . $n->pagataDa->name : '' }}.</span>
                    </p>
                    @if ($donazione)
                        <p><a href="{{ route('prima-nota.show', $donazione) }}">Entrata della donazione: movimento n. {{ $donazione->id }}</a></p>
                    @endif
                    @if ($puoModificare)
                        <div class="form-riga">
                            <button type="button" class="btn btn-danger-outline" x-data x-on:click="$dispatch('apri-modale', 'annulla-pagamento')">{{ $rinuncia ? 'Annulla la registrazione' : 'Annulla il pagamento' }}</button>
                            <p class="form-help">Solo per un errore: motivo obbligatorio, rischio alto nell'Audit Log; le spese escono dai saldi e la nota torna «firmata».</p>
                        </div>
                    @endif
                </x-card>
            @endif
        </div>

        <aside class="pn-colonna">
            <x-card :titolo="$rinuncia ? 'Dati della rinuncia' : 'Dati della nota'">
                <dl class="dati-griglia">
                    <x-dato label="Socio">
                        @if ($n->socio && ! $n->socio->trashed() && auth()->user()->puoAccedere('soci'))
                            <a href="{{ route('soci.show', $n->socio) }}">{{ $n->socioNome() }}</a>
                        @else
                            {{ $n->socioNome() }}
                        @endif
                        <span class="td-sub">{{ $socioDati['carica'] ?? '' }}</span>
                    </x-dato>
                    <x-dato :label="$rinuncia ? 'Data della dichiarazione' : 'Data della nota'" :valore="$n->data_nota->format('d/m/Y')" />
                    @unless ($rinuncia)
                        <x-dato label="Modalità prevista" :valore="\App\Models\NotaRimborso::MODALITA[$n->modalita] ?? null" />
                        <x-dato label="IBAN" :valore="$n->iban" codice />
                    @endunless
                    <x-dato :label="$rinuncia ? 'Rinuncia firmata' : 'Nota firmata'">
                        @if ($n->firmata_path)
                            <a href="{{ route('prima-nota.anticipi.firmata.show', $n) }}" target="_blank" rel="noopener">{{ $n->firmata_nome }}</a>
                        @endif
                    </x-dato>
                    <x-dato label="Visto" :valore="$visto ? ucfirst($visto['carica']) . ($visto['nome'] ? ', ' . $visto['nome'] : '') : null" />
                    <x-dato label="Preparata da" :valore="($n->preparataDa?->name ?? '—') . ', ' . $n->created_at->format('d/m/Y H:i')" class="col-span-2" />
                    @if ($n->note)<x-dato label="Note" :valore="$n->note" class="col-span-2" />@endif
                </dl>
                @if ($puoModificare && $n->stato === 'firmata')
                    <details class="form-riga an-sostituisci">
                        <summary>Sostituisci la copia firmata</summary>
                        <form method="POST" action="{{ route('prima-nota.anticipi.firmata', $n) }}" enctype="multipart/form-data" class="form-modale form-riga">
                            @csrf
                            <label class="form-label" for="firmata-nuova">Nuova copia firmata</label>
                            <input id="firmata-nuova" name="firmata" type="file" class="form-input" accept="image/jpeg,image/png,application/pdf,.pdf" required>
                            <div><button type="submit" class="btn btn-secondary btn-sm">Sostituisci</button></div>
                        </form>
                    </details>
                @endif
            </x-card>
        </aside>
    </div>

    <x-card :titolo="$rinuncia ? 'Spese della rinuncia' : 'Spese della nota'" senza-padding>
        <div class="table-wrap">
            <table class="data-table responsive">
                <thead>
                    <tr>
                        <th scope="col">Data</th>
                        <th scope="col">Descrizione</th>
                        <th scope="col">Categoria</th>
                        <th scope="col">Giustificativo</th>
                        <th scope="col" class="td-num">Importo €</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($righe as $r)
                        @php
                            $m = $movimenti[$r['movimento_id']] ?? null;
                            $doc = $m?->documenti->first();
                            $foto = $m?->scontrino?->file->first();
                        @endphp
                        <tr>
                            <td data-label="Data"><strong>{{ \Illuminate\Support\Carbon::parse($r['data'])->format('d/m/Y') }}</strong></td>
                            <td data-label="Descrizione" class="td-primario">
                                @if ($m)<a href="{{ route('prima-nota.show', $m) }}">{{ $r['descrizione'] }}</a>@else{{ $r['descrizione'] }}@endif
                                @if ($r['fornitore'])<span class="td-sub">{{ $r['fornitore'] }}</span>@endif
                            </td>
                            <td data-label="Categoria">{{ $r['categoria'] }}</td>
                            <td data-label="Giustificativo">
                                @if ($m && $doc)
                                    <a href="{{ route('prima-nota.documenti.show', [$m, $doc]) }}" target="_blank" rel="noopener">{{ $r['documento'] }}</a>
                                @elseif ($m && $foto && auth()->user()->puoAccedere('scontrini'))
                                    <a href="{{ route('scontrini.foto.show', [$m->scontrino, $foto]) }}" target="_blank" rel="noopener">{{ $r['documento'] }}</a>
                                @else
                                    {{ $r['documento'] }}
                                @endif
                            </td>
                            <td data-label="Importo €" class="td-num">{{ Importo::it($r['importo']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="td-num td-solo-desktop"><strong>Totale</strong></td>
                        <td data-label="Totale €" class="td-num"><strong>{{ Importo::it($n->totale) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-card>

    @if ($puoModificare)
        @push('modals')
            @if ($n->annullabile())
                <x-modale nome="annulla-nota" :titolo="'Annullare ' . $n->numero . '?'" :aperta="$errors->annulla->any()">
                    <form method="POST" action="{{ route('prima-nota.anticipi.annulla', $n) }}" class="form-modale" id="form-annulla-nota">
                        @csrf
                        <p>Le spese tornano tra quelle senza nota. La nota resta nell'elenco come «annullata», con il motivo: il numero {{ $n->numero }} non si riusa.</p>
                        <div @class(['form-field', 'has-error' => $errors->annulla->has('motivazione')])>
                            <label class="form-label" for="motivazione-annulla">Motivo<span class="obbligatorio" aria-hidden="true">*</span></label>
                            <textarea id="motivazione-annulla" name="motivazione" rows="3" required minlength="15" maxlength="1000" class="form-textarea"
                                      aria-describedby="motivazione-annulla-aiuto">{{ $errors->annulla->any() ? old('motivazione') : '' }}</textarea>
                            <div class="form-help" id="motivazione-annulla-aiuto">Almeno 15 caratteri: resta nell'Audit Log e arriva per email agli altri.</div>
                            @foreach ($errors->annulla->get('motivazione') as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                        </div>
                    </form>
                    <x-slot:piede>
                        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Indietro</button>
                        <button type="submit" form="form-annulla-nota" class="btn btn-danger">Annulla {{ $n->numero }}</button>
                    </x-slot:piede>
                </x-modale>
            @endif
            @if ($n->isPagata())
                <x-modale nome="annulla-pagamento" :titolo="$rinuncia ? 'Annullare la registrazione?' : 'Annullare il pagamento?'" :aperta="$errors->annulla_pagamento->any()">
                    <form method="POST" action="{{ route('prima-nota.anticipi.annulla-pagamento', $n) }}" class="form-modale" id="form-annulla-pagamento">
                        @csrf
                        <p>
                            Le spese escono dai saldi e tornano alla data della spesa@if ($rinuncia), l'entrata della donazione si toglie@endif; la nota torna «firmata».
                            @if ($chiusoPagamento) L'anno {{ $n->pagata_il->year }} è chiuso: i suoi saldi finali si aggiornano. @endif
                        </p>
                        <div @class(['form-field', 'has-error' => $errors->annulla_pagamento->has('motivazione')])>
                            <label class="form-label" for="motivazione-pagamento">Motivo<span class="obbligatorio" aria-hidden="true">*</span></label>
                            <textarea id="motivazione-pagamento" name="motivazione" rows="3" required minlength="15" maxlength="1000" class="form-textarea"
                                      aria-describedby="motivazione-pagamento-aiuto">{{ $errors->annulla_pagamento->any() ? old('motivazione') : '' }}</textarea>
                            <div class="form-help" id="motivazione-pagamento-aiuto">Almeno 15 caratteri: resta nell'Audit Log (rischio alto) e arriva per email agli altri.</div>
                            @foreach ($errors->annulla_pagamento->get('motivazione') as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                        </div>
                    </form>
                    <x-slot:piede>
                        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Indietro</button>
                        <button type="submit" form="form-annulla-pagamento" class="btn btn-danger">{{ $rinuncia ? 'Annulla la registrazione' : 'Annulla il pagamento' }}</button>
                    </x-slot:piede>
                </x-modale>
            @endif
        @endpush
    @endif
@endsection
