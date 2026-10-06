@extends('layouts.app')

@section('title', 'Registra quota')

@use('App\Support\Importo')
@use('App\Models\Rinnovo')
@use('App\Services\RinnoviQuota')

@section('content')
    @php
        $giovane = $categoria === 'giovane';
        $metodo = old('metodo', 'bonifico');
        $indietro = $da === 'socio' && $socio ? route('soci.show', $socio) . '#quote' : route('rinnovi.index');
    @endphp

    <x-page-header titolo="Registra" evidenza="quota" sottotitolo="Ricevuta numerata, movimento in Prima Nota e email con la ricevuta, in un solo passaggio"
                   :indietro="$indietro" :indietro-label="$da === 'socio' && $socio ? $socio->cognome . ' ' . $socio->nome : 'Rinnovi quota'" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Controlla i dati.">Alcuni campi non sono validi: li trovi evidenziati qui sotto.</x-avviso>
    @endif

    <div class="pn-colonne">
        <div class="pn-colonna">
            {{-- Socio e anno: cambiandoli la pagina si ricarica con categoria, importo e assenso giusti --}}
            <x-card titolo="Socio">
                <form method="GET" action="{{ route('rinnovi.create') }}" class="form-grid" x-data x-ref="scelta">
                    @if ($da)<input type="hidden" name="da" value="{{ $da }}">@endif
                    <div class="form-field">
                        <label class="form-label" for="scegli-socio">Socio<span class="obbligatorio" aria-hidden="true">*</span></label>
                        <select id="scegli-socio" name="socio" class="form-select" x-on:change="$refs.scelta.submit()" aria-describedby="scegli-socio-aiuto">
                            <option value="">Scegli…</option>
                            @foreach ($soci as $s)
                                <option value="{{ $s->id }}" @selected($socio?->id === $s->id)>{{ $s->cognome }} {{ $s->nome }}</option>
                            @endforeach
                        </select>
                        <div class="form-help" id="scegli-socio-aiuto">Soci attivi. Dalla scheda del socio è già scelto.</div>
                    </div>
                    @if ($socio)
                        <div class="form-field">
                            <label class="form-label" for="scegli-anno">Anno sociale<span class="obbligatorio" aria-hidden="true">*</span></label>
                            <select id="scegli-anno" name="anno" class="form-select" x-on:change="$refs.scelta.submit()" aria-describedby="scegli-anno-aiuto">
                                @foreach (range(now()->year - 1, now()->year + 1) as $a)
                                    <option value="{{ $a }}" @selected($a === $anno)>{{ $a }}</option>
                                @endforeach
                            </select>
                            <div class="form-help" id="scegli-anno-aiuto">Proposto il primo anno non ancora versato.</div>
                        </div>
                    @endif
                    <noscript><button type="submit" class="btn btn-secondary btn-sm">Scegli</button></noscript>
                </form>
            </x-card>

            @if ($socio && $giaVersata)
                <x-avviso tipo="info" titolo="Quota {{ $anno }} già registrata.">
                    Ricevuta n. {{ $giaVersata->numero_ricevuta }} del {{ $giaVersata->data_pagamento->format('d/m/Y') }}. Per correggerla si annulla dalla scheda del socio e si registra di nuovo.
                </x-avviso>
            @elseif ($socio)
                <form method="POST" action="{{ route('rinnovi.store') }}" class="pn-colonna" x-data="{ metodo: @js($metodo), inviato: false }" x-on:submit="inviato = true">
                    @csrf
                    <input type="hidden" name="socio_id" value="{{ $socio->id }}">
                    <input type="hidden" name="anno" value="{{ $anno }}">
                    @if ($da)<input type="hidden" name="da" value="{{ $da }}">@endif

                    @if ($serveAssenso)
                        <x-card titolo="Assenso del minore">
                            <x-avviso tipo="info" titolo="Socio giovane dai 14 anni.">
                                Al tesseramento serve anche l'assenso del minore. <span class="rq-norma">D.Lgs. 36/2021, art. 16, c. 2; statuto art. 6.3.</span>
                            </x-avviso>
                            <label @class(['spunta', 'has-error' => $errors->has('assenso')])>
                                <input type="checkbox" name="assenso" value="1" @checked(old('assenso')) required>
                                <span><strong>{{ $socio->nome }} ha dato il suo assenso al rinnovo</strong>
                                    <span class="scelta-sub">Raccolto dal genitore o in sede. Si registra con la quota, con data e operatore.</span></span>
                            </label>
                            @error('assenso')<div class="form-error">{{ $message }}</div>@enderror
                        </x-card>
                    @endif

                    @if (now()->year < $annoAvvio)
                        <x-avviso tipo="info" titolo="Contabilità nel gestionale dal {{ $annoAvvio }}.">
                            Una quota incassata prima del 1° gennaio {{ $annoAvvio }} si registra con la ricevuta ma senza movimento in Prima Nota:
                            l'incasso è nel vecchio gestionale.
                        </x-avviso>
                    @endif

                    <x-card titolo="Pagamento">
                        <div class="form-grid form-grid-3">
                            <x-campo name="importo" label="Importo €" required :value="Importo::it($importo)" inputmode="decimal" autocomplete="off"
                                     :aiuto="'Quota «' . strtolower(Rinnovo::CATEGORIE[$categoria]) . '» ' . $anno . ' decisa dal CD. Si cambia solo con una nota.'" />
                            <x-campo name="data_pagamento" label="Versata il" tipo="date" required :value="today()" />
                            <div @class(['form-field', 'has-error' => $errors->has('metodo')])>
                                <label class="form-label" for="campo-metodo">Modalità<span class="obbligatorio" aria-hidden="true">*</span></label>
                                <select id="campo-metodo" name="metodo" class="form-select" x-model="metodo" required aria-describedby="campo-metodo-aiuto">
                                    @foreach (Rinnovo::METODI as $v => $l)
                                        <option value="{{ $v }}" @selected($metodo === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                                <div class="form-help" id="campo-metodo-aiuto">Contanti → Cassa; bonifico, carta o POS → Banca.</div>
                                @error('metodo')<div class="form-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-grid form-grid-3 form-riga">
                            <x-campo name="versato_da_nome" label="Versata da" :required="$giovane" maxlength="150"
                                     :value="$giovane ? $socio->tutore_nome : null"
                                     :aiuto="$giovane ? 'Socio giovane: il genitore che paga.' : 'Se non paga il socio. Compare sulla ricevuta.'" />
                            <x-campo name="versato_da_cf" label="Codice fiscale di chi versa" :required="$giovane" codice maxlength="16"
                                     :value="$giovane ? $socio->tutore_codice_fiscale : null" aiuto="Compare sulla ricevuta." />
                            <x-campo name="note" label="Note" maxlength="1000" aiuto="Obbligatorie se l'importo è diverso dalla quota." />
                        </div>
                        @if ($giovane)
                            @if ($rasdOggi)
                                <div x-show="metodo === 'contante' || metodo === 'altro'" x-cloak>
                                    <x-avviso tipo="warn" titolo="Non detraibile per il genitore.">
                                        La detrazione per lo sport dei ragazzi chiede un pagamento tracciabile (bonifico, carta o POS).
                                    </x-avviso>
                                </div>
                            @else
                                <x-avviso tipo="info" titolo="Detrazione per lo sport dei ragazzi: non disponibile.">
                                    L'ente non è iscritto al RASD: le ricevute non riportano la detrazione del 19%. Dal passaggio ad A.S.D. con l'iscrizione la
                                    ricevuta di un socio tra 5 e 18 anni la riporta da sola, se il pagamento è tracciabile.
                                </x-avviso>
                            @endif
                        @endif
                    </x-card>

                    <x-card titolo="Ricevuta">
                        <div class="form-grid">
                            <div class="rq-prossimo">
                                <span class="form-label">Numero</span>
                                <strong>{{ $prossimo[1] }}/{{ $prossimo[0] }}</strong>
                                <span class="form-help">Il prossimo dell'anno del pagamento, assegnato al salvataggio. Non si riusa, nemmeno se la ricevuta si annulla.</span>
                            </div>
                            <label class="spunta">
                                <input type="checkbox" name="invia" value="1" @checked(old('invia', (bool) $destinatario) ) @disabled(! $destinatario)>
                                <span><strong>Invia la ricevuta per email</strong>
                                    <span class="scelta-sub">
                                        @if ($destinatario)
                                            A {{ $destinatario['email'] }}{{ $destinatario['destinatario'] === 'tutore' ? ' (genitore)' : '' }}.
                                        @else
                                            Nessuna email{{ $socio->isMinorenne() ? ' del genitore' : '' }}: la scarichi e la consegni.
                                        @endif
                                    </span></span>
                            </label>
                        </div>
                    </x-card>

                    <div class="form-actions">
                        <a href="{{ $indietro }}" class="btn btn-ghost">Annulla</a>
                        <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Registra la quota</button>
                    </div>
                </form>
            @endif
        </div>

        <aside class="pn-colonna">
            @if ($socio && ! $giaVersata)
                <x-card titolo="Cosa succede" class="pn-aiuto">
                    <p><strong>Rinnovo {{ $anno }}</strong> di {{ $socio->cognome }} {{ $socio->nome }}: quota valida fino al 31/12/{{ $anno }}, il socio torna in regola (anche per il voto).</p>
                    <p><strong>Prima Nota</strong>: entrata in Cassa o in Banca secondo la modalità, categoria «Quote associative», collegata alla ricevuta.</p>
                    <p><strong>Ricevuta</strong> in PDF, nella scheda del socio; email {{ $socio->isMinorenne() ? 'al genitore' : 'al socio' }} se scelto.</p>
                    <p>Un errore si corregge annullando la ricevuta (con motivazione) e registrando di nuovo: il numero annullato resta nel registro.</p>
                </x-card>
            @endif
            <x-card :titolo="'Quote ' . $anno . ' (dal CD)'" class="pn-aiuto">
                <div class="rq-quote">
                    <x-kpi label="Ordinario" :valore="Importo::it($quote['ordinario'], true)" sub="anche i fondatori" />
                    <x-kpi label="Giovane" :valore="Importo::it($quote['giovane'], true)" sub="minorenni al 1° gennaio" />
                </div>
                <p>Chi compie 18 anni durante l'anno paga da giovane fino al 31 dicembre (statuto art. 5.3).</p>
            </x-card>
        </aside>
    </div>
@endsection
