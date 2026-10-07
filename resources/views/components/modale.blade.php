{{--
    Modale Alpine. Si apre con un evento: $dispatch('apri-modale', 'nome').
    <x-modale nome="cessazione" titolo="Cessazione del socio">
        ...contenuto...
        <x-slot:piede> ...pulsanti... </x-slot:piede>
    </x-modale>
    Su telefono sale dal basso (app.css). Esc e clic fuori la chiudono.
    larga: per testi lunghi (statuto, informativa), corpo che scorre.
    aperta: già aperta al caricamento (es. dopo un errore di validazione,
    per mostrare l'errore nella finestra invece di chiuderla).
    titolo-alpine: espressione Alpine per un titolo che cambia (es. «Rinnova
    la tessera di …», tavola TS-3), valutata nello scope di chi contiene la
    finestra; titolo resta il testo iniziale.
--}}
@props(['nome', 'titolo', 'piede' => null, 'larga' => false, 'aperta' => false, 'titoloAlpine' => null])
<div x-data="{ aperta: @js((bool) $aperta) }"
     @if ($aperta) x-init="$nextTick(() => $refs.pannello.focus())" @endif
     x-on:apri-modale.window="if ($event.detail === '{{ $nome }}') { aperta = true; $nextTick(() => $refs.pannello.focus()) }"
     x-on:keydown.escape.window="aperta = false">
    <div class="modal-backdrop" x-show="aperta" x-cloak x-transition.opacity @click.self="aperta = false">
        <div @class(['modal', 'modal-larga' => $larga]) role="dialog" aria-modal="true" aria-labelledby="modale-{{ $nome }}-titolo" x-ref="pannello" tabindex="-1">
            <div class="modal-body">
                <div class="modal-testa">
                    <h2 class="modal-title" id="modale-{{ $nome }}-titolo" @if ($titoloAlpine) x-text="{{ $titoloAlpine }}" @endif>{{ $titolo }}</h2>
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
