{{--
    Card con intestazione di sezione opzionale.
    <x-card titolo="Anagrafica"> ... </x-card>
    <x-card titolo="Documenti"><x-slot:azioni>...</x-slot:azioni> ... </x-card>
    Attributo "senza-padding" per contenuti a filo (tabelle, elenchi).
--}}
@props(['titolo' => null, 'azioni' => null, 'senzaPadding' => false])
<section {{ $attributes->class('card') }}>
    @if ($titolo)
        <div class="section-header">
            <h2>{{ $titolo }}</h2>
            @if ($azioni)<div class="section-header-azioni">{{ $azioni }}</div>@endif
        </div>
    @endif
    @if ($senzaPadding)
        {{ $slot }}
    @else
        <div class="card-body">{{ $slot }}</div>
    @endif
</section>
