{{--
    Pagina di iscrizione, scheda 2 "Consensi e invio" (tavole "Pagina di
    iscrizione — scheda 2" della tela): riepilogo compatto dei dati e
    dichiarazioni. Una sola dichiarazione obbligatoria copre domanda,
    statuto con clausola arbitrale, tesseramento CSEN, polizza e, per i
    minori, autorizzazione alle attività (domande-iscrizione.md §3).
    Minore che ha compiuto 14 anni: anche la sua dichiarazione, obbligatoria
    (assenso al tesseramento, art. 16, c. 2, D.Lgs. 36/2021; v0.31.0, tavole
    "Assenso del minore"). La frase su foto e video compare solo se il
    genitore ha spuntato quel consenso.
    "Perché associarsi" e "Come iscriversi" non si ripetono: chi arriva qui
    li ha appena letti, e il pulsante di invio resta a portata di vista.

    Statuto e informativa: link al testo accanto alla dichiarazione, non
    obbligatorio aprirlo (decisione di Paolo, 29/09/2026). Su desktop si
    apre in una finestra sulla pagina, su telefono nella sua pagina, che ha
    il link per tornare qui e registra l'apertura in sessione ($letti).
    Le spunte restano nel browser (sessionStorage) mentre si va e si torna
    dal documento.
--}}
@extends('layouts.pubblico')

@section('title', 'Consensi e invio')

@php
    $minore = $d['minore'];
    $nomeSocio = $d['nome'] . ' ' . $d['cognome'];
    $nato = $d['sesso'] === 'F' ? 'Nata il' : 'Nato il';
    $dataNascita = \Carbon\Carbon::parse($d['data_nascita'])->format('d/m/Y');
    $quota = $quote[$minore ? 'minorenne' : 'ordinario'];
    $ammesso = $d['sesso'] === 'F' ? 'ammessa' : 'ammesso';
    $documento = ($documenti[$d['documento_tipo']] ?? $d['documento_tipo']) . ' n. ' . $d['documento_numero'];
    [$assensoBase, $assensoFoto] = \App\Models\Domanda::partiTestoAssenso($d['nome'] . ' ' . $d['cognome']);
@endphp

@section('content')
<div class="iscr-titolo">
    <span class="iscr-occhiello">{{ $testi['occhiello'] }}</span>
    <h1>{{ $testi['titolo'] }}</h1>
</div>

@include('iscrizione._prova')

<div class="iscr-corpo">
    <form method="POST" action="{{ route('iscrizione.invia') }}" class="iscr-modulo"
        x-data="{
            statuto: @js(isset($letti['statuto']) || (bool) old('statuto_aperto')),
            informativa: @js(isset($letti['informativa']) || (bool) old('informativa_aperta')),
            foto: @js((bool) old('foto_video')),
            inviato: false,
            chiave: 'iscrizione.spunte',
            apri(doc, evento) {
                this[doc] = true;
                if (window.matchMedia('(min-width: 769px)').matches) {
                    evento.preventDefault();
                    this.$dispatch('apri-modale', doc);
                }
            },
            ricorda() {
                const spunte = {};
                this.$el.querySelectorAll('input[type=checkbox]').forEach(c => spunte[c.name] = c.checked);
                try { sessionStorage.setItem(this.chiave, JSON.stringify(spunte)); } catch (e) {}
            },
            ripristina() {
                let spunte = null;
                try { spunte = JSON.parse(sessionStorage.getItem(this.chiave) || 'null'); } catch (e) {}
                if (! spunte) { return; }
                this.$el.querySelectorAll('input[type=checkbox]').forEach(c => { if (c.name in spunte) { c.checked = spunte[c.name]; } });
                this.foto = this.$refs.foto ? this.$refs.foto.checked : false;
            }
        }"
        @if (! $errors->any()) x-init="ripristina()" @endif
        x-on:change="ricorda()"
        x-on:submit="inviato = true; try { sessionStorage.removeItem(chiave); } catch (e) {}">
        @csrf
        <input type="hidden" name="impronta_testi" value="{{ $impronta }}">
        @include('iscrizione._schede', ['corrente' => 2])
        @error('impronta_testi')
            <x-avviso tipo="warn" titolo="Testi aggiornati.">{{ $message }}</x-avviso>
        @enderror

        <div class="iscr-modulo-corpo">
            <div class="quota-fascia">
                <div class="quota-fascia-testo">
                    <span class="quota-fascia-etichetta">{{ $minore ? 'Socio giovane' : 'Socio ordinario' }} · quota annuale</span>
                    <span class="quota-fascia-nota">Tesseramento CSEN e assicurazione base compresi.</span>
                </div>
                <span class="quota-fascia-importo">{{ $quota }} €</span>
            </div>

            @if ($d['nome_non_corrisponde'] || ($d['genitore_nome_non_corrisponde'] ?? false))
                <x-avviso tipo="warn" titolo="Controlla nomi e codici fiscali.">
                    Le lettere del codice fiscale non corrispondono a nome e cognome{{ $minore ? ' di una delle due persone' : '' }}. Può succedere, ma di solito è un errore di battitura.
                </x-avviso>
            @endif

            @if ($minore)
                <section class="iscr-riepilogo" aria-labelledby="riep-genitore">
                    <div class="iscr-riepilogo-testa">
                        <h2 id="riep-genitore">Genitore</h2>
                        <a href="{{ route('iscrizione.dati') }}">Modifica</a>
                    </div>
                    <dl class="iscr-dati">
                        <x-dato label="Nome e cognome" :valore="$d['genitore_nome']" />
                        <x-dato label="Codice fiscale" :valore="$d['genitore_codice_fiscale']" codice />
                        <x-dato label="Documento" :valore="$documento" />
                        <x-dato label="Email" :valore="$d['genitore_email']" />
                        <x-dato label="Telefono" :valore="$d['genitore_telefono']" />
                    </dl>
                </section>
            @endif

            {{-- Titolo solo per i minori, per distinguere dal riquadro del genitore --}}
            <section class="iscr-riepilogo" @if ($minore) aria-labelledby="riep-socio" @else aria-label="I tuoi dati" @endif>
                <div class="iscr-riepilogo-testa">
                    @if ($minore)
                        <h2 id="riep-socio">Minore che diventa socio</h2>
                    @endif
                    <a href="{{ route('iscrizione.dati') }}">Modifica</a>
                </div>
                <dl class="iscr-dati">
                    <x-dato label="Nome e cognome" :valore="$nomeSocio" />
                    <x-dato label="Codice fiscale" :valore="$d['codice_fiscale']" codice />
                    <x-dato :label="$nato" :valore="$dataNascita . ' a ' . $d['luogo_nascita']" />
                    @if ($minore)
                        <x-dato label="Email" :valore="$d['email_propria_minore'] ? $d['email'] : 'Nessuna: comunicazioni al genitore'" />
                    @else
                        <x-dato label="Email" :valore="$d['email']" />
                        <x-dato label="Telefono" :valore="$d['telefono']" />
                        <x-dato label="Documento" :valore="$documento" />
                    @endif
                    <x-dato label="Residenza" :valore="$d['indirizzo'] . ', ' . $d['cap'] . ' ' . $d['citta'] . ' (' . $d['provincia'] . ')'" class="iscr-dati-intero" />
                </dl>
            </section>

            <h2 class="iscr-sezione">Dichiarazioni</h2>

            <div @class(['has-error' => $errors->has('dichiarazione')])>
                <div class="dichiarazione principale">
                    <input type="checkbox" id="dichiarazione" name="dichiarazione" value="1" @checked(old('dichiarazione'))
                        @error('dichiarazione') aria-invalid="true" aria-describedby="dichiarazione-errore" @enderror>
                    <div class="dichiarazione-corpo">
                        <label for="dichiarazione"><strong>
                            @if ($minore)
                                Chiedo che {{ $nomeSocio }} sia {{ $ammesso }} come socio giovane e dichiaro che:
                            @else
                                Chiedo di essere {{ $ammesso }} come socio ordinario e dichiaro che:
                            @endif
                        </strong> <span class="obbligatorio" aria-hidden="true">*</span></label>
                        <ul class="dichiarazione-elenco">
                            <li>ho letto e accetto lo statuto (versione {{ $versioneStatuto }}) e i regolamenti, compresa la clausola arbitrale dell'art. 30;</li>
                            <li>chiedo il tesseramento al CSEN, Ente di promozione sportiva riconosciuto dal CONI, per l'anno in corso;</li>
                            <li>ho preso visione delle condizioni della polizza infortuni CSEN base, compresa nella quota (<a href="https://www.csen.it/assicurazioni/" target="_blank" rel="noopener">condizioni</a>);</li>
                            @if ($minore)
                                <li>autorizzo il minore a partecipare alle attività sportive dell'Associazione;</li>
                            @endif
                            <li>so che l'Assemblea dei soci si riunisce ogni anno entro il 30 aprile per il rendiconto.</li>
                        </ul>
                        <div class="dichiarazione-apri">
                            <a href="{{ route('statuto') }}" x-on:click="apri('statuto', $event)">Leggi lo statuto</a>
                            <span class="badge badge-ok" x-show="statuto" x-cloak>Aperto</span>
                        </div>
                    </div>
                </div>
                @error('dichiarazione')<div class="form-error" id="dichiarazione-errore">{{ $message }}</div>@enderror
            </div>

            <input type="hidden" name="statuto_aperto" x-bind:value="statuto ? 1 : 0" value="0">
            <input type="hidden" name="informativa_aperta" x-bind:value="informativa ? 1 : 0" value="0">
            <div @class(['has-error' => $errors->has('informativa')])>
                <div class="dichiarazione">
                    <input type="checkbox" id="informativa" name="informativa" value="1" @checked(old('informativa'))
                        @error('informativa') aria-invalid="true" aria-describedby="informativa-errore" @enderror>
                    <div class="dichiarazione-corpo">
                        <label for="informativa">Dichiaro di aver letto l'informativa sul trattamento dei dati personali (versione {{ $versioneInformativa }}){{ $minore ? ', in qualità di genitore del minore' : '' }}. <span class="obbligatorio" aria-hidden="true">*</span></label>
                        <div class="dichiarazione-apri">
                            <a href="{{ route('informativa') }}" x-on:click="apri('informativa', $event)">Leggi l'informativa</a>
                            <span class="badge badge-ok" x-show="informativa" x-cloak>Aperta</span>
                        </div>
                    </div>
                </div>
                @error('informativa')<div class="form-error" id="informativa-errore">{{ $message }}</div>@enderror
            </div>

            <div class="dichiarazione">
                <input type="checkbox" id="foto_video" name="foto_video" value="1" @checked(old('foto_video'))
                    x-ref="foto" x-on:change="foto = $event.target.checked">
                <div class="dichiarazione-corpo">
                    <label for="foto_video">Acconsento alla pubblicazione di foto e video che {{ $minore ? 'ritraggono il minore' : 'mi ritraggono' }} durante le attività, sul sito e sui canali social dell'Associazione. Posso revocare il consenso in ogni momento.</label>
                    <div><x-badge>Facoltativo</x-badge></div>
                </div>
            </div>

            @if ($assenso)
                {{-- Dichiarazione del minore che ha compiuto 14 anni: senza, il tesseramento non può avvenire --}}
                <div @class(['has-error' => $errors->has('assenso_minore')])>
                    <div class="dichiarazione principale">
                        <input type="checkbox" id="assenso_minore" name="assenso_minore" value="1" @checked(old('assenso_minore'))
                            @error('assenso_minore') aria-invalid="true" aria-describedby="assenso_minore-errore" @enderror>
                        <div class="dichiarazione-corpo">
                            <div class="dichiarazione-testa">
                                <span class="dichiarazione-etichetta">Dichiarazione del minore</span>
                                <x-badge variante="ok">{{ $d['nome'] }} ha {{ $etaMinore }} anni</x-badge>
                            </div>
                            <label for="assenso_minore"><strong>{{ $assensoBase }}<span x-show="foto" x-cloak>{{ $assensoFoto }}</span>.</strong> <span class="obbligatorio" aria-hidden="true">*</span></label>
                            <p class="dichiarazione-nota">La spunta la mette {{ $d['nome'] }}, o il genitore dopo {{ $d['sesso'] === 'F' ? 'averle' : 'avergli' }} chiesto se è d'accordo.</p>
                            <p class="norma">{{ \App\Models\Domanda::NORMA_ASSENSO }} <span x-show="foto" x-cloak>{{ \App\Models\Domanda::NORMA_IMMAGINE }}</span></p>
                        </div>
                    </div>
                    @error('assenso_minore')<div class="form-error" id="assenso_minore-errore">{{ $message }}</div>@enderror
                </div>
            @endif

            {{-- Campo trappola per i bot: nascosto a chi usa il modulo --}}
            <div class="campo-trappola" aria-hidden="true">
                <label for="sito_web">Non compilare</label>
                <input type="text" id="sito_web" name="sito_web" tabindex="-1" autocomplete="off">
            </div>

            <p class="testo-secondario">Con l'invio la domanda è presentata e passa al Consiglio Direttivo. Riceverai per email la ricevuta in PDF.</p>
        </div>

        <div class="iscr-modulo-piede tra">
            <a href="{{ route('iscrizione.dati') }}" class="iscr-indietro"><x-icona nome="indietro" width="14" height="14" /> Modifica i dati</a>
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Invia la domanda</button>
        </div>
    </form>

    @include('iscrizione._faq')
</div>

{{-- Finestre dei testi (desktop). Su telefono i link aprono la pagina del documento. --}}
@foreach (['statuto' => ['Statuto', $versioneStatuto, $statutoHtml], 'informativa' => ['Informativa privacy', $versioneInformativa, $informativaHtml]] as $nome => [$titoloDoc, $versioneDoc, $htmlDoc])
    <x-modale :nome="$nome" :titolo="$titoloDoc . ' — versione ' . $versioneDoc" larga>
        <article class="documento-testo">{!! $htmlDoc !!}</article>
        <x-slot:piede>
            <button type="button" class="btn btn-primary" x-on:click="aperta = false">Ho letto, chiudi</button>
        </x-slot:piede>
    </x-modale>
@endforeach
@endsection
