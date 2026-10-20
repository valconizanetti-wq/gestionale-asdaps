{{--
    Registra un verbale già redatto fuori dal gestionale (v0.44.0, tavola
    VB-5; V13, V15): libro, organo, data, presidente e segretario della
    seduta, delibere in breve, numero (proposto il primo libero, o uno
    libero scelto a mano per i verbali già numerati sul libro di carta),
    copia firmata digitalmente. Nel libro delle Assemblee: «Approva il
    rendiconto dell'anno …» per la chiusura dell'anno contabile.
    Variabili: $libro, $proposti.
--}}
@extends('layouts.app')

@section('title', 'Registra un verbale già redatto')

@push('head')
    @vite('resources/js/editor.js')
@endpush

@section('content')
    <x-page-header titolo="Registra un verbale" evidenza="già redatto"
                   sottotitolo="Per le sedute verbalizzate fuori dal gestionale: entra nel libro con il suo numero, già firmato."
                   :indietro="route('verbali.index', ['libro' => $libro])" indietro-label="Verbali" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Verbale non registrato.">{{ $errors->first() }}</x-avviso>
    @endif

    <form method="POST" action="{{ route('verbali.registra.store') }}" enctype="multipart/form-data" class="vb-form"
          x-data="{ libro: @js(old('libro', $libro)), rendiconto: @js((bool) old('approva_rendiconto')), proposti: @js($proposti), inviato: false }"
          x-on:submit="inviato = true">
        @csrf

        <x-card titolo="Seduta">
            <fieldset class="scelta-gruppo">
                <legend>Libro</legend>
                <div class="scelta-opzioni">
                    @foreach (\App\Models\Verbale::LIBRI as $codice => $nome)
                        <label class="scelta">
                            <input type="radio" name="libro" value="{{ $codice }}" x-model="libro" @checked(old('libro', $libro) === $codice)>
                            <span class="scelta-testo">{{ $nome }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div x-show="libro === 'assemblee'" @if (old('libro', $libro) !== 'assemblee') x-cloak @endif>
                <x-campo name="organo" label="Assemblea" tipo="select" :opzioni="['ordinaria' => 'Ordinaria', 'straordinaria' => 'Straordinaria']" value="ordinaria" />
            </div>
            <div class="form-grid">
                <x-campo name="data" label="Data della seduta" tipo="date" required :max="today()->toDateString()" />
                <x-campo name="presenti" label="Presenti" tipo="number" min="0" max="9999" aiuto="Facoltativo: soci o consiglieri presenti." />
                <x-campo name="presidente" label="Presidente della seduta" required maxlength="150" />
                <x-campo name="segretario" label="Segretario verbalizzante" required maxlength="150" />
            </div>
            <x-campo name="titolo" label="Titolo" required maxlength="255" aiuto="Per esempio «Assemblea ordinaria dei soci del 16 novembre 2026»." />
        </x-card>

        <x-card titolo="Delibere in breve">
            <x-editor name="delibere_html" label="Delibere" required :altezza="160"
                      aiuto="Un elenco delle decisioni, una per riga: il testo completo resta nella copia firmata." />
            <div x-show="libro === 'assemblee'" @if (old('libro', $libro) !== 'assemblee') x-cloak @endif>
                <label class="vb-spunta">
                    <input type="hidden" name="approva_rendiconto" value="0">
                    <input type="checkbox" name="approva_rendiconto" value="1" x-model="rendiconto">
                    <span>Questa Assemblea approva il rendiconto di un anno</span>
                </label>
                <div x-show="rendiconto" @if (! old('approva_rendiconto')) x-cloak @endif>
                    <x-campo name="approva_rendiconto_anno" label="Anno del rendiconto" tipo="number" min="2000" max="2100" :value="now()->year - 1"
                             aiuto="Serve alla chiusura dell'anno contabile, che sceglie questo verbale." obbligatorio-se="rendiconto" />
                </div>
            </div>
        </x-card>

        <x-card titolo="Numero e copia firmata">
            <div class="form-grid">
                <x-campo name="numero" label="Numero nel libro" tipo="number" min="1" max="9999"
                         aiuto="Vuoto: il primo libero dell'anno della seduta. Scrivilo solo per riprendere il numero del libro di carta." />
            </div>
            <p class="form-help" x-text="'Nel libro ' + (libro === 'cd' ? 'del CD' : 'delle Assemblee') + ' di quest\'anno il primo numero libero è ' + proposti[libro] + '.'"></p>
            <div @class(['form-field', 'has-error' => $errors->has('firmato')])>
                <label class="form-label" for="firmato">Copia firmata digitalmente<span class="obbligatorio" aria-hidden="true">*</span></label>
                <input id="firmato" name="firmato" type="file" class="form-input" accept=".pdf,.p7m,application/pdf,application/pkcs7-mime" required aria-describedby="firmato-aiuto">
                <div class="form-help" id="firmato-aiuto">PDF firmato (PAdES) o .p7m (CAdES), fino a 10 MB: la firma del presidente e del segretario della seduta (V15).</div>
                @error('firmato')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <fieldset @class(['scelta-gruppo', 'has-error' => $errors->has('dichiarazione')])>
                <legend>Chi ha firmato</legend>
                <p class="form-help">Con due firme nel PDF il gestionale le conta da solo; scegli qui per un .p7m o se c'è una firma sola.</p>
                <div class="scelta-opzioni">
                    <label class="scelta"><input type="radio" name="dichiarazione" value="entrambi" @checked(old('dichiarazione') === 'entrambi')>
                        <span class="scelta-testo">Il presidente e il segretario</span></label>
                    <label class="scelta"><input type="radio" name="dichiarazione" value="segretario" @checked(old('dichiarazione') === 'segretario')>
                        <span class="scelta-testo">Solo il segretario verbalizzante</span></label>
                </div>
                @error('dichiarazione')<div class="form-error">{{ $message }}</div>@enderror
            </fieldset>
            <x-campo-giustificativi id="documenti-registra" label="Documenti allegati" accept=".pdf,application/pdf"
                                    aiuto="Facoltativi, solo PDF: per esempio il rendiconto approvato." :errori="$errors->get('documenti.*')" />
        </x-card>

        <div class="form-actions form-actions-fine">
            <a href="{{ route('verbali.index', ['libro' => $libro]) }}" class="btn btn-ghost">Annulla</a>
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Registra nel libro</button>
        </div>
    </form>
@endsection
