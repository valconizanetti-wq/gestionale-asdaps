@extends('layouts.app')

@section('title', $p->exists ? $p->nome : 'Nuovo promemoria')

@use('App\Models\Promemoria')
@use('App\Promemoria\Invii')

@section('content')
    @php
        $quando = old('quando', $p->quando);
        $sola = ! $puoModificare;
    @endphp

    <x-page-header :titolo="$p->exists ? $p->nome : 'Nuovo promemoria'"
                   :sottotitolo="'Funzione «' . $f->nome() . '»' . ($prossimo && $p->attivo ? ' · prossimo invio ' . $prossimo->format('d/m/Y \a\l\l\e H:i') : '')"
                   :indietro="route('promemoria.index')" indietro-label="Promemoria">
        @if ($p->exists && $puoModificare)
            <x-slot:azioni>
                <form method="POST" action="{{ route('promemoria.prova', $p) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Invia una prova a me</button>
                </form>
                <button type="submit" form="form-promemoria" class="btn btn-primary">Salva</button>
            </x-slot:azioni>
        @endif
    </x-page-header>

    @if ($sola)
        <x-avviso tipo="info" titolo="Sola lettura.">Il tuo ruolo consulta questo promemoria ma non lo modifica.</x-avviso>
    @endif
    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Controlla i dati.">Alcuni campi non sono validi: li trovi evidenziati qui sotto.</x-avviso>
    @endif

    <div class="pn-colonne">
        <form method="POST" action="{{ $p->exists ? route('promemoria.update', $p) : route('promemoria.store') }}" id="form-promemoria" class="pn-colonna">
            @csrf
            @if ($p->exists) @method('PUT') @else <input type="hidden" name="funzione" value="{{ $f->chiave() }}"> @endif
            <fieldset class="pr-fieldset" @disabled($sola)>
                <x-card titolo="Promemoria">
                    <div class="form-grid">
                        <x-campo name="nome" label="Nome" required :value="$p->nome" maxlength="150" />
                        <div class="form-field">
                            <span class="form-label">Funzione</span>
                            <span class="pr-valore">{{ $f->nome() }}</span>
                            <div class="form-help">Decide chi riceve il promemoria. Non si cambia dopo la creazione.</div>
                        </div>
                    </div>
                    <label class="spunta form-riga">
                        <input type="hidden" name="attivo" value="0">
                        <input type="checkbox" name="attivo" value="1" @checked(old('attivo', $p->attivo))>
                        <span><strong>Attivo</strong><span class="scelta-sub">Spento: non parte, resta con il suo storico.</span></span>
                    </label>
                </x-card>

                <x-card titolo="Quando">
                    <fieldset class="scelta-gruppo">
                        <legend class="sr-only">Tipo di pianificazione</legend>
                        <div class="scelta-opzioni">
                            <label class="scelta">
                                <input type="radio" name="quando" value="data_fissa" @checked($quando === 'data_fissa')>
                                <span class="scelta-testo">Data fissa ogni anno <span class="scelta-sub">Es. 1° aprile</span></span>
                            </label>
                            <label class="scelta pr-scelta-spenta">
                                <input type="radio" name="quando" value="relativa" @checked($quando === 'relativa') @disabled(! in_array('relativa', $f->quandoAmmessi(), true))>
                                <span class="scelta-testo">Prima o dopo una data del destinatario
                                    <span class="scelta-sub">{{ in_array('relativa', $f->quandoAmmessi(), true) ? 'N giorni prima o dopo' : 'Non disponibile per questa funzione' }}</span></span>
                            </label>
                        </div>
                    </fieldset>
                    <div class="form-grid form-grid-3 form-riga">
                        <x-campo name="giorno" label="Giorno" tipo="select" :value="$p->giorno" :opzioni="array_combine(range(1, 31), range(1, 31))" />
                        <x-campo name="mese" label="Mese" tipo="select" :value="$p->mese" :opzioni="array_map('ucfirst', Promemoria::MESI)" />
                        <x-campo name="ora" label="Ora" tipo="select" :value="$p->oraBreve()"
                                 :opzioni="collect(range(7, 21))->mapWithKeys(fn ($h) => [sprintf('%02d:00', $h) => $h . ':00'])->all()" />
                    </div>
                    @foreach ($f->parametri() as $chiave => $def)
                        <div class="form-riga">
                            <x-campo :name="'parametri[' . $chiave . ']'" :label="$def['label']" tipo="select" :value="$p->parametro($chiave)"
                                     :opzioni="$def['opzioni']" :aiuto="$def['aiuto'] ?? null" />
                        </div>
                    @endforeach
                </x-card>

                <x-card titolo="Email">
                    <x-campo name="oggetto" label="Oggetto" required :value="$p->oggetto" maxlength="200" />
                    <div class="form-riga">
                        <x-campo name="testo" label="Testo" tipo="textarea" required :value="$p->testo" rows="11" maxlength="5000" />
                    </div>
                    <div class="pr-segnaposti">
                        <span class="form-help">Segnaposti:</span>
                        @foreach ($f->segnaposti() as $k => $descr)
                            <span class="pr-chip" title="{{ $descr }}">{{ '{' . $k . '}' }}</span>
                        @endforeach
                    </div>
                    <p class="form-help">Ogni segnaposto si sostituisce con il valore del destinatario (passa il mouse per la descrizione). L'email ha l'intestazione e il piè di pagina della Compagnia.</p>
                </x-card>
            </fieldset>

            @if ($puoModificare)
                <div class="form-actions">
                    @if ($p->exists && $storico->where('inviati', '>', 0)->isEmpty())
                        <button type="button" class="btn btn-danger-outline" @click="$dispatch('apri-modale', 'elimina-promemoria')">Elimina</button>
                    @endif
                    <a href="{{ route('promemoria.index') }}" class="btn btn-ghost">Annulla</a>
                    <button type="submit" class="btn btn-primary">{{ $p->exists ? 'Salva' : 'Crea il promemoria' }}</button>
                </div>
            @endif
        </form>

        <aside class="pn-colonna">
            <x-card titolo="Destinatari">
                @php $quandoTesto = $riferimento->isToday() ? 'Oggi' : 'Il ' . $riferimento->format('d/m/Y'); @endphp
                @if ($destinatari->isEmpty())
                    <p class="form-help">{{ $quandoTesto }} nessuno riceverebbe il promemoria (tutti in regola, o già avvisati in questo periodo).</p>
                @else
                    <x-avviso tipo="info" :titolo="$quandoTesto . ' lo riceverebbero ' . $destinatari->count() . ($destinatari->count() === 1 ? ' destinatario' : ' destinatari')">
                        {{ $destinatari->take(8)->map(fn ($d) => $d->etichetta . ($d->email ? '' : ' — senza email'))->implode(', ') }}{{ $destinatari->count() > 8 ? '…' : '.' }}
                    </x-avviso>
                @endif
                <p class="form-help">Con la situazione di oggi; si ricalcolano il giorno dell'invio: chi nel frattempo è in regola esce dall'elenco.</p>
            </x-card>

            @if ($p->exists)
                <x-card :titolo="'Anteprima' . ($esempio ? ' per ' . $esempio->etichetta : '')">
                    @if ($esempio)
                        <div class="pr-anteprima">
                            <div class="pr-anteprima-oggetto"><strong>Oggetto:</strong> {{ Invii::componi($p->oggetto, $esempio->valori) }}</div>
                            <div>{!! nl2br(e(Invii::componi($p->testo, $esempio->valori)), false) !!}</div>
                        </div>
                    @else
                        <p class="form-help">Nessun destinatario oggi: l'anteprima compare quando ce n'è almeno uno. Per vederla comunque usa «Invia una prova a me».</p>
                    @endif
                </x-card>

                <x-card titolo="Storico" senza-padding>
                    @if ($storico->isEmpty())
                        <x-vuoto>Nessun invio ancora.</x-vuoto>
                    @else
                        <ul class="pr-storico">
                            @foreach ($storico as $riga)
                                <li><span>{{ \Illuminate\Support\Carbon::parse($riga->giorno)->format('d/m/Y') }}{{ $riga->periodo === 'prova' ? ' · prova' : ' · anno ' . $riga->periodo }}</span>
                                    <span class="td-sub">
                                        @if ($riga->periodo === 'prova')
                                            {{ (int) $riga->prove }} {{ (int) $riga->prove === 1 ? 'prova' : 'prove' }}
                                        @else
                                            {{ (int) $riga->inviati }} inviate{{ (int) $riga->falliti ? ' · ' . (int) $riga->falliti . ' non riuscite' : '' }}{{ (int) $riga->senza ? ' · ' . (int) $riga->senza . ' senza email' : '' }}
                                        @endif
                                    </span></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($senzaEmail->isNotEmpty())
                        <div class="card-body">
                            <x-avviso tipo="warn" titolo="Da avvisare a mano (senza email).">
                                {{ $senzaEmail->map(fn ($i) => $i->socio ? $i->socio->cognome . ' ' . $i->socio->nome . ' (' . $i->periodo . ')' : null)->filter()->implode(', ') }}.
                            </x-avviso>
                        </div>
                    @endif
                </x-card>
            @endif
        </aside>
    </div>

    @if ($p->exists && $puoModificare)
        @push('modals')
            <x-modale nome="elimina-promemoria" titolo="Eliminare il promemoria?">
                <form method="POST" action="{{ route('promemoria.destroy', $p) }}" id="form-elimina-promemoria">
                    @csrf
                    @method('DELETE')
                    <p>{{ $p->nome }}: non partirà più. Un promemoria già inviato non si elimina, si spegne.</p>
                </form>
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Indietro</button>
                    <button type="submit" form="form-elimina-promemoria" class="btn btn-danger">Elimina</button>
                </x-slot:piede>
            </x-modale>
        @endpush
    @endif
@endsection
