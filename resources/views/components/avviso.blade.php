{{--
    Avviso. Tipi: info, ok, warn, ko.
    <x-avviso tipo="warn" titolo="Tutore mancante.">Testo…</x-avviso>
--}}
@props(['tipo' => 'info', 'titolo' => null])
@php $icona = ['info' => 'info', 'ok' => 'ok', 'warn' => 'avviso', 'ko' => 'avviso'][$tipo] ?? 'info'; @endphp
<div {{ $attributes->class(['alert', 'alert-' . $tipo]) }} role="{{ in_array($tipo, ['ko', 'warn']) ? 'alert' : 'status' }}">
    <x-icona :nome="$icona" />
    <div>@if ($titolo)<strong>{{ $titolo }}</strong> @endif{{ $slot }}</div>
</div>
