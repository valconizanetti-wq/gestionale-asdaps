{{--
    Campo codice fiscale con l'anteprima di data e luogo di nascita
    ricavati dal codice ("Nato il 10/05/1990 a Terni (TR)"), accanto al
    campo su desktop e sotto su telefono (classe cf-campo). L'anteprima chiede al server, che ha la tabella dei
    comuni; è solo un aiuto: al salvataggio il server ricalcola tutto.
    Parametri: $campo (nome del campo), $valore.

    Gli errori stanno nello stesso riquadro dell'anteprima, in rosso, non
    sotto il campo (collaudo A1, 29/09/2026): sia quelli del server dopo
    "Avanti" (errore) sia quelli trovati qui (codice incompleto all'uscita
    dal campo, codice non valido). Bordo rosso solo con un errore in vista;
    appena si modifica il codice errore e bordo spariscono, e con un codice
    valido compaiono data e luogo di nascita.

    La richiesta è una POST (il codice non deve finire nei log di accesso
    del server); "richiesta" scarta le risposte arrivate fuori ordine
    quando si scrive in fretta. avvisa() comunica al modulo se il codice è
    di un minorenne (null: non si sa).
--}}
@php
    $idEsito = 'cf-esito-' . str_replace(['[', ']', '.'], '-', $campo);
@endphp
<div x-data="{
        stato: null, testo: '', richiesta: 0,
        errore: @js($errors->first($campo) ?: null),
        get esito() { return this.errore ? 'ko' : this.stato; },
        get messaggio() { return this.errore || this.testo; },
        pulisci(cf) { return (cf || '').toUpperCase().replace(/\s+/g, ''); },
        modificato() { this.errore = null; this.stato = null; },
        uscita(cf) {
            cf = this.pulisci(cf);
            if (cf.length > 0 && cf.length !== 16 && ! this.errore) {
                this.stato = 'ko';
                this.testo = 'Il codice fiscale deve avere 16 caratteri: ne hai scritti ' + cf.length + '.';
            }
        },
        async verifica(cf) {
            const numero = ++this.richiesta;
            cf = this.pulisci(cf);
            if (cf.length !== 16) { this.stato = null; this.avvisa(null); return; }
            try {
                const r = await fetch(@js(route('iscrizione.codice-fiscale')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ cf })
                });
                if (numero !== this.richiesta) { return; }
                if (! r.ok) { this.stato = null; this.avvisa(null); return; }
                const j = await r.json();
                if (numero !== this.richiesta) { return; }
                this.stato = j.valido ? 'ok' : 'ko';
                this.testo = j.valido ? j.descrizione : j.errore;
                this.avvisa(j.valido ? j.minorenne : null);
            } catch (e) { if (numero === this.richiesta) { this.stato = null; this.avvisa(null); } }
        },
        avvisa(minorenne) {
            this.$dispatch('cf-verificato', { campo: @js($campo), minorenne });
        }
    }"
    x-init="verifica($el.querySelector('input').value)"
    x-on:input="modificato()"
    x-on:input.debounce.400ms="verifica($event.target.value)"
    x-on:focusout="uscita($event.target.value)"
    class="cf-campo" x-bind:class="{ 'has-error': esito === 'ko' }">
    <x-campo :name="$campo" label="Codice fiscale" required codice maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false" :value="$valore"
        errore-esterno aria-describedby="{{ $idEsito }}" x-bind:aria-invalid="esito === 'ko' ? 'true' : 'false'" />
    {{-- Esito accanto al campo (sotto, su telefono): data e luogo di nascita, oppure l'errore --}}
    <div class="cf-esito" id="{{ $idEsito }}" aria-live="polite">
        <div class="cf-anteprima" x-bind:class="esito" x-show="esito" @if (! $errors->has($campo)) x-cloak @endif>
            <span class="cf-anteprima-segno" x-show="esito === 'ok'"><x-icona nome="ok" /></span>
            <x-icona nome="avviso" x-show="esito === 'ko'" />
            <span class="cf-anteprima-testo" x-text="messaggio" x-bind:title="messaggio">{{ $errors->first($campo) }}</span>
        </div>
    </div>
</div>
