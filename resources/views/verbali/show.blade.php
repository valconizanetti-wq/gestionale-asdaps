{{--
    Scheda del verbale (v0.44.0, tavola VB-3). Tre passi: bozza → definitivo
    (numero nel libro, PDF salvato con l'impronta) → firmato (copia con la
    firma digitale, V2 e V14). In bozza: «Scrivi il verbale» (riunione),
    anteprima e «Rendi definitivo» con i controlli. Definitivo: carica la
    copia firmata. Firmato: firme, sostituzione con il motivo. Dal
    definitivo: rettifiche datate e motivate, ciascuna con il suo PDF.
    Variabili: $v, $documenti, $mancanze.
--}}
@extends('layouts.app')

@section('title', $v->codice() ? 'Verbale ' . $v->codice() : 'Verbale in bozza')

@push('head')
    @if (auth()->user()->puoModificare('verbali') && $v->isImmutabile())
        @vite('resources/js/editor.js')
    @endif
@endpush

@section('content')
    @php
        $puoModificare = auth()->user()->puoModificare('verbali');
        $passo = $v->isBozza() ? 1 : ($v->isDefinitivo() ? 2 : 4);
        $nomi = [
            'Bozza',
            'Definitivo' . ($v->definitivo_il ? ' · ' . $v->definitivo_il->format('d/m/Y') : ''),
            'Firmato' . ($v->firmato_il ? ' · ' . $v->firmato_il->format('d/m/Y') : ''),
        ];
        $indietro = route('verbali.index', ['libro' => $v->libro, 'anno' => $v->anno_libro ?: $v->data_seduta?->year]);
    @endphp

    <x-page-header :titolo="$v->codice() ? 'Verbale' : 'Verbale in'" :evidenza="$v->codice() ?: 'bozza'"
                   :sottotitolo="$v->titolo . ($v->data_seduta && ! str_contains((string) $v->titolo, $v->data_seduta->format('d/m/Y')) ? ' · ' . $v->data_seduta->format('d/m/Y') : '')"
                   :indietro="$indietro" :indietro-label="$v->libro === 'cd' ? 'Libro del CD' : 'Libro delle Assemblee'">
        <div class="badge-riga">
            <x-badge :variante="$v->statoVariante()">{{ $v->statoTesto() }}</x-badge>
            <x-badge variante="neutral">{{ $v->tipoTesto() }}</x-badge>
            @if ($v->rettifiche->isNotEmpty())<x-badge variante="info">{{ $v->rettifiche->count() === 1 ? 'Con rettifica' : $v->rettifiche->count() . ' rettifiche' }}</x-badge>@endif
        </div>
        <x-slot:azioni>
            @if (! $v->da_fuori)
                <a href="{{ route('verbali.pdf', $v) }}" target="_blank" rel="noopener" class="btn btn-secondary">
                    <x-icona nome="download" width="14" height="14" /> {{ $v->isBozza() ? 'Anteprima PDF' : 'PDF definitivo' }}
                </a>
            @endif
            @if ($v->isFirmato())
                <a href="{{ route('verbali.firmato.show', $v) }}" target="_blank" rel="noopener" class="btn btn-secondary">
                    <x-icona nome="download" width="14" height="14" /> Copia firmata
                </a>
            @endif
            @if ($puoModificare && $v->isBozza() && $v->tipo === 'cd_riunione' && $v->seduta)
                <a href="{{ route('verbali.scrivi', $v) }}" class="btn btn-primary"><x-icona nome="matita" width="14" height="14" /> Scrivi il verbale</a>
            @endif
        </x-slot:azioni>
    </x-page-header>

    <x-sola-lettura sezione="verbali" />

    @foreach (['default', 'firmato', 'rettifica', 'rettifica_firmata', 'documenti'] as $bag)
        @if ($errors->$bag->any() && ! in_array($bag, ['firmato', 'rettifica']))
            <x-avviso tipo="ko" titolo="Operazione non eseguita.">{{ $errors->$bag->first() }}</x-avviso>
        @endif
    @endforeach

    <x-passi :corrente="$passo" :nomi="$nomi" etichetta="Passi del verbale" class="an-passi" />

    <div class="pn-colonne">
        <div class="pn-colonna">
            @if ($v->isBozza())
                <x-card titolo="Rendi definitivo">
                    @if ($mancanze)
                        <x-avviso tipo="warn" titolo="Non ancora.">
                            <ul class="vb-elenco">@foreach ($mancanze as $m)<li>{{ $m }}</li>@endforeach</ul>
                        </x-avviso>
                    @else
                        <p class="form-intro">
                            Il verbale prende il <strong>numero {{ \App\Services\Verbali::prossimoNumero($v->libro, (int) $v->data_seduta->year) }}</strong> nel libro {{ $v->libro === 'cd' ? 'del CD' : 'delle Assemblee' }} del {{ $v->data_seduta->year }}
                            e il PDF si salva con la sua impronta. <strong>Da lì non si modifica più</strong>: un errore si corregge con una rettifica.
                        </p>
                    @endif
                    @if ($puoModificare && ! $mancanze)
                        <div class="form-actions">
                            <a href="{{ route('verbali.pdf', $v) }}" target="_blank" rel="noopener" class="btn btn-secondary">Controlla l'anteprima</a>
                            <button type="button" class="btn btn-primary" x-data x-on:click="$dispatch('apri-modale', 'definitivo')">Rendi definitivo</button>
                        </div>
                    @endif
                </x-card>
            @elseif ($v->isDefinitivo())
                <x-card titolo="Copia firmata">
                    <p class="form-intro">
                        Scarica il <a href="{{ route('verbali.pdf', $v) }}" target="_blank" rel="noopener">PDF definitivo</a>, fallo firmare digitalmente (CIE o kit di firma)
                        dal presidente della seduta, <strong>{{ $v->presidente_assemblea ?: '—' }}</strong>, e dal segretario verbalizzante, <strong>{{ $v->segretario_verbalizzante ?: '—' }}</strong>, poi caricalo qui.
                    </p>
                    @if ($puoModificare)
                        @include('verbali._firmato', ['sostituzione' => false])
                    @endif
                </x-card>
            @else
                <x-card titolo="Copia firmata">
                    @if ($v->mancaFirmaPresidente())
                        <x-avviso tipo="warn" titolo="Manca la firma del presidente della seduta.">Nel file c'è solo la firma del segretario verbalizzante: quando si può, carica la copia con tutte e due le firme.</x-avviso>
                    @endif
                    <dl class="dati-griglia">
                        <x-dato label="Firma" :valore="$v->firmaTesto()" />
                        <x-dato label="Firmatari letti nel file" :valore="$v->firmatari ?: 'non scritti nel file'" />
                        <x-dato label="Caricata il" :valore="$v->firmato_il?->format('d/m/Y H:i') . ($v->firmatoDa ? ' da ' . $v->firmatoDa->name : '')" />
                        <x-dato label="Impronta (SHA-256)" :valore="$v->hash_file_verbale" codice class="dato-largo" />
                    </dl>
                    <p class="form-help">Il gestionale controlla che ci sia la firma digitale, non la validità del certificato: per quella si usa il servizio di verifica dell'AgID o del certificatore.</p>
                    @if ($puoModificare)
                        <details class="an-sostituisci" @if ($errors->firmato->any()) open @endif>
                            <summary>Sostituisci la copia firmata</summary>
                            @include('verbali._firmato', ['sostituzione' => true])
                        </details>
                    @endif
                </x-card>
            @endif

            <x-card :titolo="$v->da_fuori ? 'Dati del verbale' : 'Testo del verbale'">
                <div class="documento-testo vb-testo">
                    @include('verbali._corpo', ['v' => $v, 'documenti' => $documenti])
                </div>
            </x-card>

            @if ($v->isImmutabile() && ($v->rettifiche->isNotEmpty() || $puoModificare))
                <x-card titolo="Rettifiche">
                    @forelse ($v->rettifiche as $r)
                        <div class="vb-rettifica">
                            <div class="vb-rettifica-testa">
                                <strong>Rettifica n. {{ $r->numero }} · {{ $r->created_at->format('d/m/Y') }}</strong>
                                <span class="badge-riga">
                                    <a href="{{ route('verbali.rettifiche.show', [$v, $r]) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">PDF</a>
                                    @if ($r->firmato_path)
                                        <a href="{{ route('verbali.rettifiche.show', [$v, $r, 'firmata']) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">Firmata</a>
                                    @endif
                                </span>
                            </div>
                            <p class="testo-secondario">Motivo: {{ $r->motivo }}{{ $r->creataDa ? ' · ' . $r->creataDa->name : '' }}</p>
                            <div class="vb-html">{!! $r->testo_html !!}</div>
                            @if (! $r->firmato_path && $puoModificare)
                                <form method="POST" action="{{ route('verbali.rettifiche.firmata', [$v, $r]) }}" enctype="multipart/form-data" class="form-riga"
                                      x-data="{ inviato: false }" x-on:submit="inviato = true">
                                    @csrf
                                    <div @class(['form-field', 'has-error' => $errors->rettifica_firmata->has('firmato_rettifica')])>
                                        <label class="form-label" for="firmata-{{ $r->id }}">Copia firmata della rettifica</label>
                                        <input id="firmata-{{ $r->id }}" name="firmato_rettifica" type="file" class="form-input" accept=".pdf,.p7m,application/pdf" required>
                                    </div>
                                    <div><button type="submit" class="btn btn-secondary" x-bind:disabled="inviato">Carica</button></div>
                                </form>
                            @elseif ($r->firmato_path)
                                <p class="form-help">Firmata digitalmente ({{ $r->firma_tipo }}), caricata il {{ $r->firmato_il?->format('d/m/Y') }}.</p>
                            @endif
                        </div>
                    @empty
                        <p class="form-help">Nessuna rettifica.</p>
                    @endforelse

                    @if ($puoModificare)
                        <details class="an-sostituisci" @if ($errors->rettifica->any()) open @endif>
                            <summary>Aggiungi una rettifica</summary>
                            <form method="POST" action="{{ route('verbali.rettifiche.store', $v) }}" class="form-modale" x-data="{ inviato: false }" x-on:submit="inviato = true">
                                @csrf
                                <p class="form-help">La rettifica si aggiunge in fondo, con la data e il motivo, e ha il suo PDF da firmare: il testo del verbale resta com'è (statuto, art. 23.2).</p>
                                @if ($errors->rettifica->any())<x-avviso tipo="ko">{{ $errors->rettifica->first() }}</x-avviso>@endif
                                <x-editor name="testo_html" label="Testo della rettifica" required :altezza="140" />
                                <div @class(['form-field', 'has-error' => $errors->rettifica->has('motivo')])>
                                    <label class="form-label" for="motivo-rettifica">Motivo<span class="obbligatorio" aria-hidden="true">*</span></label>
                                    <input id="motivo-rettifica" name="motivo" type="text" class="form-input" maxlength="500" minlength="15" required value="{{ $errors->rettifica->any() ? old('motivo') : '' }}">
                                </div>
                                <div class="form-actions form-actions-fine">
                                    <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Aggiungi la rettifica</button>
                                </div>
                            </form>
                        </details>
                    @endif
                </x-card>
            @endif
        </div>

        <div class="pn-colonna">
            <x-card titolo="Dati">
                <dl class="dati-griglia dati-una-colonna">
                    <x-dato label="Libro" :valore="\App\Models\Verbale::LIBRI[$v->libro] ?? $v->libro" />
                    <x-dato label="Numero" :valore="$v->numeroTesto()" />
                    <x-dato label="Data della seduta" :valore="$v->data_seduta?->format('d/m/Y')" />
                    <x-dato label="Presidente della seduta" :valore="$v->presidente_assemblea" />
                    <x-dato label="Segretario verbalizzante" :valore="$v->segretario_verbalizzante" />
                    @if ($v->approva_rendiconto_anno)
                        <x-dato label="Rendiconto" :valore="'Approva il rendiconto ' . $v->approva_rendiconto_anno" />
                    @endif
                    @if ($v->definitivo_il)
                        <x-dato label="Definitivo" :valore="$v->definitivo_il->format('d/m/Y H:i') . ($v->definitivoDa ? ' · ' . $v->definitivoDa->name : '')" />
                    @endif
                    @if ($v->pdf_impronta)
                        <x-dato label="Impronta del PDF" :valore="$v->pdf_impronta" codice />
                    @endif
                </dl>
                @if ($v->seduta && $v->tipo === 'cd_riunione')
                    <p class="form-help"><a href="{{ route('verbali.riunioni.show', $v->seduta) }}">Riunione del {{ $v->seduta->data->format('d/m/Y') }}</a></p>
                @elseif ($v->consultazione)
                    <p class="form-help"><a href="{{ route('verbali.consultazioni.show', $v->consultazione) }}">Consultazione: stato dei voti</a></p>
                @elseif ($v->domanda && auth()->user()->puoAccedere('domande'))
                    <p class="form-help"><a href="{{ route('domande.show', $v->domanda) }}">Domanda di {{ $v->domanda->nomeCognome() }}</a></p>
                @endif
            </x-card>

            <x-card titolo="Documenti">
                @if ($documenti->isEmpty())
                    <p class="form-help">Nessun documento.</p>
                @else
                    <ul class="ct-documenti">
                        @foreach ($documenti as $d)
                            <li>
                                <a href="{{ route('verbali.documenti.show', [$v, $d]) }}" target="_blank" rel="noopener"><x-icona nome="documento" width="16" height="16" /> Allegato {{ $loop->iteration }}: {{ $d->nome }}</a>
                                <span class="testo-secondario">{{ $d->pesoTesto() }}</span>
                                @if ($puoModificare && $v->isBozza())
                                    <form method="POST" action="{{ route('verbali.documenti.destroy', [$v, $d]) }}" class="vb-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="action-btn" aria-label="Togli {{ $d->nome }}"><x-icona nome="cestino" width="14" height="14" /></button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($puoModificare && $v->isBozza())
                    <form method="POST" action="{{ route('verbali.documenti.store', $v) }}" enctype="multipart/form-data" class="form-modale" x-data="{ inviato: false }" x-on:submit="inviato = true">
                        @csrf
                        <x-campo-giustificativi id="documenti-verbale" label="Aggiungi documenti" accept=".pdf,application/pdf"
                                                aiuto="Solo PDF, fino a 8 MB ciascuno. Si scaricano a parte, non dentro il PDF del verbale." :errori="$errors->documenti->all()" />
                        <div><button type="submit" class="btn btn-secondary" x-bind:disabled="inviato">Carica</button></div>
                    </form>
                @endif
            </x-card>
        </div>
    </div>

    @if ($puoModificare && $v->isBozza() && ! $mancanze)
        @push('modals')
            <x-modale nome="definitivo" titolo="Rendi definitivo il verbale">
                <form method="POST" action="{{ route('verbali.definitivo', $v) }}" class="form-modale" id="form-definitivo">
                    @csrf
                    <p>Il verbale prende il numero <strong>{{ $v->data_seduta->year }}/{{ \App\Services\Verbali::prossimoNumero($v->libro, (int) $v->data_seduta->year) }}</strong> e il PDF si salva così com'è.</p>
                    <p><strong>Da qui non si modifica più</strong>: un errore si corregge con una rettifica, datata e motivata. L'operazione arriva per email agli altri del CD.</p>
                </form>
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Indietro</button>
                    <button type="submit" form="form-definitivo" class="btn btn-primary">Rendi definitivo</button>
                </x-slot:piede>
            </x-modale>
        @endpush
    @endif
@endsection
