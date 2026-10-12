{{-- Finestra «Nuovo fornitore» / «Modifica» (tavola SN-7). $f null per il nuovo; $bag: errori di questa finestra. --}}
@php
    $err = $errors->$bag;
    $v = fn ($campo) => $err->any() ? old($campo) : $f?->$campo;
@endphp
<x-modale :nome="$nome" :titolo="$f ? 'Modifica il fornitore' : 'Nuovo fornitore'" :aperta="$err->any()">
    <form method="POST" action="{{ $f ? route('scontrini.fornitori.update', $f) : route('scontrini.fornitori.store') }}" id="form-{{ $nome }}" class="form-modale">
        @csrf
        @if ($f) @method('PUT') @endif
        @foreach (['nome' => ['Ragione sociale', 200], 'partita_iva' => ['Partita IVA', 11], 'indirizzo' => ['Indirizzo', 200], 'comune' => ['Comune', 100]] as $campo => [$etichetta, $max])
            <div @class(['form-field', 'has-error' => $err->has($campo)])>
                <label class="form-label" for="{{ $nome }}-{{ $campo }}">{{ $etichetta }}@if ($campo === 'nome')<span class="obbligatorio" aria-hidden="true">*</span>@endif</label>
                <input id="{{ $nome }}-{{ $campo }}" name="{{ $campo }}" value="{{ $v($campo) }}" maxlength="{{ $max }}" @required($campo === 'nome')
                       @class(['form-input', 'code' => $campo === 'partita_iva']) @if ($campo === 'partita_iva') inputmode="numeric" @endif>
                @foreach ($err->get($campo) as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
            </div>
        @endforeach
        @if ($f)
            <label class="sn-spunta-riga">
                <input type="checkbox" name="attivo" value="1" @checked($err->any() ? old('attivo') : $f->attivo)>
                <span>In elenco <span class="form-help">(tolto dalla lista non compare più nelle scelte; i movimenti restano)</span></span>
            </label>
            @if ($f->usi ?? 0)
                <p class="form-help">Il nome si aggiorna anche nei {{ $f->usi }} movimenti di Prima Nota.</p>
            @endif
        @endif
    </form>
    <x-slot:piede>
        @if ($f && ! ($f->usi ?? 0))
            <form method="POST" action="{{ route('scontrini.fornitori.destroy', $f) }}" class="sn-elimina-fornitore">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger-outline">Elimina</button>
            </form>
        @endif
        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
        <button type="submit" form="form-{{ $nome }}" class="btn btn-primary">Salva</button>
    </x-slot:piede>
</x-modale>
