{{--
    Modale Alpine. Si apre con un evento: $dispatch('apri-modale', 'nome').
    <x-modale nome="cessazione" titolo="Cessazione del socio">
        ...contenuto...
        <x-slot:piede> ...pulsanti... </x-slot:piede>
    </x-modale>
    Su telefono sale dal basso (app.css). Esc e clic fuori la chiudono.
    larga: per testi lunghi (statuto, informativa), corpo che scorre.
--}}
@props(['nome', 'titolo', 'piede' => null, 'larga' => false])
<div x-data="{ aperta: false }"
     x-on:apri-modale.window="if ($event.detail === '{{ $nome }}') { aperta = true; $nextTick(() => $refs.pannello.focus()) }"
     x-on:keydown.escape.window="aperta = false">
    <div class="modal-backdrop" x-show="aperta" x-cloak x-transition.opacity @click.self="aperta = false">
        <div @class(['modal', 'modal-larga' => $larga]) role="dialog" aria-modal="true" aria-labelledby="modale-{{ $nome }}-titolo" x-ref="pannello" tabindex="-1">
            <div class="modal-body">
                <div class="modal-testa">
                    <h2 class="modal-title" id="modale-{{ $nome }}-titolo">{{ $titolo }}</h2>
                    <button type="button" class="action-btn" @click="aperta = false" aria-label="Chiudi"><x-icona nome="chiudi" /></button>
                </div>
                {{ $slot }}
            </div>
            @if ($piede)
                <div class="modal-footer">{{ $piede }}</div>
            @endif
        </div>
    </div>
</div>
