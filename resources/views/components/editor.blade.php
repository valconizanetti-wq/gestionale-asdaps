{{--
    Testo con l'editor (Jodit, v0.44.0, analisi V3). Una <textarea
    data-editor> che resources/js/editor.js trasforma nell'editor; senza
    JavaScript resta una textarea. La pagina deve caricare l'editor:
    @push('head') @vite('resources/js/editor.js') @endpush

    <x-editor name="punti[3][delibera_html]" label="Testo della delibera" :value="$p->delibera_html" required />
--}}
@props(['name', 'label', 'value' => null, 'aiuto' => null, 'required' => false, 'altezza' => 180])
@php
    $id = 'ed-' . str_replace(['[', ']', '.'], '-', $name);
    $chiave = str_replace(['[', ']'], ['.', ''], $name);
    $errore = $errors->first($chiave);
@endphp
<div @class(['form-field', 'campo-editor', 'has-error' => $errore])>
    <label class="form-label" for="{{ $id }}">{{ $label }}@if ($required)<span class="obbligatorio" aria-hidden="true">*</span>@endif</label>
    <textarea id="{{ $id }}" name="{{ $name }}" data-editor data-altezza="{{ $altezza }}" class="form-input" rows="8"
              @if ($aiuto || $errore) aria-describedby="{{ collect([$aiuto ? $id . '-aiuto' : null, $errore ? $id . '-errore' : null])->filter()->implode(' ') }}" @endif
              {{ $attributes }}>{{ old($chiave, $value) }}</textarea>
    @if ($aiuto)<div class="form-help" id="{{ $id }}-aiuto">{{ $aiuto }}</div>@endif
    @if ($errore)<div class="form-error" id="{{ $id }}-errore">{{ $errore }}</div>@endif
</div>
