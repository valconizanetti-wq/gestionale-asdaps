{{--
    Intestazione di pagina. La seconda parte del titolo va in "evidenza".
    <x-page-header titolo="Gestione" evidenza="Soci" sottotitolo="..."
                   :indietro="route('soci.index')" indietro-label="Soci">
        <x-slot:azioni> ...pulsanti... </x-slot:azioni>
    </x-page-header>
--}}
@props(['titolo', 'evidenza' => null, 'sottotitolo' => null, 'indietro' => null, 'indietroLabel' => 'Indietro', 'azioni' => null])
<div class="page-header">
    <div>
        @if ($indietro)
            <a href="{{ $indietro }}" class="breadcrumb"><x-icona nome="indietro" width="12" height="12" />{{ $indietroLabel }}</a>
        @endif
        <h1 class="page-title">{{ $titolo }}@if ($evidenza) <span>{{ $evidenza }}</span>@endif</h1>
        @if ($sottotitolo)
            <p class="page-sub">{{ $sottotitolo }}</p>
        @endif
        {{ $slot }}
    </div>
    @if ($azioni)
        <div class="page-actions">{{ $azioni }}</div>
    @endif
</div>
