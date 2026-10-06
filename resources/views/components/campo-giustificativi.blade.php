{{--
    Campo dei giustificativi (tavola PN-3): riquadro tratteggiato con un'etichetta
    per file (nome, peso, ×) e il pulsante «Aggiungi file». Si possono
    aggiungere file più volte e togliere quelli scelti per sbaglio.

    I file già caricati in un invio non riuscito (errore di validazione)
    tornano come etichette con il loro gettone (file_temp[]): non vanno
    riscelti (PrimaNotaController::trattieniFile, collaudo J4).

    <x-campo-giustificativi id="campo-documenti" label="Giustificativi" :tenuti="$tenuti" :errori="$errors->get('documenti')" />

    Comportamento in Alpine.data('campoFile') (resources/js/app.js): il campo
    file vero (documenti[]) è nascosto e viene riempito con DataTransfer.
--}}
@props(['id', 'label', 'tenuti' => [], 'errori' => [], 'aiuto' => 'JPG, PNG o PDF, fino a 8 MB ciascuno e 10 per volta. Dal telefono si può scattare una foto.'])
@php
    $peso = fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
@endphp
<div {{ $attributes->class(['form-field', 'has-error' => filled($errori)]) }} x-data="campoFile({{ count($tenuti) }})">
    <span class="form-label" id="{{ $id }}-etichetta">{{ $label }}</span>
    <div class="pn-file-box" role="group" aria-labelledby="{{ $id }}-etichetta" aria-describedby="{{ $id }}-aiuto">
        <ul class="pn-file-lista" x-show="totale > 0" @if (count($tenuti) === 0) x-cloak @endif>
            @foreach ($tenuti as $t)
                <li class="pn-file-chip" x-data="{ via: false }" x-show="! via">
                    <input type="hidden" name="file_temp[]" value="{{ $t['gettone'] }}" x-bind:disabled="via">
                    <strong>{{ $t['nome'] }}</strong>
                    <span class="pn-file-peso">{{ $peso((int) $t['peso']) }} · già caricato</span>
                    <button type="button" class="pn-file-togli" aria-label="Togli {{ $t['nome'] }}" @click="via = true; tenuti--">×</button>
                </li>
            @endforeach
            <template x-for="(f, i) in scelti" :key="f.name + f.size + i">
                <li class="pn-file-chip">
                    <strong x-text="f.name"></strong>
                    <span class="pn-file-peso" x-text="peso(f.size)"></span>
                    <button type="button" class="pn-file-togli" x-bind:aria-label="'Togli ' + f.name" @click="togli(i)">×</button>
                </li>
            </template>
        </ul>
        <span class="pn-file-vuoto" x-show="totale === 0" @if (count($tenuti) > 0) x-cloak @endif>Nessun file scelto</span>
        <label class="btn btn-secondary btn-sm pn-file-aggiungi">
            <x-icona nome="piu" width="14" height="14" /> Aggiungi file
            <input id="{{ $id }}" type="file" multiple class="sr-only" x-ref="scegli" @change="aggiungi($event)"
                   accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
        </label>
    </div>
    <input type="file" name="documenti[]" multiple class="pn-file-vero" x-ref="vero" tabindex="-1" aria-hidden="true">
    <div class="form-help" id="{{ $id }}-aiuto">{{ $aiuto }}</div>
    @foreach ((array) $errori as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
</div>
