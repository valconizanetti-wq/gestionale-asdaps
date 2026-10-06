{{--
    Tendina nello stile del sito, al posto della <select> nativa (il cui
    elenco il browser disegna con lo stile di sistema: collaudo A1,
    29/09/2026). Pochi valori fissi; il valore va al server con un campo
    nascosto. Stessa etichetta, asterisco ed errore di <x-campo>.

    <x-tendina name="documento_tipo" label="Tipo" required :opzioni="$documenti" vuota="Scegli…" :value="..." />

    Tastiera: frecce su/giù aprono e scorrono, Invio o spazio scelgono, Esc
    chiude, Tab esce. Ruoli ARIA di una listbox; clic fuori chiude.
--}}
@props([
    'name',
    'label',
    'opzioni' => [],
    'value' => null,
    'vuota' => 'Scegli…',
    'required' => false,
])
@php
    $id = 'campo-' . str_replace(['[', ']', '.'], '-', $name);
    $chiave = str_replace(['[', ']'], ['.', ''], $name);
    $errore = $errors->first($chiave);
    $valore = (string) old($chiave, $value);
    $voci = collect($opzioni)->map(fn ($etichetta, $valoreOpz) => ['valore' => (string) $valoreOpz, 'etichetta' => $etichetta])->values();
@endphp
<div @class(['form-field', 'has-error' => $errore])
    x-data="{
        aperta: false,
        voci: @js($voci),
        valore: @js($valore),
        attiva: -1,
        get scelta() { return this.voci.find(v => v.valore === this.valore) || null; },
        apri() {
            this.aperta = true;
            this.attiva = Math.max(0, this.voci.findIndex(v => v.valore === this.valore));
        },
        chiudi(rifocalizza = false) { this.aperta = false; if (rifocalizza) { this.$refs.pulsante.focus(); } },
        scegli(i) {
            this.valore = this.voci[i].valore;
            this.chiudi(true);
            this.$nextTick(() => this.$refs.nascosto.dispatchEvent(new Event('change', { bubbles: true })));
        },
        sposta(passo) {
            if (! this.aperta) { this.apri(); return; }
            this.attiva = (this.attiva + passo + this.voci.length) % this.voci.length;
        }
    }"
    x-on:click.outside="chiudi()"
    x-on:keydown.escape.prevent.stop="chiudi(true)">
    <label class="form-label" id="{{ $id }}-etichetta" for="{{ $id }}">
        {{ $label }}@if ($required)<span class="obbligatorio" aria-hidden="true">*</span>@endif
    </label>

    <div class="tendina">
        <input type="hidden" name="{{ $name }}" x-ref="nascosto" x-bind:value="valore" value="{{ $valore }}">
        <button type="button" id="{{ $id }}" x-ref="pulsante" class="form-input tendina-pulsante"
            aria-haspopup="listbox" x-bind:aria-expanded="aperta ? 'true' : 'false'"
            aria-labelledby="{{ $id }}-etichetta {{ $id }}"
            @if ($errore) aria-invalid="true" aria-describedby="{{ $id }}-errore" @endif
            x-on:click="aperta ? chiudi() : apri()"
            x-on:keydown.arrow-down.prevent="sposta(1)"
            x-on:keydown.arrow-up.prevent="sposta(-1)"
            x-on:keydown.enter.prevent="aperta && attiva >= 0 ? scegli(attiva) : apri()"
            x-on:keydown.space.prevent="aperta && attiva >= 0 ? scegli(attiva) : apri()"
            x-on:keydown.tab="chiudi()">
            <span class="tendina-valore" x-bind:class="{ vuota: ! scelta }" x-text="scelta ? scelta.etichetta : @js($vuota)">{{ $voci->firstWhere('valore', $valore)['etichetta'] ?? $vuota }}</span>
            <x-icona nome="giu" class="tendina-freccia" />
        </button>

        <ul class="tendina-lista" role="listbox" aria-labelledby="{{ $id }}-etichetta" x-show="aperta" x-cloak x-transition.opacity.duration.100ms>
            <template x-for="(voce, i) in voci" x-bind:key="voce.valore">
                <li role="option" class="tendina-voce"
                    x-bind:aria-selected="voce.valore === valore ? 'true' : 'false'"
                    x-bind:class="{ attiva: i === attiva, scelta: voce.valore === valore }"
                    x-on:mouseenter="attiva = i"
                    x-on:mousedown.prevent
                    x-on:click="scegli(i)">
                    <span x-text="voce.etichetta"></span>
                    <x-icona nome="ok" class="tendina-spunta" x-show="voce.valore === valore" />
                </li>
            </template>
        </ul>
    </div>

    @if ($errore)
        <div class="form-error" id="{{ $id }}-errore">{{ $errore }}</div>
    @endif
</div>
