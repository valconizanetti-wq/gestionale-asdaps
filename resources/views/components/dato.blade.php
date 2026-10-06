{{--
    Coppia etichetta/valore in una scheda di dettaglio.
    <x-dato label="Codice fiscale" :valore="$socio->codice_fiscale" codice />
    Valore vuoto → trattino. Oppure contenuto libero nello slot.
    Gli attributi (es. class) passano al contenitore.
--}}
@props(['label', 'valore' => null, 'codice' => false])
<div {{ $attributes->class('dato') }}>
    <dt class="dato-label">{{ $label }}</dt>
    <dd @class(['dato-valore', 'code' => $codice])>
        @if (trim($slot) !== '')
            {{ $slot }}
        @elseif (filled($valore))
            {{ $valore }}
        @else
            <span class="dato-vuoto">—</span>
        @endif
    </dd>
</div>
