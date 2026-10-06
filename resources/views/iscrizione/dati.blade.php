{{--
    Pagina di iscrizione, scheda 1 "Dati e quota" (tavole "Pagina di
    iscrizione" della tela, modello Dante): istruzioni in cima, modulo a
    schede a sinistra, domande frequenti a destra.
    Data, sesso e luogo di nascita non si chiedono: li ricava il server dal
    codice fiscale. novalidate: i campi del genitore sono obbligatori solo
    per i minori, e la validazione che conta è quella del server.
--}}
@extends('layouts.pubblico')

@section('title', 'Diventa socio')

@php
    $p = $precedenti;
    $chi = old('chi', $p['chi'] ?? 'me');
@endphp

@section('content')
<div class="iscr-titolo">
    <span class="iscr-occhiello">{{ $testi['occhiello'] }}</span>
    <h1>{{ $testi['titolo'] }}</h1>
</div>

@include('iscrizione._intro')

@if ($errors->has('sessione'))
    <x-avviso tipo="warn">{{ $errors->first('sessione') }}</x-avviso>
@endif

@include('iscrizione._prova')

<div class="iscr-corpo">
    {{--
        cfMinorenne: dall'anteprima del codice fiscale di chi diventa socio
        (true, false o null se non ancora valido). Serve agli avvisi quando
        la scelta Maggiorenne / Minorenne non corrisponde all'età.
    --}}
    <form method="POST" action="{{ route('iscrizione.dati.salva') }}" novalidate class="iscr-modulo"
        x-data="{
            chi: @js($chi),
            cfMinorenne: null,
            passaA(scelta) {
                this.chi = scelta;
                this.$nextTick(() => document.getElementById(scelta === 'minore' ? 'sezione-genitore' : 'sezione-socio').scrollIntoView({ behavior: 'smooth', block: 'start' }));
            }
        }"
        x-on:cf-verificato="if ($event.detail.campo === 'codice_fiscale') cfMinorenne = $event.detail.minorenne">
        @csrf
        @include('iscrizione._schede', ['corrente' => 1])

        <div class="iscr-modulo-corpo">
            <p class="testo-secondario">I campi con * sono obbligatori.</p>

            <div class="selettore-riga">
                {{-- Etichetta solo per i lettori di schermo: a vista i due pulsanti bastano --}}
                <span id="chi-etichetta" class="sr-only">Chi si iscrive?</span>
                <div class="selettore" role="radiogroup" aria-labelledby="chi-etichetta">
                    <label><input type="radio" name="chi" value="me" x-model="chi" @checked($chi === 'me')><span>Maggiorenne</span></label>
                    <label><input type="radio" name="chi" value="minore" x-model="chi" @checked($chi === 'minore')><span>Minorenne</span></label>
                </div>
            </div>
            @error('chi')<div class="form-error">{{ $message }}</div>@enderror

            <div class="quota-fascia">
                <div class="quota-fascia-testo">
                    <span class="quota-fascia-etichetta" x-text="chi === 'minore' ? 'Socio giovane · quota annuale' : 'Socio ordinario · quota annuale'">{{ $chi === 'minore' ? 'Socio giovane' : 'Socio ordinario' }} · quota annuale</span>
                    <span class="quota-fascia-nota">Tesseramento CSEN e assicurazione base compresi.</span>
                </div>
                <span class="quota-fascia-importo" x-text="chi === 'minore' ? '{{ $quote['minorenne'] }} €' : '{{ $quote['ordinario'] }} €'">{{ $quote[$chi === 'minore' ? 'minorenne' : 'ordinario'] }} €</span>
            </div>

            {{-- Genitore: solo per i minorenni --}}
            <div id="sezione-genitore" class="iscr-gruppo" x-show="chi === 'minore'" @if ($chi !== 'minore') x-cloak @endif>
                <h2 class="iscr-sezione">Genitore che presenta la domanda</h2>
                <div class="iscr-riga-nome">
                    <x-campo name="genitore_nome" label="Nome" required autocomplete="given-name" :value="$p['genitore_nome'] ?? null" />
                    <x-campo name="genitore_cognome" label="Cognome" required autocomplete="family-name" :value="$p['genitore_cognome'] ?? null" />
                    @include('iscrizione._codice-fiscale', ['campo' => 'genitore_codice_fiscale', 'valore' => $p['genitore_codice_fiscale'] ?? null])
                </div>
                <div class="iscr-riga-contatti">
                    <x-campo name="genitore_email" label="Email" tipo="email" required autocomplete="email" :value="$p['genitore_email'] ?? null" />
                    <x-campo name="genitore_telefono" label="Telefono" tipo="tel" required autocomplete="tel" :value="$p['genitore_telefono'] ?? null" />
                </div>
            </div>

            <div id="sezione-socio" class="iscr-gruppo">
                {{-- Titolo solo per i minori, per distinguere dal blocco del genitore --}}
                <h2 class="iscr-sezione" x-show="chi === 'minore'" @if ($chi !== 'minore') x-cloak @endif>Minore che diventa socio</h2>
                <div class="iscr-riga-nome">
                    <x-campo name="nome" label="Nome" required :value="$p['nome'] ?? null" />
                    <x-campo name="cognome" label="Cognome" required :value="$p['cognome'] ?? null" />
                    @include('iscrizione._codice-fiscale', ['campo' => 'codice_fiscale', 'valore' => $p['codice_fiscale'] ?? null])
                </div>
                <x-avviso tipo="warn" titolo="È un minorenne." x-show="chi === 'me' && cfMinorenne === true" x-cloak>
                    La domanda per un minore la presenta un genitore, con i propri dati e il proprio documento.
                    I dati che hai inserito restano: aggiungi solo quelli del genitore.
                    <div class="avviso-azioni">
                        <button type="button" class="btn btn-primary btn-sm" x-on:click="passaA('minore')">Compila come genitore</button>
                    </div>
                </x-avviso>
                <x-avviso tipo="warn" titolo="È un maggiorenne." x-show="chi === 'minore' && cfMinorenne === false" x-cloak>
                    Dal codice fiscale la persona risulta maggiorenne: deve presentare la domanda per sé, senza i dati del genitore.
                    <div class="avviso-azioni">
                        <button type="button" class="btn btn-primary btn-sm" x-on:click="passaA('me')">Passa a «Maggiorenne»</button>
                    </div>
                </x-avviso>
                <div class="iscr-riga-contatti">
                    <div class="iscr-campo-nota">
                        <x-campo name="email" label="Email" tipo="email" :value="$p['email'] ?? null" obbligatorio-se="chi === 'me'" />
                        <p class="form-help" x-show="chi === 'minore'" @if ($chi !== 'minore') x-cloak @endif>Facoltativa: se manca, scriviamo al genitore.</p>
                    </div>
                    <div x-show="chi === 'me'" @if ($chi !== 'me') x-cloak @endif>
                        <x-campo name="telefono" label="Telefono" tipo="tel" autocomplete="tel" :value="$p['telefono'] ?? null" />
                    </div>
                </div>

                <h3 class="iscr-occhiello-riga">Residenza</h3>
                <div class="iscr-riga-residenza">
                    <x-campo name="indirizzo" label="Indirizzo" required autocomplete="street-address" :value="$p['indirizzo'] ?? null" />
                    <x-campo name="citta" label="Comune" required autocomplete="address-level2" :value="$p['citta'] ?? null" />
                    <x-campo name="cap" label="CAP" required inputmode="numeric" maxlength="5" autocomplete="postal-code" :value="$p['cap'] ?? null" />
                    <x-campo name="provincia" label="Prov." required maxlength="2" :value="$p['provincia'] ?? null" />
                </div>

                <h3 class="iscr-occhiello-riga">Documento d'identità<span x-show="chi === 'minore'" @if ($chi !== 'minore') x-cloak @endif> del genitore</span></h3>
                <div class="iscr-riga-documento">
                    <x-tendina name="documento_tipo" label="Tipo" required :opzioni="$documenti" vuota="Scegli…" :value="$p['documento_tipo'] ?? null" />
                    <x-campo name="documento_numero" label="Numero" required codice autocomplete="off" :value="$p['documento_numero'] ?? null" />
                </div>
            </div>
        </div>

        <div class="iscr-modulo-piede">
            <button type="submit" class="btn btn-primary">Avanti: consensi e invio <x-icona nome="avanti" width="14" height="14" /></button>
        </div>
    </form>

    @include('iscrizione._faq')
</div>
@endsection
