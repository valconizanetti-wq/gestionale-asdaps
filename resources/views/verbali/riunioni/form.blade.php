{{--
    Riunione del CD in bozza (v0.44.0, tavola CD-5, parte «Convocazione»):
    data, ora, luogo o videoconferenza, ordine del giorno a punti,
    urgenza con il motivo (meno di 3 giorni, comunque non meno di 24 ore:
    art. 16.1, V4), documenti in PDF.
    Variabili: $s (Seduta, nuova o in bozza), $documenti.
--}}
@extends('layouts.app')

@section('title', $s->exists ? 'Modifica la riunione' : 'Nuova riunione del CD')

@section('content')
    @php
        $punti = old('punti', $s->ordine_del_giorno ?: ['']);
        $punti = $punti ?: [''];
    @endphp

    <x-page-header :titolo="$s->exists ? 'Modifica la' : 'Nuova'" :evidenza="$s->exists ? 'riunione' : 'riunione del CD'"
                   sottotitolo="Si salva in bozza; la convocazione parte dalla scheda della riunione, con il controllo dei termini."
                   :indietro="$s->exists ? route('verbali.riunioni.show', $s) : route('verbali.index')" :indietro-label="$s->exists ? 'Riunione' : 'Verbali'" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Riunione non salvata.">Controlla i campi segnati.</x-avviso>
    @endif

    <form method="POST" action="{{ $s->exists ? route('verbali.riunioni.update', $s) : route('verbali.riunioni.store') }}" enctype="multipart/form-data"
          class="vb-form" x-data="{ video: @js((bool) old('videoconferenza', $s->videoconferenza)), urgente: @js((bool) old('urgente', $s->urgente)), punti: @js(array_values($punti)), inviato: false }"
          x-on:submit="inviato = true">
        @csrf
        @if ($s->exists) @method('PUT') @endif

        <x-card titolo="Quando e dove">
            <div class="form-grid">
                <x-campo name="data" label="Data" tipo="date" required :value="$s->data" :min="today()->toDateString()" />
                <x-campo name="ora_inizio" label="Ora" tipo="time" required :value="$s->ora_inizio ? substr((string) $s->ora_inizio, 0, 5) : null" />
            </div>
            <x-campo name="luogo" label="Luogo" :value="$s->luogo" maxlength="255" obbligatorio-se="! video" aiuto="Per esempio: sede della Compagnia, via …" />
            <label class="vb-spunta">
                <input type="hidden" name="videoconferenza" value="0">
                <input type="checkbox" name="videoconferenza" value="1" x-model="video">
                <span>Anche in videoconferenza</span>
            </label>
            <div x-show="video" @if (! old('videoconferenza', $s->videoconferenza)) x-cloak @endif>
                <x-campo name="collegamento" label="Collegamento" tipo="textarea" rows="2" :value="$s->collegamento" maxlength="500"
                         aiuto="Il link o le istruzioni per collegarsi: vanno nella convocazione." />
            </div>
        </x-card>

        <x-card titolo="Ordine del giorno">
            <ol class="vb-punti">
                <template x-for="(p, i) in punti" :key="i">
                    <li class="vb-punto-riga">
                        <span class="vb-punto-num" x-text="(i + 1) + '.'"></span>
                        <label class="sr-only" x-bind:for="'punto-' + i" x-text="'Punto ' + (i + 1)"></label>
                        <input type="text" class="form-input" name="punti[]" x-bind:id="'punto-' + i" x-model="punti[i]" maxlength="500" x-bind:required="i === 0">
                        <button type="button" class="action-btn" x-show="punti.length > 1" x-on:click="punti.splice(i, 1)" x-bind:aria-label="'Togli il punto ' + (i + 1)"><x-icona nome="cestino" width="14" height="14" /></button>
                    </li>
                </template>
            </ol>
            <noscript>
                @foreach ($punti as $p)<input type="text" class="form-input" name="punti[]" value="{{ $p }}" maxlength="500">@endforeach
            </noscript>
            <button type="button" class="btn btn-secondary btn-sm" x-on:click="punti.push('')" x-bind:disabled="punti.length >= 30"><x-icona nome="piu" width="14" height="14" /> Aggiungi un punto</button>
            @error('punti')<div class="form-error">{{ $message }}</div>@enderror
            <p class="form-help">Dopo la convocazione l'ordine del giorno non cambia: per cambiarlo si rinvia la riunione e la si riconvoca.</p>
        </x-card>

        <x-card titolo="Urgenza">
            <label class="vb-spunta">
                <input type="hidden" name="urgente" value="0">
                <input type="checkbox" name="urgente" value="1" x-model="urgente">
                <span>Convocazione urgente</span>
            </label>
            <p class="form-help">Di norma la convocazione parte almeno 3 giorni prima. Con l'urgenza bastano 24 ore, con il motivo (statuto, art. 16.1).</p>
            <div x-show="urgente" @if (! old('urgente', $s->urgente)) x-cloak @endif>
                <x-campo name="motivo_urgenza" label="Motivo dell'urgenza" tipo="textarea" rows="2" :value="$s->motivo_urgenza" maxlength="500" obbligatorio-se="urgente" />
            </div>
        </x-card>

        <x-card titolo="Documenti">
            @if ($documenti->isNotEmpty())
                <ul class="ct-documenti">
                    @foreach ($documenti as $d)
                        <li><a href="{{ route('verbali.riunioni.documenti.show', [$s, $d]) }}" target="_blank" rel="noopener"><x-icona nome="documento" width="16" height="16" /> {{ $d->nome }}</a>
                            <span class="testo-secondario">{{ $d->pesoTesto() }}</span></li>
                    @endforeach
                </ul>
                <p class="form-help">Si tolgono dalla scheda della riunione.</p>
            @endif
            <x-campo-giustificativi id="documenti-riunione" label="Aggiungi documenti" accept=".pdf,application/pdf"
                                    aiuto="Solo PDF, fino a 8 MB ciascuno. I membri li leggono dalla scheda della riunione." :errori="$errors->get('documenti.*')" />
        </x-card>

        <div class="form-actions form-actions-fine">
            <a href="{{ $s->exists ? route('verbali.riunioni.show', $s) : route('verbali.index') }}" class="btn btn-ghost">Annulla</a>
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Salva in bozza</button>
        </div>
    </form>
@endsection
