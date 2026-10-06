{{--
    Riquadro KPI. Se ha "href" diventa un link (es. filtro rapido).
    <x-kpi label="Soci attivi" :valore="26" sub="..." variante="ok|warn|ko" :attivo="true" :href="..." />
--}}
@props(['label', 'valore', 'sub' => null, 'variante' => null, 'attivo' => false, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @if ($attivo) aria-current="true" @endif @endif @class(['kpi-card', 'active' => $attivo])>
    <span class="kpi-label">{{ $label }}</span>
    <span @class(['kpi-value', $variante => $variante])>{{ $valore }}</span>
    @if ($sub)
        <span class="kpi-sub">{{ $sub }}</span>
    @endif
</{{ $tag }}>
