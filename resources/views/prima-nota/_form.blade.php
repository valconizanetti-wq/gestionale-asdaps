{{--
    Modulo del movimento, condiviso tra nuovo e modifica (tavola PN-3).
    Variabili: $m, $categorieEntrata, $categorieUscita, $soci, $soggetti,
    $tags, $progetti; in modifica anche $chiuso.

    Le categorie cambiano con il tipo (Alpine): le due tendine hanno lo
    stesso nome e quella nascosta è disattivata, quindi non si invia.
    L'importo è un campo di testo con tastiera numerica (prima-nota.md §4).
--}}
@php
    $tipo = old('tipo', $m->tipo);
    $conto = old('conto', $m->conto);
    $chiuso = $chiuso ?? false;
    $categoriaScelta = (string) old('categoria_id', $m->categoria_id);
    $importo = old('importo', $m->importo !== null ? \App\Support\Importo::it($m->importo) : '');
    // Movimento di uno scontrino: tipo, conto, data e importo dalla scheda dello scontrino
    $bloccati = (bool) $m->scontrino_id;
@endphp

@if ($bloccati)
    <x-avviso tipo="info" titolo="Movimento dello scontrino {{ $m->scontrino?->codice() }}.">
        Importo, data e conto si cambiano dalla <a href="{{ route('scontrini.show', $m->scontrino_id) }}">scheda dello scontrino</a>; qui categoria, descrizione e il resto.
    </x-avviso>
@endif

@if ($errors->any())
    <x-avviso tipo="ko" titolo="Controlla i dati." data-errori-elenco>Alcuni campi non sono validi: li trovi evidenziati qui sotto.</x-avviso>
@endif

<div class="pn-colonne" x-data="{ tipo: @js($tipo) }">
    <div class="pn-colonna">
        @if ($chiuso)
            <x-card titolo="Correzione su anno chiuso">
                <p class="form-intro">L'anno {{ $m->annoMovimento() }} è chiuso: la correzione resta nell'Audit Log con i valori prima e dopo, e i saldi finali dell'anno si aggiornano.</p>
                <x-campo name="motivazione" label="Motivazione" tipo="textarea" required rows="3"
                         aiuto="Almeno 15 caratteri: perché si corregge e, se c'è, il verbale che lo decide." />
            </x-card>
        @endif

        <x-card titolo="Movimento">
            <div class="pn-scelte">
                <fieldset class="scelta-gruppo">
                    <legend>Tipo <span class="obbligatorio" aria-hidden="true">*</span></legend>
                    <div class="scelta-opzioni">
                        <label class="scelta">
                            <input type="radio" name="tipo" value="uscita" x-model="tipo" @checked($tipo === 'uscita') @disabled($bloccati)>
                            <span class="scelta-testo">Uscita <span class="scelta-sub">Spesa</span></span>
                        </label>
                        <label class="scelta">
                            <input type="radio" name="tipo" value="entrata" x-model="tipo" @checked($tipo === 'entrata') @disabled($bloccati)>
                            <span class="scelta-testo">Entrata <span class="scelta-sub">Incasso</span></span>
                        </label>
                    </div>
                </fieldset>
                <fieldset @class(['scelta-gruppo', 'has-error' => $errors->has('conto')])>
                    <legend>Conto <span class="obbligatorio" aria-hidden="true">*</span></legend>
                    <div class="scelta-opzioni">
                        <label class="scelta">
                            <input type="radio" name="conto" value="cassa" @checked($conto === 'cassa') @disabled($bloccati)>
                            <span class="scelta-testo">Cassa <span class="scelta-sub">Contanti</span></span>
                        </label>
                        <label class="scelta">
                            <input type="radio" name="conto" value="banca" @checked($conto === 'banca') @disabled($bloccati)>
                            <span class="scelta-testo">Banca <span class="scelta-sub">Bonifico, carta, POS</span></span>
                        </label>
                    </div>
                    @error('conto')<div class="form-error">{{ $message }}</div>@enderror
                </fieldset>
            </div>
        </x-card>

        <x-card titolo="Importo e categoria">
            <div class="form-grid form-grid-3">
                <x-campo name="data" label="Data" tipo="date" required :value="$m->data" :disabled="$bloccati" />
                <x-campo name="importo" label="Importo €" required :value="$importo" inputmode="decimal" autocomplete="off" :disabled="$bloccati"
                         class="pn-campo-importo" aiuto="Virgola per i centesimi: 1.450 vale millequattrocentocinquanta." />
                @foreach (['uscita' => $categorieUscita, 'entrata' => $categorieEntrata] as $t => $elenco)
                    <div @class(['form-field', 'has-error' => $errors->has('categoria_id')]) x-show="tipo === '{{ $t }}'" @if ($tipo !== $t) x-cloak @endif>
                        <label class="form-label" for="categoria-{{ $t }}">Categoria<span class="obbligatorio" aria-hidden="true">*</span></label>
                        <select id="categoria-{{ $t }}" name="categoria_id" class="form-select" required x-bind:disabled="tipo !== '{{ $t }}'" @disabled($tipo !== $t)
                                aria-describedby="categoria-{{ $t }}-aiuto">
                            <option value="">Scegli…</option>
                            @foreach ($elenco as $c)
                                <option value="{{ $c->id }}" @selected($categoriaScelta === (string) $c->id)>{{ $c->nome }}{{ $c->attiva ? '' : ' (disattivata)' }}</option>
                            @endforeach
                        </select>
                        <div class="form-help" id="categoria-{{ $t }}-aiuto">Solo le categorie attive di {{ $t }}. Le voci dell'anno precedente si usano solo nel bilancio.</div>
                        @error('categoria_id')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
            <div class="form-riga">
                <x-campo name="descrizione" label="Descrizione" required :value="$m->descrizione" maxlength="255" />
            </div>
        </x-card>

        <x-card titolo="Fornitore, socio, tag e progetto">
            <div class="form-grid form-grid-3">
                <x-campo name="soggetto" label="Fornitore o soggetto" :value="$m->soggetto" list="elenco-soggetti" maxlength="200" autocomplete="off"
                         aiuto="Scegli dall'elenco o scrivi un nome nuovo: si aggiunge al salvataggio." />
                <x-campo name="tag" label="Tag" :value="$m->tag?->nome" list="elenco-tag" maxlength="100" autocomplete="off"
                         aiuto="Raggruppa le spese di uno stesso lavoro, anche su categorie diverse. Facoltativo." />
                @if ($progetti->isNotEmpty())
                    <x-campo name="progetto_id" label="Progetto" tipo="select" vuota="Nessuno" :value="$m->progetto_id"
                             :opzioni="$progetti->pluck('nome', 'id')->all()" aiuto="Solo per bandi e contributi da rendicontare." />
                @endif
            </div>
            <div class="form-riga" x-show="tipo === 'entrata'" @if ($tipo !== 'entrata') x-cloak @endif>
                <x-campo name="socio_id" label="Socio" tipo="select" vuota="Nessuno" :value="$m->socio_id"
                         :opzioni="$soci->mapWithKeys(fn ($s) => [$s->id => $s->cognome . ' ' . $s->nome])->all()"
                         aiuto="Per un'entrata versata da un socio. Le quote associative si registrano con la domanda o con il rinnovo, che fanno anche la ricevuta." />
            </div>
            <datalist id="elenco-soggetti">@foreach ($soggetti as $s)<option value="{{ $s }}"></option>@endforeach</datalist>
            <datalist id="elenco-tag">@foreach ($tags as $t)<option value="{{ $t }}"></option>@endforeach</datalist>
        </x-card>

        <x-card titolo="Documento">
            <div class="form-grid form-grid-3">
                <x-campo name="tipo_documento" label="Tipo di documento" tipo="select" vuota="—" :value="$m->tipo_documento"
                         :opzioni="\App\Models\PrimaNota::TIPI_DOCUMENTO" />
                <x-campo name="numero_documento" label="Numero" :value="$m->numero_documento" maxlength="100" />
                <x-campo name="data_documento" label="Data del documento" tipo="date" :value="$m->data_documento" />
            </div>
            <x-campo-giustificativi id="campo-documenti" class="form-riga" :label="$m->exists ? 'Aggiungi giustificativi' : 'Giustificativi'"
                                    :tenuti="\App\Http\Controllers\PrimaNotaController::fileTenuti()" :errori="$errors->get('documenti')" />
            <div class="form-riga">
                <x-campo name="note" label="Note" tipo="textarea" :value="$m->note" rows="3" maxlength="2000" />
            </div>
        </x-card>
    </div>

    <aside class="pn-colonna">
        <x-card titolo="Come funziona" class="pn-aiuto">
            <p>Le <strong>quote associative</strong> entrano da sole quando si iscrive un socio dalla domanda online; i rinnovi avranno il loro modulo, con la ricevuta.</p>
            <p>Per spostare soldi tra <strong>cassa e banca</strong> usa «Giroconto»: non è né un'entrata né un'uscita.</p>
            <p>Gli <strong>scontrini</strong> si caricano dal telefono (pagina Scontrini); uno scontrino con voci di categorie diverse si divide dalla sua scheda, un movimento per categoria.</p>
        </x-card>
        <x-card titolo="Correzioni" class="pn-aiuto">
            <p>Finché l'anno è aperto il movimento si corregge liberamente; ogni modifica resta comunque nell'Audit Log.</p>
            <p>Dopo la chiusura dell'anno ogni correzione chiede una motivazione obbligatoria.</p>
        </x-card>
    </aside>
</div>
