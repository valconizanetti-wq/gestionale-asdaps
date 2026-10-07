{{--
    Finestra «Emetti / Rinnova la tessera» (tavola TS-3), dalla pagina Tessere.
    Si apre con $dispatch('emetti-tessera', idSocio) (null: socio da scegliere);
    dalla scheda del socio con ?emetti=<id>&da=socio. Dati proposti per socio
    in $candidati, primo numero libero per tipo in $liberi (TessereController).
--}}
@php
    $bag = $errors->getBag('emissione');
    $vecchi = $bag->any() ? [
        'socio_id' => old('socio_id'), 'tipo_id' => old('tipo_id'), 'numero' => old('numero'), 'data_rilascio' => old('data_rilascio'),
        // Con una data scelta a mano la quota può essere di un altro anno: se il
        // server ha chiesto la conferma, la casella compare comunque
        'forza' => (bool) session('serve_conferma'),
    ] : null;
    $da = $bag->any() ? old('da') : (request('da') === 'socio' ? 'socio' : null);
    $tipiUsabili = $tipi->filter(fn ($t) => $t->attivo || ($vecchi && (string) $t->id === (string) $vecchi['tipo_id']));
@endphp
<div x-data="emissioneTessera(@js($candidati), @js($liberi), @js($vecchi))"
     x-on:emetti-tessera.window="apri($event.detail)"
     @if ($apri && ! $bag->any()) x-init="$nextTick(() => apri({{ (int) $apri }}))" @endif>
    <x-modale nome="emetti-tessera" titolo="Emetti tessera" titolo-alpine="titolo" :aperta="$bag->any()">
        <form method="POST" action="{{ route('tesseramento.store') }}" class="form-modale" id="form-emetti-tessera">
            @csrf
            @if ($da)<input type="hidden" name="da" value="{{ $da }}">@endif

            @if ($bag->any())
                <x-avviso tipo="ko">{{ $bag->first('numero') ?: $bag->first() }}</x-avviso>
            @endif

            <div class="form-grid">
                <div @class(['form-field', 'has-error' => $bag->has('socio_id')])>
                    <label class="form-label" for="emetti-socio">Socio<span class="obbligatorio" aria-hidden="true">*</span></label>
                    <select id="emetti-socio" name="socio_id" class="form-select" required x-model="socio" x-on:change="proponi()">
                        <option value="">Scegli…</option>
                        @foreach ($candidati as $id => $c)
                            <option value="{{ $id }}">{{ $c['nome'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div @class(['form-field', 'has-error' => $bag->has('tipo_id')])>
                    <label class="form-label" for="emetti-tipo">Tipo<span class="obbligatorio" aria-hidden="true">*</span></label>
                    <select id="emetti-tipo" name="tipo_id" class="form-select" required x-model="tipo" x-on:change="proponiNumero()" aria-describedby="emetti-tipo-aiuto">
                        <option value="">Scegli…</option>
                        @foreach ($tipiUsabili as $t)
                            <option value="{{ $t->id }}">{{ $t->nome }}</option>
                        @endforeach
                    </select>
                    <div class="form-help" id="emetti-tipo-aiuto" x-text="dati?.rinnovo ? 'Come la tessera precedente.' : 'Dal listino CSEN.'"></div>
                </div>
            </div>

            <div class="form-grid ts-emissione-date">
                <div @class(['form-field', 'has-error' => $bag->has('numero')])>
                    <label class="form-label" for="emetti-numero">Numero<span class="obbligatorio" aria-hidden="true">*</span></label>
                    <input id="emetti-numero" name="numero" class="form-input code" required maxlength="30" x-model="numero" autocomplete="off" aria-describedby="emetti-numero-aiuto">
                    <div class="form-help" id="emetti-numero-aiuto">
                        <span x-show="libero" x-text="libero ? 'Primo libero del pacchetto ' + libero.pacchetto + '. Puoi scegliere un altro numero libero.' : ''"></span>
                        <span x-show="tipo && ! libero" x-cloak class="ts-da-fare">Nessun numero libero per questo tipo: registra un pacchetto.</span>
                    </div>
                </div>
                <div @class(['form-field', 'has-error' => $bag->has('data_rilascio')])>
                    <label class="form-label" for="emetti-data">Emessa il<span class="obbligatorio" aria-hidden="true">*</span></label>
                    <input id="emetti-data" name="data_rilascio" type="date" class="form-input" required x-model="data" x-on:change="forza = false" aria-describedby="emetti-data-aiuto">
                    <div class="form-help" id="emetti-data-aiuto" x-text="dati?.dataAiuto ?? ''"></div>
                </div>
                <div class="form-field">
                    <span class="form-label">Scade il</span>
                    <span class="pr-valore" x-text="scade || '—'"></span>
                    <div class="form-help">Un anno dall'emissione.</div>
                </div>
            </div>

            {{-- Dopo l'errore «Rinnova lo stesso» il riquadro della data proposta non vale: resta il messaggio in alto --}}
            <template x-if="dati && ! forza">
                <div class="ts-quota" x-bind:class="'ts-quota-' + dati.quota.variante">
                    <strong x-text="dati.quota.titolo"></strong>
                    <span x-text="dati.quota.testo"></span>
                </div>
            </template>

            <label class="spunta" x-show="serveConferma" x-cloak>
                <input type="checkbox" name="conferma" value="1" x-bind:required="serveConferma" x-bind:disabled="! serveConferma" @checked($bag->any() && old('conferma'))>
                <span><strong>Rinnova lo stesso</strong><span class="scelta-sub">Scelta registrata con la tessera.</span></span>
            </label>

            <label class="spunta">
                <input type="checkbox" name="portale" value="1" @checked($bag->any() && old('portale'))>
                <span><strong>Inserita anche sul portale CSEN</strong><span class="scelta-sub">Promemoria per chi la registra: si spunta quando è fatto, anche dopo dall'elenco.</span></span>
            </label>
        </form>
        <x-slot:piede>
            <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
            <button type="submit" form="form-emetti-tessera" class="btn btn-primary" x-text="numero ? 'Emetti la tessera ' + numero : 'Emetti'">Emetti</button>
        </x-slot:piede>
    </x-modale>
</div>
