{{--
    Passo 2 dal telefono (tavole SN-2, SN-3): i dati letti dalla foto, tutti
    da controllare. È l'ultima occasione per chi carica: dopo «Registra» lo
    scontrino lo correggono solo Presidente e Tesoriere.

    Fornitore (analisi-scontrini.md §9): già in elenco (partita IVA o nome),
    «forse è uno di questi», nuovo con i dati letti da correggere, oppure
    scelto dalla lista (finestra con ricerca). Lo stato è in Alpine; al
    server arrivano «fornitore» (id o «nuovo») e, per un fornitore in elenco
    senza partita IVA, quella letta da aggiungere.
--}}
@extends('layouts.app')

@section('title', 'Controlla lo scontrino')

@use('App\Support\Importo')
@use('App\Scontrini\LettoreScontrino')

@section('content')
    @php
        $esito = $bozza['ocr']['esito'] ?? 'errore';
        $pivaLetta = $letto['partita_iva'] ?? null;
        $pivaValida = $pivaLetta && LettoreScontrino::partitaIvaValida($pivaLetta);
        $trovato = $riconosciuto['trovato'];
        $proposti = collect($trovato ? [$trovato] : $riconosciuto['simili']);
        $opzioni = $proposti->map(fn ($s) => ['id' => $s->id, 'nome' => $s->nome, 'dettaglio' => $s->dettaglio(), 'partita_iva' => $s->partita_iva])->values();
        $predefinito = old('fornitore', $trovato?->id ?? ($proposti->first()?->id ?? ($letto['fornitore'] ?? null ? 'nuovo' : '')));
        $righe = $letto['righe'] ?? [];
        $righeTornano = $righe && isset($letto['righe_somma'], $letto['totale']) && $letto['righe_somma'] === $letto['totale'];
        $totale = old('importo', isset($letto['totale']) ? Importo::it($letto['totale']) : '');
    @endphp

    <x-page-header titolo="Controlla lo" evidenza="scontrino"
                   sottotitolo="Passo 2 di 2 · ultima occasione per correggere: dopo «Registra» lo modifica solo il Tesoriere." />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Controlla i dati.">{{ $errors->first() }}</x-avviso>
    @endif

    <form method="POST" action="{{ route('scontrini.store', $token) }}" class="sn-passo"
          x-data="{
              scelta: @js((string) $predefinito),
              opzioni: @js($opzioni),
              elenco: @js($elenco),
              pivaLetta: @js($pivaValida ? $pivaLetta : null),
              cerca: '',
              invio: false,
              get sceltoDallaLista() { return this.scelta !== '' && this.scelta !== 'nuovo' && ! this.opzioni.some(o => String(o.id) === this.scelta) ? this.elenco.find(f => String(f.id) === this.scelta) : null },
              get pivaDaAggiungere() {
                  if (! this.pivaLetta || this.scelta === '' || this.scelta === 'nuovo') return '';
                  const f = this.elenco.find(x => String(x.id) === this.scelta) || this.opzioni.find(x => String(x.id) === this.scelta);
                  return f && ! f.partita_iva && ! this.elenco.some(x => x.partita_iva === this.pivaLetta) ? this.pivaLetta : '';
              },
              get trovati() { const q = this.cerca.trim().toLowerCase(); return q === '' ? this.elenco : this.elenco.filter(f => f.nome.toLowerCase().includes(q) || (f.partita_iva || '').includes(q)); },
              scegli(id) { this.scelta = String(id); this.cerca = '' },
          }"
          x-on:submit="invio = true">
        @csrf

        <div class="sn-foto-riga">
            @if ($bozza['ext'] === 'pdf' && empty($bozza['ridotta']))
                <span class="sn-miniatura sn-miniatura-pdf"><x-icona nome="documento" width="22" height="22" /> PDF</span>
            @else
                <button type="button" class="sn-miniatura" x-on:click="$dispatch('apri-modale', 'foto-bozza')" aria-label="Ingrandisci la foto">
                    <img src="{{ route('scontrini.conferma.foto', $token) }}" alt="">
                </button>
            @endif
            <div>
                <strong>Foto dello scontrino</strong>
                <span class="td-sub">{{ number_format($bozza['peso'] / 1048576, 1, ',', '.') }} MB
                    @unless ($bozza['ext'] === 'pdf' && empty($bozza['ridotta']))
                        · <button type="button" class="sn-link" x-on:click="$dispatch('apri-modale', 'foto-bozza')">Ingrandisci</button>
                    @endunless
                    · <button type="submit" form="rifai" class="sn-link">Rifai la foto</button>
                </span>
            </div>
        </div>

        @if ($esito === 'letto')
            <x-avviso tipo="warn" titolo="Letti dalla foto: controllali.">
                Correggi quello che non torna, poi registra.
                @foreach (array_filter($letto['avvisi'] ?? [], fn ($a) => ! str_starts_with($a, 'Le righe lette')) as $a)<br>{{ $a }}@endforeach
            </x-avviso>
        @else
            <x-avviso tipo="ko" titolo="Foto non letta.">
                {{ $bozza['ocr']['messaggio'] ?? 'Il servizio di lettura non ha risposto.' }} Scrivi tu totale, data e fornitore. La foto resta allegata.
            </x-avviso>
        @endif

        <div class="form-grid form-grid-2 sn-coppia">
            <x-campo name="importo" label="Totale €" required :value="$totale" inputmode="decimal" autocomplete="off"
                     :aiuto="! old('importo') && ($letto['totale_fonte'] ?? null) ? 'Dalla riga «' . mb_strtoupper($letto['totale_fonte']) . '».' : null" />
            <x-campo name="data" label="Data" tipo="date" required :value="$letto['data'] ?? null" :max="today()->toDateString()"
                     :aiuto="$letto['data_avviso'] ?? null" />
        </div>

        <fieldset @class(['card', 'sn-fornitore', 'has-error' => $errors->has('fornitore') || $errors->has('nuovo_nome') || $errors->has('nuovo_partita_iva')])>
            <legend class="card-title sn-legenda">Fornitore <span class="obbligatorio" aria-hidden="true">*</span></legend>
            <div class="card-body">
                @if ($esito === 'letto')
                    <p class="sn-letto">
                        Letto: «{{ $letto['fornitore'] ?? '—' }}»@if ($pivaLetta) · P.IVA {{ $pivaLetta }}@endif.
                        @if ($trovato && $riconosciuto['come'] === 'partita_iva')
                            È la partita IVA di un fornitore in elenco.
                        @elseif ($trovato)
                            C'è già un fornitore con questo nome.
                        @elseif ($proposti->isNotEmpty())
                            Non è in elenco con questi dati; forse è uno di questi:
                        @else
                            Nessun fornitore simile: si crea con i dati letti, dopo la tua correzione.
                        @endif
                    </p>
                @endif

                <div class="sn-opzioni" role="radiogroup" aria-label="Fornitore">
                    <template x-for="o in opzioni" :key="o.id">
                        <label class="scelta sn-scelta">
                            <input type="radio" name="fornitore" x-bind:value="String(o.id)" x-model="scelta">
                            <span class="scelta-testo">
                                <span x-text="o.nome"></span>
                                <span class="scelta-sub">
                                    <span x-text="o.dettaglio || 'nessun dato in elenco'"></span>
                                    @if ($trovato)<span class="sn-in-elenco"> · già in elenco</span>@endif
                                </span>
                                <span class="sn-aggiunta" x-show="scelta === String(o.id) && pivaDaAggiungere" x-cloak>Le aggiungo la partita IVA letta</span>
                            </span>
                        </label>
                    </template>

                    <template x-if="sceltoDallaLista">
                        <label class="scelta sn-scelta">
                            <input type="radio" name="fornitore" x-bind:value="scelta" checked>
                            <span class="scelta-testo">
                                <span x-text="sceltoDallaLista.nome"></span>
                                <span class="scelta-sub"><span x-text="sceltoDallaLista.dettaglio || 'nessun dato in elenco'"></span> · scelto dalla lista</span>
                                <span class="sn-aggiunta" x-show="pivaDaAggiungere" x-cloak>Le aggiungo la partita IVA letta</span>
                            </span>
                        </label>
                    </template>

                    <label class="scelta sn-scelta">
                        <input type="radio" name="fornitore" value="nuovo" x-model="scelta">
                        <span class="scelta-testo">Nuovo fornitore <span class="scelta-sub">{{ $esito === 'letto' ? 'Con i dati letti, da controllare' : 'Scrivi i dati dallo scontrino' }}</span></span>
                    </label>
                </div>

                <div class="sn-nuovo" x-show="scelta === 'nuovo'" @if ($predefinito !== 'nuovo') x-cloak @endif>
                    <x-campo name="nuovo_nome" label="Ragione sociale" obbligatorio-se="scelta === 'nuovo'" :value="$letto['fornitore'] ?? null" maxlength="200" />
                    <x-campo name="nuovo_partita_iva" label="Partita IVA" :value="$pivaLetta" inputmode="numeric" maxlength="11" codice
                             :aiuto="$pivaLetta && ! $pivaValida ? 'Letta male (la cifra di controllo non torna): correggila guardando lo scontrino.' : '11 cifre, controllate.'" />
                    <x-campo name="nuovo_indirizzo" label="Indirizzo" :value="$letto['indirizzo'] ?? null" maxlength="200" />
                    <x-campo name="nuovo_comune" label="Comune" :value="$letto['comune'] ?? null" maxlength="100" />
                </div>

                <input type="hidden" name="aggiungi_piva" x-bind:value="pivaDaAggiungere">
                @error('fornitore')<div class="form-error">{{ $message }}</div>@enderror
                <button type="button" class="btn btn-secondary sn-pulsante" x-on:click="$dispatch('apri-modale', 'lista-fornitori')">Scegli dalla lista dei fornitori</button>
            </div>
        </fieldset>

        <x-campo name="nota" label="Nota" tipo="textarea" required rows="2" maxlength="500" :value="$bozza['nota']" />

        <fieldset @class(['scelta-gruppo', 'has-error' => $errors->has('conto')])>
            <legend>Pagato con <span class="obbligatorio" aria-hidden="true">*</span></legend>
            @php $conto = old('conto', $letto['conto'] ?? 'cassa'); @endphp
            <div class="scelta-opzioni sn-due">
                <label class="scelta">
                    <input type="radio" name="conto" value="cassa" @checked($conto === 'cassa')>
                    <span class="scelta-testo">Cassa <span class="scelta-sub">Contanti</span></span>
                </label>
                <label class="scelta">
                    <input type="radio" name="conto" value="banca" @checked($conto === 'banca')>
                    <span class="scelta-testo">Banca <span class="scelta-sub">Carta o bancomat</span></span>
                </label>
            </div>
            @error('conto')<div class="form-error">{{ $message }}</div>@enderror
        </fieldset>

        @if ($righe)
            <div class="sn-righe-lette">
                <strong>Righe lette: {{ count($righe) }}</strong>
                <span>{{ collect($righe)->take(6)->map(fn ($r) => mb_convert_case(mb_strtolower($r['descrizione']), MB_CASE_TITLE) . ' ' . Importo::it($r['importo']))->implode(' · ') }}@if (count($righe) > 6) · …@endif.</span>
                <span>{{ $righeTornano ? 'Le divide per categoria il Tesoriere.' : 'Non tornano col totale: il Tesoriere le sistema dalla scheda.' }}</span>
            </div>
        @endif

        <div class="sn-azioni">
            <button type="submit" form="rifai" class="btn btn-secondary">Annulla</button>
            <button type="submit" class="btn btn-primary" x-bind:disabled="invio || scelta === ''">Registra</button>
        </div>

        {{-- Dentro il modulo: la finestra usa lo stato del fornitore qui sopra --}}
        <x-modale nome="lista-fornitori" titolo="Lista dei fornitori">
            <div class="sn-lista">
                <label class="sr-only" for="cerca-fornitore">Cerca un fornitore</label>
                <input id="cerca-fornitore" type="search" class="form-input" placeholder="Nome o partita IVA" autocomplete="off" x-model="cerca">
                <ul class="sn-lista-voci">
                    <template x-for="f in trovati" :key="f.id">
                        <li>
                            <button type="button" class="sn-lista-voce" x-on:click="scegli(f.id); aperta = false">
                                <strong x-text="f.nome"></strong>
                                <span class="td-sub" x-text="[f.dettaglio, f.usi ? 'usato ' + f.usi + (f.usi === 1 ? ' volta' : ' volte') : ''].filter(Boolean).join(' · ') || 'nessun dato in elenco'"></span>
                            </button>
                        </li>
                    </template>
                    <li x-show="trovati.length === 0" class="form-help">Nessun fornitore trovato.</li>
                </ul>
                <button type="button" class="btn btn-secondary sn-pulsante" x-on:click="scegli('nuovo'); aperta = false">+ Nuovo fornitore</button>
            </div>
        </x-modale>
    </form>

    <form method="POST" action="{{ route('scontrini.annulla', $token) }}" id="rifai">
        @csrf
        @method('DELETE')
    </form>

    @push('modals')
        <x-modale nome="foto-bozza" titolo="Foto dello scontrino" larga>
            <img src="{{ route('scontrini.conferma.foto', $token) }}" alt="Foto dello scontrino" class="sn-foto-grande">
        </x-modale>

    @endpush
@endsection
