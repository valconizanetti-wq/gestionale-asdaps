{{--
    Form condiviso tra inserimento e modifica. Variabili: $socio, $tipi.

    Righe compatte con campi larghi quanto il contenuto (Paolo, 30/09/2026).
    Data, luogo di nascita e sesso si ricavano dal codice fiscale: si
    compilano mentre lo si scrive (anteprima pubblica del codice, che non
    legge il database) e il server li ricalcola comunque (SocioRequest).
    Residenza, email e telefono obbligatori; per un minorenne email e
    telefono diventano facoltativi e sono obbligatori tutti i dati del
    genitore o tutore ("minore" segue la data ricavata dal codice).
--}}
@php
    $nascitaIniziale = old('data_nascita', $socio->data_nascita?->format('Y-m-d'));
    $minoreIniziale = $nascitaIniziale && \Illuminate\Support\Carbon::parse($nascitaIniziale)->addYears(18)->isFuture();
@endphp

@if (session('ripristinabile'))
    @php $r = session('ripristinabile'); @endphp
    <x-avviso tipo="warn" titolo="Scheda eliminata trovata.">
        Il codice fiscale appartiene alla scheda eliminata di {{ $r['nome'] }}.
        @if (auth()->user()->haRuolo('presidente'))
            Invece di reinserirla puoi ripristinarla con tutti i suoi dati.
            <form method="POST" action="{{ route('soci.ripristina', $r['id']) }}" class="inline-form">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm"><x-icona nome="ripristina" width="14" height="14" /> Ripristina la scheda</button>
            </form>
        @else
            Solo il Presidente può ripristinarla: contattalo invece di reinserire il socio.
        @endif
    </x-avviso>
@endif

@if ($errors->any())
    <x-avviso tipo="ko" titolo="Controlla i dati." data-errori-elenco>Alcuni campi non sono validi: li trovi evidenziati qui sotto.</x-avviso>
@endif

<div class="socio-form"
     x-data="{
        minore: @js($minoreIniziale),
        erroreCf: @js($errors->first('codice_fiscale') ?: null),
        richiesta: 0,
        async leggiCf(valore) {
            const numero = ++this.richiesta;
            const cf = (valore || '').toUpperCase().replace(/\s+/g, '');
            if (cf.length !== 16) { this.erroreCf = null; return; }
            try {
                const r = await fetch(@js(route('iscrizione.codice-fiscale')), {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ cf })
                });
                if (numero !== this.richiesta || ! r.ok) { return; }
                const j = await r.json();
                if (numero !== this.richiesta) { return; }
                if (! j.valido) { this.erroreCf = j.errore; return; }
                this.erroreCf = null;
                this.$refs.nascita.value = j.data_nascita;
                this.$refs.luogo.value = j.luogo;
                this.$refs.sesso.value = j.sesso;
                this.minore = j.minorenne;
            } catch (e) {}
        }
     }">

<x-card titolo="Dati associativi">
    <div class="socio-riga socio-riga-assoc">
        <x-campo name="tipo" label="Tipo socio" tipo="select" :opzioni="$tipi" :value="$socio->tipo" required />
        <x-campo name="data_prima_iscrizione" label="Iscritto dal" tipo="date" :value="$socio->data_prima_iscrizione" required />
        <x-campo name="numero_tessera" label="N. tessera interna" :value="$socio->numero_tessera" codice />
        <div class="socio-spunta">
            <x-spunta name="scheda_registrazione_presente" label="Scheda cartacea presente"
                      :checked="$socio->scheda_registrazione_presente" />
        </div>
    </div>
    <p class="form-help">La categoria di quota non dipende dall'età; la minore età si ricava dal codice fiscale. Il numero di tessera interna è facoltativo: il numero CSEN si registra nel Tesseramento.</p>
</x-card>

<x-card titolo="Anagrafica">
    <div class="socio-riga socio-riga-nome">
        <x-campo name="cognome" label="Cognome" :value="$socio->cognome" required autocomplete="family-name" />
        <x-campo name="nome" label="Nome" :value="$socio->nome" required autocomplete="given-name" />
        {{-- Errore del codice fiscale in un solo posto, sotto la riga: quello
             del server dopo il salvataggio e quello del controllo immediato.
             Appena si modifica il codice errore e bordo spariscono (collaudo
             O, 16/10/2026: prima restava l'errore del server fino al
             salvataggio successivo). Come nel modulo della domanda. --}}
        <div class="socio-cf" x-bind:class="{ 'has-error': erroreCf }">
            <x-campo name="codice_fiscale" label="Codice fiscale" :value="$socio->codice_fiscale" required codice
                     maxlength="16" autocapitalize="characters" spellcheck="false" autocomplete="off" errore-esterno
                     aria-describedby="socio-cf-errore" x-bind:aria-invalid="erroreCf ? 'true' : 'false'"
                     x-init="if (! erroreCf) leggiCf($el.value)" x-on:input="erroreCf = null"
                     x-on:input.debounce.400ms="leggiCf($event.target.value)" />
        </div>
    </div>
    <div class="form-error" id="socio-cf-errore" x-show="erroreCf" x-text="erroreCf" @if (! $errors->has('codice_fiscale')) x-cloak @endif>{{ $errors->first('codice_fiscale') }}</div>
    <div class="socio-riga socio-riga-nascita">
        <x-campo name="data_nascita" label="Data di nascita" tipo="date" :value="$socio->data_nascita" readonly tabindex="-1" x-ref="nascita" />
        <x-campo name="luogo_nascita" label="Luogo di nascita" :value="$socio->luogo_nascita" readonly tabindex="-1" x-ref="luogo" />
        <x-campo name="sesso" label="Sesso" :value="$socio->sesso" readonly tabindex="-1" x-ref="sesso" />
        <x-campo name="nazionalita" label="Nazionalità" :value="$socio->nazionalita" />
    </div>
    <p class="form-help">Data, luogo di nascita e sesso si compilano dal codice fiscale.</p>
</x-card>

<x-card titolo="Residenza e contatti">
    <div class="socio-riga socio-riga-indirizzo">
        <x-campo name="indirizzo" label="Indirizzo" :value="$socio->indirizzo" required autocomplete="street-address" />
        <x-campo name="cap" label="CAP" :value="$socio->cap" required inputmode="numeric" maxlength="5" autocomplete="postal-code" />
        <x-campo name="citta" label="Comune" :value="$socio->citta" required autocomplete="address-level2" />
        <x-campo name="provincia" label="Prov." :value="$socio->provincia" required maxlength="2" autocapitalize="characters" />
    </div>
    <div class="socio-riga socio-riga-contatti">
        <x-campo name="email" label="Email" tipo="email" :value="$socio->email" autocomplete="email" obbligatorio-se="! minore" />
        <x-campo name="telefono" label="Telefono" tipo="tel" :value="$socio->telefono" autocomplete="tel" obbligatorio-se="! minore" />
    </div>
    <p class="form-help" x-show="minore" x-cloak>Socio minorenne: email e telefono propri sono facoltativi, le comunicazioni vanno al genitore o tutore.</p>
</x-card>

<x-card titolo="Genitore o tutore (per i soci minorenni)">
    <p class="form-help form-intro" x-show="! minore">Da compilare se il socio è minorenne: in quel caso tutti i campi sono obbligatori.</p>
    <div class="socio-riga socio-riga-tutore">
        <x-campo name="tutore_nome" label="Nome e cognome" :value="$socio->tutore_nome" obbligatorio-se="minore" />
        <x-campo name="tutore_codice_fiscale" label="Codice fiscale" :value="$socio->tutore_codice_fiscale" codice
                 maxlength="16" autocapitalize="characters" spellcheck="false" autocomplete="off" obbligatorio-se="minore" />
        <x-campo name="tutore_relazione" label="Relazione" :value="$socio->tutore_relazione" placeholder="Genitore, tutore legale…" obbligatorio-se="minore" />
    </div>
    <div class="socio-riga socio-riga-contatti">
        <x-campo name="tutore_email" label="Email" tipo="email" :value="$socio->tutore_email" obbligatorio-se="minore" />
        <x-campo name="tutore_telefono" label="Telefono" tipo="tel" :value="$socio->tutore_telefono" obbligatorio-se="minore" />
    </div>
</x-card>

<x-card titolo="Note">
    <x-campo name="note" label="Note interne" tipo="textarea" :value="$socio->note" />
</x-card>

</div>
