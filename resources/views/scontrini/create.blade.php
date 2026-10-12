{{--
    Passo 1 dal telefono (tavola SN-1): foto (fotocamera posteriore) o file,
    e nota obbligatoria. «Leggi lo scontrino» manda la foto alla lettura e
    porta al passo 2; nel database non si scrive nulla fino a «Registra».
--}}
@extends('layouts.app')

@section('title', 'Nuovo scontrino')

@section('content')
    <x-page-header titolo="Nuovo" evidenza="scontrino"
                   :sottotitolo="'Anno ' . $anno . ' · lo vedi entrare in Prima Nota; la categoria la sceglie il Tesoriere.'"
                   :indietro="route(auth()->user()->puoModificare('prima_nota') ? 'scontrini.index' : 'scontrini.miei')" indietro-label="Scontrini" />

    @if ($anno < $avvio)
        <x-avviso tipo="warn" titolo="Contabilità dal {{ $avvio }}.">Gli scontrini del {{ $anno }} si registrano ancora nel vecchio gestionale: qui si può solo provare (la registrazione chiede una data dal {{ $avvio }} o un anno contabile {{ $anno }} di prova).</x-avviso>
    @endif

    @if ($chiuso)
        <x-avviso tipo="warn" titolo="Anno {{ $anno }} chiuso.">Gli scontrini di quest'anno non si registrano più: scrivi al Tesoriere.</x-avviso>
    @endif

    <form method="POST" action="{{ route('scontrini.leggi') }}" enctype="multipart/form-data" class="sn-passo"
          x-data="{ nome: '', peso: '', anteprima: null, invio: false, fonte: '',
                    scelto(e, fonte) { const f = e.target.files[0]; if (! f) return;
                        this.fonte = fonte;
                        this.nome = f.name; this.peso = (f.size / 1048576).toFixed(1).replace('.', ',') + ' MB';
                        this.anteprima = f.type.startsWith('image/') ? URL.createObjectURL(f) : null; } }"
          x-on:submit="invio = true; $el.querySelectorAll('input[name=foto]').forEach(i => i.disabled = i.dataset.fonte !== fonte)">
        @csrf
        {{-- Due campi «foto» (fotocamera e file): si invia solo quello usato per ultimo, l'altro è disattivato --}}

        <label @class(['sn-foto', 'has-error' => $errors->has('foto')]) x-bind:class="{ 'sn-foto-scelta': nome }">
            <input type="file" name="foto" accept="image/*" capture="environment" class="sr-only" data-fonte="foto" x-on:change="scelto($event, 'foto')">
            <template x-if="anteprima"><img x-bind:src="anteprima" alt="Foto scelta" class="sn-foto-img"></template>
            <span class="sn-foto-icona" x-show="! anteprima"><x-icona nome="fotocamera" width="34" height="34" /></span>
            <strong x-text="nome ? 'Rifai la foto' : 'Fotografa lo scontrino'">Fotografa lo scontrino</strong>
            <span class="sn-foto-sub" x-text="nome ? nome + ' · ' + peso : 'Intero, dritto e ben illuminato: una foto basta'">Intero, dritto e ben illuminato: una foto basta</span>
        </label>

        <label class="btn btn-secondary sn-file">
            <input type="file" name="foto" accept="image/jpeg,image/png,application/pdf,.pdf" class="sr-only" data-fonte="file" x-on:change="scelto($event, 'file')">
            Scegli un file (foto o PDF)
        </label>
        @error('foto')<div class="form-error" role="alert">{{ $message }}</div>@enderror
        <p class="form-help">JPG, PNG o PDF, fino a 8 MB. Su iPhone, se la foto non viene accettata: Impostazioni › Fotocamera › Formati › «Più compatibile».</p>

        <x-campo name="nota" label="Nota: che cosa hai comprato" tipo="textarea" required rows="3" maxlength="500"
                 aiuto="Se ci sono spese diverse scrivile tutte: aiuta il Tesoriere a dividerle." />

        <button type="submit" class="btn btn-primary sn-pulsante" x-bind:disabled="invio || ! nome" @disabled($chiuso)>
            <span x-show="! invio">Leggi lo scontrino</span>
            <span x-show="invio" x-cloak>Sto leggendo lo scontrino…</span>
        </button>
        <p class="form-help">Totale, data, negozio e righe si leggono dalla foto: al passo dopo li controlli e li correggi.</p>
    </form>
@endsection
