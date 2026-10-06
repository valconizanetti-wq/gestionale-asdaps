@extends('layouts.app')

@section('title', "Movimento n. {$m->id}")

@use('App\Support\Importo')

@section('content')
    @php
        $puoModificare = auth()->user()->puoModificare('prima_nota');
        $giroconto = $m->isGiroconto();
        $daClassificare = $m->categoria?->codice === \App\Models\PrimaNotaCategoria::DA_CLASSIFICARE;
        $eliminabile = ! $m->rinnovo_id && ! $m->scontrino_id;
    @endphp

    <x-page-header :titolo="$giroconto ? 'Giroconto' : 'Movimento'" :evidenza="'n. ' . $m->id"
                   :sottotitolo="$m->descrizione" :indietro="route('prima-nota.index', ['anno' => $m->annoMovimento()])" :indietro-label="'Prima Nota ' . $m->annoMovimento()">
        <div class="badge-riga">
            @if ($giroconto)
                <x-badge variante="neutral">Giroconto</x-badge>
            @elseif ($m->isEntrata())
                <x-badge variante="ok">Entrata</x-badge>
            @else
                <x-badge variante="ko">Uscita</x-badge>
            @endif
            @if ($daClassificare)<x-badge variante="warn">Da classificare</x-badge>@endif
            @if ($m->isAnticipoAperto())<x-badge variante="warn">Da rimborsare</x-badge>@endif
            @if ($chiuso)<x-badge variante="neutral">Anno {{ $m->annoMovimento() }} chiuso</x-badge>@endif
        </div>
        @if ($puoModificare)
            <x-slot:azioni>
                @unless ($m->isAnticipoAperto())
                <a href="{{ route('prima-nota.edit', $m) }}" class="btn btn-primary"><x-icona nome="matita" width="14" height="14" /> {{ $chiuso ? 'Correggi' : 'Modifica' }}</a>
                @endunless
                @if ($eliminabile)
                    <button type="button" class="btn btn-danger-outline" @click="$dispatch('apri-modale', 'elimina')"><x-icona nome="cestino" width="14" height="14" /> Elimina</button>
                @endif
            </x-slot:azioni>
        @endif
    </x-page-header>

    <x-sola-lettura sezione="prima_nota" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Operazione non eseguita.">{{ $errors->first() }}</x-avviso>
    @endif

    @if ($m->rinnovo_id)
        <x-avviso tipo="info" titolo="Quota associativa.">
            Movimento della quota {{ $m->rinnovo?->anno }}@if ($m->socio) di {{ $m->socio->cognome }} {{ $m->socio->nome }}@endif: non si elimina da qui.
        </x-avviso>
    @endif

    <div class="scheda-griglia">
        <x-card titolo="Dati del movimento">
            <dl class="dati-griglia">
                <x-dato label="Data" :valore="$m->data->format('d/m/Y')" />
                <x-dato label="Importo">
                    <span class="{{ $giroconto ? 'importo-neutro' : ($m->isEntrata() ? 'importo-entrata' : 'importo-uscita') }}">{{ Importo::it($m->importo, true) }}</span>
                </x-dato>
                @if ($giroconto)
                    <x-dato label="Da" :valore="$m->contoLabel()" />
                    <x-dato label="A" :valore="$m->giroconto?->contoLabel()" />
                @else
                    <x-dato label="Conto" :valore="$m->sospeso && ! $m->rimborsato_il ? 'Da rimborsare a ' . $m->anticipato_da : $m->contoLabel()" />
                    <x-dato label="Categoria" :valore="$m->categoria?->nome" />
                @endif
                <x-dato label="Descrizione" :valore="$m->descrizione" class="col-span-2" />
                @unless ($giroconto)
                    <x-dato label="Fornitore o soggetto" :valore="$m->soggetto" />
                    @if ($m->isEntrata())
                        <x-dato label="Socio">
                            @if ($m->socio)<a href="{{ route('soci.show', $m->socio) }}">{{ $m->socio->cognome }} {{ $m->socio->nome }}</a>@endif
                        </x-dato>
                    @endif
                    <x-dato label="Tag">
                        @if ($m->tag)<a href="{{ route('prima-nota.index', ['anno' => $m->annoMovimento(), 'tag' => $m->tag_id]) }}" class="badge-tag">{{ $m->tag->nome }}</a>@endif
                    </x-dato>
                    @if ($m->progetto)<x-dato label="Progetto" :valore="$m->progetto->nome" />@endif
                    <x-dato label="Documento" :valore="trim(implode(' ', array_filter([\App\Models\PrimaNota::TIPI_DOCUMENTO[$m->tipo_documento] ?? null, $m->numero_documento, $m->data_documento ? 'del ' . $m->data_documento->format('d/m/Y') : null])))" />
                @endunless
            </dl>
            @if ($m->note)
                <div class="form-riga">
                    <span class="dato-label">Note</span>
                    <p class="testo-note">{{ $m->note }}</p>
                </div>
            @endif
            <p class="form-riga form-help">Registrato il {{ $m->created_at?->format('d/m/Y H:i') }}@if ($m->registratoDa) da {{ $m->registratoDa->name }}@endif.</p>
        </x-card>

        @unless ($giroconto)
            <x-card titolo="Giustificativi">
                @if ($m->documenti->isEmpty())
                    <p class="form-help">Nessun giustificativo allegato.</p>
                @else
                    <div class="pn-allegati">
                        @foreach ($m->documenti as $d)
                            <span class="pn-allegato">
                                <a href="{{ route('prima-nota.documenti.show', [$m, $d]) }}" target="_blank" rel="noopener">{{ $d->nome }}</a>
                                <a href="{{ route('prima-nota.documenti.show', [$m, $d, 'scarica' => 1]) }}" class="action-btn" aria-label="Scarica {{ $d->nome }}"><x-icona nome="download" /></a>
                                @if ($puoModificare)
                                    <button type="button" class="action-btn" aria-label="Togli {{ $d->nome }}" @click="$dispatch('apri-modale', 'togli-{{ $d->id }}')"><x-icona nome="chiudi" /></button>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif

                @if ($puoModificare)
                    <form method="POST" action="{{ route('prima-nota.documenti.store', $m) }}" enctype="multipart/form-data" class="form-riga form-modale">
                        @csrf
                        <div @class(['form-field', 'has-error' => $errors->has('documenti') || $errors->has('documenti.*')])>
                            <label class="form-label" for="aggiungi-documenti">Aggiungi giustificativi</label>
                            <input id="aggiungi-documenti" type="file" name="documenti[]" multiple accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" class="form-input pn-file">
                            <div class="form-help">JPG, PNG o PDF, fino a 8 MB ciascuno.</div>
                            @foreach ($errors->get('documenti*') as $msg)<div class="form-error">{{ $msg[0] }}</div>@endforeach
                        </div>
                        @if ($chiuso)
                            <div class="form-field">
                                <label class="form-label" for="motivazione-aggiungi">Motivazione<span class="obbligatorio" aria-hidden="true">*</span></label>
                                <textarea id="motivazione-aggiungi" name="motivazione" rows="2" minlength="15" maxlength="1000" class="form-textarea"
                                          aria-describedby="motivazione-aggiungi-aiuto"></textarea>
                                <div class="form-help" id="motivazione-aggiungi-aiuto">L'anno è chiuso: almeno 15 caratteri, restano nell'Audit Log.</div>
                            </div>
                        @endif
                        <div><button type="submit" class="btn btn-secondary btn-sm">Carica</button></div>
                    </form>
                @endif
            </x-card>
        @endunless
    </div>

    @if ($puoModificare)
        @push('modals')
            @if ($eliminabile)
                <x-modale nome="elimina" :titolo="'Eliminare il movimento n. ' . $m->id . '?'">
                    <form method="POST" action="{{ route('prima-nota.destroy', $m) }}" class="form-modale" id="form-elimina">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="conferma" value="si">
                        <p>{{ $m->descrizione }} — {{ Importo::it($m->importo, true) }} del {{ $m->data->format('d/m/Y') }}.
                            @if ($giroconto) Si eliminano entrambe le righe del giroconto. @endif
                            @if ($m->documenti->isNotEmpty()) Si eliminano anche i giustificativi allegati. @endif
                        </p>
                        @if ($chiuso)
                            <x-campo name="motivazione" label="Motivazione" tipo="textarea" required rows="3" errore-esterno
                                     aiuto="L'anno è chiuso: almeno 15 caratteri, restano nell'Audit Log." />
                        @endif
                    </form>
                    <x-slot:piede>
                        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                        <button type="submit" form="form-elimina" class="btn btn-danger">Elimina</button>
                    </x-slot:piede>
                </x-modale>
            @endif
            @foreach ($m->documenti as $d)
                <x-modale nome="togli-{{ $d->id }}" titolo="Togliere il giustificativo?">
                    <form method="POST" action="{{ route('prima-nota.documenti.destroy', [$m, $d]) }}" class="form-modale" id="form-togli-{{ $d->id }}">
                        @csrf
                        @method('DELETE')
                        <p>{{ $d->nome }}: il file viene cancellato.</p>
                        @if ($chiuso)
                            <div class="form-field">
                                <label class="form-label" for="motivazione-togli-{{ $d->id }}">Motivazione<span class="obbligatorio" aria-hidden="true">*</span></label>
                                <textarea id="motivazione-togli-{{ $d->id }}" name="motivazione" rows="3" required minlength="15" maxlength="1000" class="form-textarea"
                                          aria-describedby="motivazione-togli-{{ $d->id }}-aiuto"></textarea>
                                <div class="form-help" id="motivazione-togli-{{ $d->id }}-aiuto">L'anno è chiuso: almeno 15 caratteri, restano nell'Audit Log.</div>
                            </div>
                        @endif
                    </form>
                    <x-slot:piede>
                        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                        <button type="submit" form="form-togli-{{ $d->id }}" class="btn btn-danger">Togli</button>
                    </x-slot:piede>
                </x-modale>
            @endforeach
        @endpush
    @endif
@endsection
