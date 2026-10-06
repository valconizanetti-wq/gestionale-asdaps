{{-- Badge di uno stato: colore ed etichetta da config/stati.php. --}}
@props(['dominio', 'valore'])
@php
    $def = config("stati.{$dominio}.{$valore}");
    $variante = $def['variante'] ?? 'neutral';
    $label = $def['label'] ?? \Illuminate\Support\Str::ucfirst(str_replace('_', ' ', (string) $valore));
@endphp
<x-badge :variante="$variante" {{ $attributes }}>{{ $label }}</x-badge>
