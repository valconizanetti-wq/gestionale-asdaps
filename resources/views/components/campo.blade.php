{{--
    Campo di form completo: etichetta, controllo, aiuto, errore.
    Recupera da solo old() ed errori di validazione.

    <x-campo name="cognome" label="Cognome" required :value="$socio->cognome" />
    <x-campo name="tipo" label="Tipo" tipo="select" :opzioni="['ordinario' => 'Ordinario']" :value="..." />
    <x-campo name="note" label="Note" tipo="textarea" :value="..." />
    <x-campo name="codice_fiscale" label="Codice fiscale" codice aiuto="16 caratteri" />

    Obbligatorio solo in certe condizioni (espressione Alpine): asterisco e
    attributo required seguono l'espressione.
    <x-campo name="email" label="Email" obbligatorio-se="chi === 'me'" />

    Errore mostrato altrove (es. codice fiscale, nella riga dell'anteprima):
    niente testo sotto il campo e niente bordo rosso da qui; li gestisce chi
    include il campo.
    <x-campo name="codice_fiscale" label="Codice fiscale" errore-esterno />

    Gli attributi non riconosciuti (maxlength, autocomplete, x-model...) passano al controllo.
--}}
@props([
    'name',
    'label',
    'tipo' => 'text',
    'value' => null,
    'opzioni' => [],
    'vuota' => null,          // etichetta dell'opzione vuota di una select
    'aiuto' => null,
    'required' => false,
    'obbligatorioSe' => null, // espressione Alpine: obbligatorio solo se vera
    'codice' => false,        // font monospace (codici fiscali, tessere)
    'erroreEsterno' => false, // errore mostrato fuori dal componente
])
@php
    $id = 'campo-' . str_replace(['[', ']', '.'], '-', $name);
    $chiave = str_replace(['[', ']'], ['.', ''], $name);
    $errore = $erroreEsterno ? null : $errors->first($chiave);
    $valore = old($chiave, $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value);
    $descritto = collect([$aiuto ? "{$id}-aiuto" : null, $errore ? "{$id}-errore" : null])->filter()->implode(' ');
@endphp
<div @class(['form-field', 'has-error' => $errore])>
    <label class="form-label" for="{{ $id }}">
        {{ $label }}@if ($required)<span class="obbligatorio" aria-hidden="true">*</span>@elseif ($obbligatorioSe)<span class="obbligatorio" aria-hidden="true" x-show="{{ $obbligatorioSe }}" x-cloak>*</span>@endif
    </label>

    @if ($tipo === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @required($required)
            @if ($obbligatorioSe) x-bind:required="{{ $obbligatorioSe }}" @endif
            @if ($descritto) aria-describedby="{{ $descritto }}" @endif
            @if ($errore) aria-invalid="true" @endif
            {{ $attributes->class('form-select') }}>
            @if ($vuota !== null)<option value="">{{ $vuota }}</option>@endif
            @foreach ($opzioni as $chiaveOpz => $labelOpz)
                <option value="{{ $chiaveOpz }}" @selected((string) $valore === (string) $chiaveOpz)>{{ $labelOpz }}</option>
            @endforeach
        </select>
    @elseif ($tipo === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" @required($required)
            @if ($obbligatorioSe) x-bind:required="{{ $obbligatorioSe }}" @endif
            @if ($descritto) aria-describedby="{{ $descritto }}" @endif
            @if ($errore) aria-invalid="true" @endif
            {{ $attributes->class('form-textarea') }}>{{ $valore }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $tipo }}" value="{{ $valore }}" @required($required)
            @if ($obbligatorioSe) x-bind:required="{{ $obbligatorioSe }}" @endif
            @if ($descritto) aria-describedby="{{ $descritto }}" @endif
            @if ($errore) aria-invalid="true" @endif
            {{ $attributes->class(['form-input', 'code' => $codice]) }}>
    @endif

    @if ($aiuto)
        <div class="form-help" id="{{ $id }}-aiuto">{{ $aiuto }}</div>
    @endif
    @if ($errore)
        <div class="form-error" id="{{ $id }}-errore">{{ $errore }}</div>
    @endif
</div>
