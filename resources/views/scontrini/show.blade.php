{{--
    Scheda dello scontrino (tavola SN-6). A sinistra foto e dati; a destra
    la quadratura, le righe con la categoria per riga e il riepilogo «un
    movimento per categoria» con «Registra la divisione».

    Presidente e Tesoriere ($puo) correggono e dividono; gli altri ruoli
    (chi l'ha caricato, il Revisore) vedono tutto in sola lettura.
    Il piano della divisione mostrato qui segue la stessa regola del server
    (Scontrini::piano): righe raggruppate per categoria nell'ordine in cui
    compaiono, movimento della stessa categoria riusato, poi i liberi.
--}}
@extends('layouts.app')

@section('title', 'Scontrino ' . $s->codice())

@use('App\Support\Importo')
@use('App\Models\PrimaNotaCategoria')

@section('content')
    @php
        $erroriDivisione = $errors->divisione;
        $righeIniziali = $erroriDivisione->any() && is_array(old('righe'))
            ? collect(old('righe'))->map(fn ($r) => ['descrizione' => (string) ($r['descrizione'] ?? ''), 'importo' => (string) ($r['importo'] ?? ''), 'categoria_id' => (string) ($r['categoria_id'] ?? '')])->values()
            : $s->righe->map(function ($r) use ($s) {
                $cat = $r->categoria_id ?? $s->movimenti->firstWhere('id', $r->prima_nota_id)?->categoria_id;
                $sistema = $s->movimenti->firstWhere('id', $r->prima_nota_id)?->categoria?->codice === PrimaNotaCategoria::DA_CLASSIFICARE;

                return ['descrizione' => $r->descrizione, 'importo' => Importo::it($r->importo), 'categoria_id' => $sistema && ! $r->categoria_id ? '' : (string) $cat];
            })->values();
        $nomiCategorie = $categorie->pluck('nome', 'id')->union($s->movimenti->pluck('categoria.nome', 'categoria_id'));  // union, non merge: le chiavi sono id
        $righeLette = count($s->righe_lette ?? []);
        $prima = $s->file->first();
    @endphp

    <x-page-header titolo="Scontrino" :evidenza="'n. ' . $s->numero"
                   :sottotitolo="collect([$s->fornitoreNome(), $s->data_scontrino->format('d/m/Y'), strtolower($s->contoLabel()), 'caricato dal telefono' . ($s->registratoDa ? ' da ' . $s->registratoDa->name : '') . ' il ' . $s->created_at->format('d/m/Y') . ' alle ' . $s->created_at->format('H:i')])->filter()->implode(' · ')"
                   :indietro="route(auth()->user()->puoModificare('prima_nota') || ! auth()->user()->puoModificare('scontrini') ? 'scontrini.index' : 'scontrini.miei', ['anno' => $s->anno])" indietro-label="Scontrini">
        <div class="badge-riga">
            <x-badge :variante="$s->statoVariante()">{{ $s->statoTesto() }}</x-badge>
            <span class="sn-codice">{{ $s->codice() }}</span>
            @if ($chiuso)<x-badge variante="neutral">Anno {{ $s->anno }} chiuso</x-badge>@endif
        </div>
        @if ($puo)
            <x-slot:azioni>
                <button type="button" class="btn btn-danger-outline" x-data x-on:click="$dispatch('apri-modale', 'elimina-scontrino')"><x-icona nome="cestino" width="14" height="14" /> Elimina lo scontrino</button>
            </x-slot:azioni>
        @endif
    </x-page-header>

    @unless ($puo)
        <x-avviso tipo="info" titolo="Sola lettura.">Verificano, dividono e correggono gli scontrini il Presidente e il Tesoriere.</x-avviso>
    @endunless

    @foreach (['dati', 'foto', 'elimina'] as $bag)
        @if ($errors->$bag->any())
            <x-avviso tipo="ko" titolo="Operazione non eseguita.">{{ $errors->$bag->first() }}</x-avviso>
        @endif
    @endforeach

    @if ($nota)
        <x-avviso tipo="info" titolo="Scontrino nella {{ $nota->isRinuncia() ? 'rinuncia' : 'nota di rimborso' }} {{ $nota->numero }}.">
            Pagato da {{ $s->anticipante?->cognome }} {{ $s->anticipante?->nome }}: totale, data, conto e divisione si cambiano, e lo scontrino si elimina, solo dopo aver annullato la
            <a href="{{ route('prima-nota.anticipi.show', $nota) }}">{{ $nota->isRinuncia() ? 'rinuncia' : 'nota' }}</a>.
        </x-avviso>
    @elseif ($s->isAnticipo())
        <x-avviso tipo="warn" titolo="Pagato di tasca propria da {{ $s->anticipante?->cognome }} {{ $s->anticipante?->nome }}.">
            Spesa da rimborsare: fuori dai saldi finché non si paga la nota. Dopo la divisione si mette in una nota da <a href="{{ route('prima-nota.anticipi.index') }}">Anticipi e note di rimborso</a>.
        </x-avviso>
    @endif

    <div class="sn-scheda">
        <div class="sn-scheda-sinistra">
            <x-card class="sn-foto-card">
                @if ($s->anteprima_path)
                    <button type="button" class="sn-foto-scheda" x-data x-on:click="$dispatch('apri-modale', 'foto-scontrino')" aria-label="Ingrandisci la foto">
                        <img src="{{ route('scontrini.anteprima', $s) }}" alt="Foto dello scontrino {{ $s->codice() }}">
                    </button>
                @else
                    <a href="{{ $prima ? route('scontrini.foto.show', [$s, $prima]) : '#' }}" class="sn-foto-scheda sn-foto-pdf" target="_blank" rel="noopener">
                        <x-icona nome="documento" width="34" height="34" /> {{ $prima?->nome ?? 'Nessun file' }}
                    </a>
                @endif
                <ul class="sn-file-lista">
                    @foreach ($s->file as $f)
                        <li>
                            {{ $f->nome }} ·
                            <a href="{{ route('scontrini.foto.show', [$s, $f]) }}" target="_blank" rel="noopener">Apri</a> ·
                            <a href="{{ route('scontrini.foto.show', [$s, $f, 'scarica' => 1]) }}">Scarica</a>
                        </li>
                    @endforeach
                </ul>
                @if ($puo)
                    <form method="POST" action="{{ route('scontrini.foto.store', $s) }}" enctype="multipart/form-data" class="sn-aggiungi-foto" x-data="{ nome: '' }">
                        @csrf
                        <label class="btn btn-secondary btn-sm">
                            <input type="file" name="foto" accept="image/jpeg,image/png,application/pdf,.pdf" class="sr-only" x-on:change="nome = $event.target.files[0]?.name || ''">
                            Aggiungi una foto
                        </label>
                        <span class="td-sub" x-text="nome"></span>
                        @if ($chiuso)
                            <input type="text" name="motivazione" class="form-input" minlength="15" maxlength="1000" placeholder="Motivazione (anno chiuso)" x-show="nome" x-cloak>
                        @endif
                        <button type="submit" class="btn btn-primary btn-sm" x-show="nome" x-cloak>Carica</button>
                    </form>
                @endif
            </x-card>

            <x-card titolo="Dati">
                @if ($puo)
                    <form method="POST" action="{{ route('scontrini.update', $s) }}" class="sn-dati">
                        @csrf
                        @method('PUT')
                        @php $erroriDati = $errors->dati; @endphp
                        <div class="form-grid form-grid-2">
                            <div @class(['form-field', 'has-error' => $erroriDati->has('importo')])>
                                <label class="form-label" for="dati-importo">Totale €</label>
                                <input id="dati-importo" name="importo" class="form-input" inputmode="decimal" autocomplete="off" required
                                       value="{{ $erroriDati->any() ? old('importo') : Importo::it($s->importo_totale) }}">
                            </div>
                            <div @class(['form-field', 'has-error' => $erroriDati->has('data')])>
                                <label class="form-label" for="dati-data">Data</label>
                                <input id="dati-data" name="data" type="date" class="form-input" required
                                       min="{{ $s->anno }}-01-01" max="{{ min($s->anno . '-12-31', today()->toDateString()) }}"
                                       value="{{ $erroriDati->any() ? old('data') : $s->data_scontrino->toDateString() }}">
                            </div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="dati-fornitore">Fornitore</label>
                            <select id="dati-fornitore" name="soggetto_id" class="form-select" required>
                                @foreach ($fornitori as $f)
                                    <option value="{{ $f->id }}" @selected((int) ($erroriDati->any() ? old('soggetto_id') : $s->soggetto_id) === $f->id)>{{ $f->nome }}{{ $f->partita_iva ? ' · P.IVA ' . $f->partita_iva : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="dati-conto">Conto</label>
                            <select id="dati-conto" name="conto" class="form-select">
                                @foreach (\App\Models\PrimaNota::CONTI as $v => $l)
                                    <option value="{{ $v }}" @selected(($erroriDati->any() ? old('conto') : $s->conto) === $v)>{{ $l }}</option>
                                @endforeach
                                @if ($s->anticipante_socio_id)
                                    {{-- «Pagato da me» (v0.43.0): spesa anticipata dal socio, da rimborsare --}}
                                    <option value="anticipo" @selected(($erroriDati->any() ? old('conto') : $s->conto) === 'anticipo')>Pagato da {{ $s->anticipante?->cognome }} {{ $s->anticipante?->nome }} (da rimborsare)</option>
                                @endif
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="dati-nota">Nota di chi l'ha caricato</label>
                            <textarea id="dati-nota" class="form-textarea" rows="2" readonly>{{ $s->nota }}</textarea>
                        </div>
                        @if ($chiuso)
                            <div class="form-field">
                                <label class="form-label" for="dati-motivazione">Motivazione<span class="obbligatorio" aria-hidden="true">*</span></label>
                                <textarea id="dati-motivazione" name="motivazione" class="form-textarea" rows="2" minlength="15" maxlength="1000">{{ $erroriDati->any() ? old('motivazione') : '' }}</textarea>
                                <div class="form-help">L'anno è chiuso: almeno 15 caratteri, restano nell'Audit Log.</div>
                            </div>
                        @endif
                        <button type="submit" class="btn btn-secondary sn-pulsante">Salva i dati</button>
                        <p class="form-help">Data, conto e fornitore passano ai movimenti. Il totale passa al movimento se è uno solo; con più movimenti va rifatta la divisione.</p>
                    </form>
                @else
                    <dl class="dati-griglia">
                        <x-dato label="Totale" :valore="Importo::it($s->importo_totale, true)" />
                        <x-dato label="Data" :valore="$s->data_scontrino->format('d/m/Y')" />
                        <x-dato label="Fornitore" :valore="$s->fornitoreNome()" />
                        <x-dato label="Conto" :valore="$s->contoLabel()" />
                        <x-dato label="Nota" :valore="$s->nota" class="col-span-2" />
                    </dl>
                @endif
            </x-card>

            @if ($puo && $s->ocr_testo_grezzo)
                <details class="card sn-testo-letto">
                    <summary>Testo letto dalla foto</summary>
                    <pre>{{ $s->ocr_testo_grezzo }}</pre>
                </details>
            @endif
        </div>

        <div class="sn-scheda-destra"
             x-data="{
                righe: @js($righeIniziali).map(r => ({ ...r, sel: false })),
                totale: {{ (int) round((float) $s->importo_totale * 100) }},
                movimenti: @js($s->movimenti->map(fn ($m) => ['id' => $m->id, 'categoria_id' => $m->categoria_id, 'nome' => $m->categoria?->nome, 'importo' => Importo::it($m->importo)])->values()),
                nomi: @js($nomiCategorie),
                split: @js($splitAbilitato),
                lette: @js(collect($s->righe_lette ?? [])->map(fn ($r) => ['descrizione' => mb_substr((string) $r['descrizione'], 0, 200), 'importo' => Importo::it($r['importo'])])->values()),
                tutte: '',
                centesimi(t) {
                    let s = String(t ?? '').replace(/[\s€ ]/g, '').replace('−', '-');
                    const neg = s.startsWith('-'); s = s.replace(/^-/, '');
                    if (s.includes(',') && s.includes('.')) s = s.replace(/\./g, '').replace(',', '.');
                    else if (s.includes(',')) s = s.replace(',', '.');
                    else if (/^\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
                    if (! /^\d+(\.\d{1,2})?$/.test(s)) return null;
                    const c = Math.round(parseFloat(s) * 100); return neg ? -c : c;
                },
                euro(c) { return (c < 0 ? '−' : '') + (Math.abs(c) / 100).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
                get somma() { return this.righe.reduce((t, r) => t + (this.centesimi(r.importo) ?? 0), 0) },
                get validi() { return this.righe.every(r => this.centesimi(r.importo) !== null && r.descrizione.trim() !== '') },
                get torna() { return this.validi && this.somma === this.totale },
                get categorieOk() { return this.righe.length > 0 && this.righe.every(r => r.categoria_id !== '') },
                get percentuale() { return this.totale > 0 ? Math.max(0, Math.min(100, Math.round(this.somma / this.totale * 100))) : 0 },
                get piano() {
                    const gruppi = [];
                    for (const r of this.righe) {
                        if (r.categoria_id === '') continue;
                        let g = gruppi.find(x => x.categoria_id === String(r.categoria_id));
                        if (! g) { g = { categoria_id: String(r.categoria_id), importo: 0, descrizioni: [] }; gruppi.push(g); }
                        g.importo += this.centesimi(r.importo) ?? 0;
                        if (r.descrizione.trim() && ! g.descrizioni.includes(r.descrizione.trim())) g.descrizioni.push(r.descrizione.trim());
                    }
                    let liberi = [...this.movimenti];
                    for (const g of gruppi) { const m = liberi.find(x => String(x.categoria_id) === g.categoria_id); if (m) { g.movimento = m.id; liberi = liberi.filter(x => x.id !== m.id); } }
                    for (const g of gruppi) { if (! g.movimento && liberi.length) { g.movimento = liberi[0].id; liberi = liberi.slice(1); } }
                    return { gruppi, tolti: liberi };
                },
                aggiungi() { this.righe.push({ descrizione: '', importo: '', categoria_id: this.split ? '' : (this.righe[0]?.categoria_id ?? ''), sel: false }); },
                togli(i) { this.righe.splice(i, 1); },
                get selezionate() { return this.righe.filter(r => r.sel).length },
                unisci() {
                    const sel = this.righe.filter(r => r.sel); if (sel.length < 2) return;
                    const unita = { descrizione: sel.map(r => r.descrizione.trim()).filter(Boolean).join(', ').slice(0, 200), importo: this.euro(sel.reduce((t, r) => t + (this.centesimi(r.importo) ?? 0), 0)).replace('−', '-'), categoria_id: sel[0].categoria_id, sel: false };
                    const primo = this.righe.indexOf(sel[0]);
                    this.righe = this.righe.filter(r => ! r.sel); this.righe.splice(primo, 0, unita);
                },
                riprendi() { const c = this.righe[0]?.categoria_id ?? ''; this.righe = this.lette.map(r => ({ ...r, categoria_id: c, sel: false })); },
                stessa() { if (this.tutte !== '') { this.righe.forEach(r => r.categoria_id = this.tutte); this.tutte = ''; } },
                quadra() { const diff = this.totale - this.somma; if (diff !== 0) this.righe.push({ descrizione: diff < 0 ? 'Sconto' : 'Differenza', importo: this.euro(diff).replace('−', '-'), categoria_id: this.split ? '' : (this.righe[0]?.categoria_id ?? ''), sel: false }); },
             }">

            <section class="card sn-quadratura" x-bind:class="torna ? 'sn-quadratura-ok' : 'sn-quadratura-ko'" aria-live="polite">
                <div class="sn-quadratura-testa">
                    <strong x-text="torna ? 'Torna: righe ' + euro(somma) + ' € su ' + euro(totale) + ' €' : (validi ? 'Non torna: righe ' + euro(somma) + ' € su ' + euro(totale) + ' € (mancano ' + euro(totale - somma) + ' €)' : 'C\'è una riga con l\'importo da sistemare')"></strong>
                    <span class="td-sub">{{ $righeLette ? $righeLette . ' ' . ($righeLette === 1 ? 'riga letta' : 'righe lette') . ' dalla foto' . ($s->righe->count() === 1 && $righeLette > 1 ? ': non tornavano, qui una riga col totale' : '') : 'Righe non lette dalla foto: una riga col totale, dividila se serve' }}</span>
                </div>
                <div class="sn-barra"><span x-bind:class="'sn-barra-' + Math.round(percentuale / 5) * 5"></span></div>
            </section>

            @if ($puo)
                <form method="POST" action="{{ route('scontrini.dividi', $s) }}" x-on:submit="if (! torna || ! categorieOk) { $event.preventDefault() }">
                    @csrf
            @endif

            <x-card titolo="Righe e categorie" senza-padding>
                <x-slot:azioni><x-badge variante="neutral"><span x-text="righe.length + (righe.length === 1 ? ' riga' : ' righe')">{{ count($righeIniziali) }} righe</span></x-badge></x-slot:azioni>
                @if ($erroriDivisione->any())
                    <div class="card-body"><x-avviso tipo="ko" titolo="Divisione non registrata.">{{ $erroriDivisione->first() }}</x-avviso></div>
                @endif
                <div class="table-wrap">
                    <table @class(['data-table', 'sn-righe', 'sn-righe-modifica' => $puo])>
                        <thead>
                            <tr>
                                @if ($puo)<th scope="col"><span class="sr-only">Seleziona</span></th>@endif
                                <th scope="col">Riga dello scontrino</th>
                                <th scope="col">Categoria</th>
                                <th scope="col" class="td-num">Importo €</th>
                                @if ($puo)<th scope="col"><span class="sr-only">Togli</span></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @if ($puo)
                                <template x-for="(r, i) in righe" :key="i">
                                    <tr>
                                        <td><input type="checkbox" x-model="r.sel" x-bind:aria-label="'Seleziona la riga ' + (i + 1)" class="sn-spunta"></td>
                                        <td><input type="text" class="form-input" maxlength="200" required x-model="r.descrizione" x-bind:name="'righe[' + i + '][descrizione]'" x-bind:aria-label="'Descrizione della riga ' + (i + 1)"></td>
                                        <td>
                                            <select class="form-select" required x-model="r.categoria_id" x-bind:name="'righe[' + i + '][categoria_id]'" x-bind:aria-label="'Categoria della riga ' + (i + 1)"
                                                    x-bind:disabled="! split && i > 0" x-effect="if (! split && i > 0) r.categoria_id = righe[0].categoria_id">
                                                <option value="">Scegli…</option>
                                                @foreach ($categorie as $c)<option value="{{ $c->id }}">{{ $c->nome }}</option>@endforeach
                                            </select>
                                            <template x-if="! split && i > 0"><input type="hidden" x-bind:name="'righe[' + i + '][categoria_id]'" x-bind:value="r.categoria_id"></template>
                                        </td>
                                        <td class="td-num"><input type="text" class="form-input sn-importo" inputmode="decimal" autocomplete="off" required x-model="r.importo" x-bind:name="'righe[' + i + '][importo]'"
                                                   x-bind:class="{ 'sn-importo-ko': centesimi(r.importo) === null }" x-bind:aria-label="'Importo della riga ' + (i + 1)"></td>
                                        <td><button type="button" class="action-btn" x-on:click="togli(i)" x-bind:disabled="righe.length === 1" x-bind:aria-label="'Togli la riga ' + (i + 1)"><x-icona nome="chiudi" /></button></td>
                                    </tr>
                                </template>
                            @else
                                @foreach ($s->righe as $r)
                                    <tr>
                                        <td>{{ $r->descrizione }}</td>
                                        <td>{{ $r->categoria?->nome ?? ($s->movimenti->firstWhere('id', $r->prima_nota_id)?->categoria?->nome ?? '—') }}</td>
                                        <td class="td-num">{{ Importo::it($r->importo) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
                @if ($puo)
                    <div class="sn-righe-azioni">
                        <button type="button" class="btn btn-secondary btn-sm" x-on:click="aggiungi()">+ Aggiungi una riga</button>
                        <button type="button" class="btn btn-secondary btn-sm" x-on:click="unisci()" x-bind:disabled="selezionate < 2">Unisci le righe selezionate</button>
                        <button type="button" class="btn btn-secondary btn-sm" x-on:click="quadra()" x-show="validi && ! torna" x-cloak>Aggiungi la differenza</button>
                        <button type="button" class="btn btn-secondary btn-sm" x-on:click="riprendi()" x-show="lette.length > 1 && righe.length === 1" x-cloak x-text="'Riprendi le ' + lette.length + ' righe lette'"></button>
                        <label class="sr-only" for="tutte-categoria">Tutte nella stessa categoria</label>
                        <select id="tutte-categoria" class="form-select sn-tutte" x-model="tutte" x-on:change="stessa()">
                            <option value="">Tutte nella stessa categoria…</option>
                            @foreach ($categorie as $c)<option value="{{ $c->id }}">{{ $c->nome }}</option>@endforeach
                        </select>
                    </div>
                    @unless ($splitAbilitato)
                        <p class="form-help sn-righe-nota">La divisione su più categorie è spenta nelle impostazioni: tutte le righe hanno la categoria della prima.</p>
                    @endunless
                @endif
            </x-card>

            @if ($puo)
                <x-card titolo="Divisione: un movimento per categoria" senza-padding>
                    <div class="table-wrap">
                        <table class="data-table sn-piano">
                            <thead>
                                <tr>
                                    <th scope="col">Movimento in Prima Nota</th>
                                    <th scope="col">Categoria</th>
                                    <th scope="col">Descrizione</th>
                                    <th scope="col" class="td-num">Importo €</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="g in piano.gruppi" :key="g.categoria_id">
                                    <tr>
                                        <td x-text="g.movimento ? 'n. ' + g.movimento : 'nuovo'"></td>
                                        <td x-text="nomi[g.categoria_id] ?? ''"></td>
                                        <td x-text="g.descrizioni.join(', ')"></td>
                                        <td class="td-num"><strong x-text="euro(g.importo)"></strong></td>
                                    </tr>
                                </template>
                                <tr x-show="piano.gruppi.length === 0"><td colspan="4" class="form-help">Scegli la categoria delle righe.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="sn-piano-piede">
                        <p class="form-help">
                            <template x-if="piano.tolti.length">
                                <span x-text="'Sostituisce ' + piano.tolti.map(m => 'il movimento n. ' + m.id + ' «' + (m.nome ?? '') + '» (' + m.importo + ')').join(', ') + '. '"></span>
                            </template>
                            Stessa foto, fornitore, data e conto.
                        </p>
                        @if ($chiuso)
                            <input type="text" name="motivazione" class="form-input" minlength="15" maxlength="1000" required placeholder="Motivazione (anno chiuso, almeno 15 caratteri)" value="{{ $erroriDivisione->any() ? old('motivazione') : '' }}">
                        @endif
                        <button type="submit" class="btn btn-primary" x-bind:disabled="! torna || ! categorieOk">Registra la divisione</button>
                    </div>
                </x-card>
                </form>
            @endif

            <x-card titolo="In Prima Nota ora" senza-padding>
                <div class="table-wrap">
                    <table class="data-table sn-piano">
                        <tbody>
                            @forelse ($s->movimenti as $m)
                                <tr>
                                    <td><a href="{{ route('prima-nota.show', $m) }}">n. {{ $m->id }}</a></td>
                                    <td>
                                        @if ($m->categoria?->codice === PrimaNotaCategoria::DA_CLASSIFICARE)
                                            <x-badge variante="warn">{{ $m->categoria->nome }}</x-badge>
                                        @else
                                            {{ $m->categoria?->nome }}
                                        @endif
                                    </td>
                                    <td>{{ $m->descrizione }}</td>
                                    <td class="td-num"><strong>{{ Importo::it($m->importo) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td class="form-help">Nessun movimento.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </div>

    @push('modals')
        @if ($s->anteprima_path)
            <x-modale nome="foto-scontrino" :titolo="'Foto dello scontrino ' . $s->codice()" larga>
                <img src="{{ route('scontrini.anteprima', $s) }}" alt="Foto dello scontrino {{ $s->codice() }}" class="sn-foto-grande">
            </x-modale>
        @endif
        @if ($puo)
            <x-modale nome="elimina-scontrino" titolo="Eliminare lo scontrino?" :aperta="$errors->elimina->any()">
                <form method="POST" action="{{ route('scontrini.destroy', $s) }}" id="form-elimina" class="form-modale">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="conferma" value="si">
                    <p>Si tolgono lo scontrino {{ $s->codice() }}, le sue foto e {{ $s->movimenti->count() === 1 ? 'il suo movimento' : 'i suoi ' . $s->movimenti->count() . ' movimenti' }} di Prima Nota ({{ Importo::it($s->importo_totale, true) }}). Resta la riga nell'Audit Log.</p>
                    @if ($chiuso)
                        <x-campo name="motivazione" label="Motivazione" tipo="textarea" required rows="2" aiuto="L'anno è chiuso: almeno 15 caratteri." />
                    @endif
                </form>
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                    <button type="submit" form="form-elimina" class="btn btn-danger">Elimina</button>
                </x-slot:piede>
            </x-modale>
        @endif
    @endpush
@endsection
