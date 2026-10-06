{{-- Badge generico. Varianti: ok, warn, ko, info, neutral. --}}
@props(['variante' => 'neutral'])
<span {{ $attributes->class(['badge', 'badge-' . $variante]) }}>{{ $slot }}</span>
