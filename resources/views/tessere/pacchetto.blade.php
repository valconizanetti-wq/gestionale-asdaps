@extends('layouts.app')

@section('title', $p ? 'Correggi pacchetto' : 'Nuovo pacchetto')

@use('App\Support\Importo')

@section('content')
    @php
        $nuovo = ! $p;
        $tipoScelto = old('tipo_id', $p?->tipo_id ?? $tipi->first()?->id);
        $prezzo = old('prezzo_unitario', $p ? Importo::it($p->prezzo_unitario) : ($prezzi[$tipoScelto] ?? null));
        $iniziale = [
            'tipo' => (string) $tipoScelto,
            'prezzo' => $prezzo,
            'dal' => old('dal', $p ? $p->numero($p->dal) : ''),
            'al' => old('al', $p ? $p->numero($p->al) : ''),
            'movimento' => $nuovo ? (bool) old('movimento', ! old('_token')) : false,
        ];
        $conTessere = $p && $p->usate > 0;
    @endphp

    <x-page-header :titolo="$nuovo ? 'Nuovo' : 'Correggi'" evidenza="pacchetto"
                   :sottotitolo="$nuovo ? 'Tessere comprate dal CSEN: numeri, tipo e costo, con l\'uscita in Prima Nota' : $p->tipo->nome . ' ' . $p->intervallo() . ' · ' . $p->usate . ($p->usate === 1 ? ' tessera emessa' : ' tessere emesse')"
                   :indietro="route('tesseramento.index')" indietro-label="Tessere CSEN" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Controlla i dati.">Alcuni campi non sono validi: li trovi evidenziati qui sotto.</x-avviso>
    @endif

    @if ($nuovo && $tipi->isEmpty())
        <x-avviso tipo="warn" titolo="Nessun tipo di tessera in uso.">Aggiungi i tipi del listino CSEN dalla pagina Tessere (riquadro «Disponibili», «Nuovo tipo»).</x-avviso>
    @endif

    <div class="pn-colonne">
        <form method="POST" action="{{ $nuovo ? route('tesseramento.pacchetti.store') : route('tesseramento.pacchetti.update', $p) }}"
              enctype="multipart/form-data" class="pn-colonna" x-data="pacchettoTessere(@js($prezzi), @js($iniziale))">
            @csrf
            @unless ($nuovo) @method('PUT') @endunless

            <x-card titolo="Pacchetto">
                <div class="form-grid form-grid-3">
                    <x-campo name="acquistato_il" label="Acquistato il" tipo="date" required :value="$p?->acquistato_il ?? today()" />
                    @if ($nuovo)
                        <div @class(['form-field', 'has-error' => $errors->has('tipo_id')])>
                            <label class="form-label" for="campo-tipo_id">Tipo di tessera<span class="obbligatorio" aria-hidden="true">*</span></label>
                            <select id="campo-tipo_id" name="tipo_id" class="form-select" required x-model="tipo" x-on:change="cambiaTipo()" aria-describedby="campo-tipo_id-aiuto">
                                @foreach ($tipi as $t)
                                    <option value="{{ $t->id }}">{{ $t->nome }}</option>
                                @endforeach
                            </select>
                            <div class="form-help" id="campo-tipo_id-aiuto">Dal listino CSEN: base, silver, gold… Si aggiungono dalla pagina Tessere.</div>
                            @error('tipo_id')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    @else
                        <div class="form-field">
                            <span class="form-label">Tipo di tessera</span>
                            <span class="pr-valore">{{ $p->tipo->nome }}</span>
                            <div class="form-help">Il tipo non cambia: per un tipo sbagliato, elimina il pacchetto e registralo di nuovo.</div>
                        </div>
                    @endif
                    <x-campo name="prezzo_unitario" label="Prezzo per tessera €" required inputmode="decimal" autocomplete="off" x-model="prezzo"
                             :aiuto="$nuovo ? 'Proposto dall\'ultimo pacchetto dello stesso tipo.' : null" />
                </div>
                <div class="form-grid form-grid-3 form-riga">
                    <x-campo name="dal" label="Dal numero" required codice x-model="dal" maxlength="20" autocomplete="off"
                             aiuto="Come stampato, es. 26B2749167 o 1201." />
                    <x-campo name="al" label="Al numero" required codice x-model="al" maxlength="20" autocomplete="off" />
                    <div class="form-field">
                        <span class="form-label">Quantità</span>
                        <span class="pr-valore" x-text="quantita ?? '—'"></span>
                        <div class="form-help">Calcolata dai numeri.</div>
                    </div>
                </div>
                <div class="ts-totale form-riga" x-show="totale" x-cloak>
                    <strong x-text="'Totale ' + totale"></strong>
                    <span>Il gestionale controlla che i numeri non si sovrappongano ai pacchetti già registrati.</span>
                </div>
                <div class="form-riga">
                    <x-campo name="note" label="Note" tipo="textarea" :value="$p?->note" rows="2" maxlength="1000" />
                </div>
            </x-card>

            @if ($nuovo)
                <x-card titolo="Prima Nota">
                    <label class="spunta">
                        <input type="checkbox" name="movimento" value="1" x-model="movimento">
                        <span><strong>Registra l'uscita in Prima Nota</strong>
                            <span class="scelta-sub">Togli la spunta solo se il pagamento è già registrato (es. nel vecchio gestionale, prima del {{ $annoAvvio }}): poi si collega a mano.</span></span>
                    </label>
                    <div class="form-grid form-grid-3 form-riga" x-show="movimento" x-cloak>
                        <x-campo name="pagato_il" label="Pagato il" tipo="date" obbligatorio-se="movimento" :value="today()" x-bind:disabled="! movimento" />
                        <x-campo name="conto" label="Conto" tipo="select" obbligatorio-se="movimento" :value="'banca'" :opzioni="\App\Models\PrimaNota::CONTI" x-bind:disabled="! movimento" />
                        <div class="form-field">
                            <span class="form-label">Categoria</span>
                            <span class="pr-valore">Tesseramento Soci EPS</span>
                        </div>
                    </div>
                    <div x-show="movimento" x-cloak>
                        <x-campo-giustificativi id="campo-documenti" class="form-riga" label="Giustificativo" :tenuti="$tenuti" :errori="$errors->get('documenti')"
                                                aiuto="Ricevuta CSEN, bonifico. JPG, PNG o PDF, fino a 8 MB ciascuno." />
                    </div>
                </x-card>
            @else
                @if ($p->movimento)
                    <x-avviso tipo="info" titolo="Uscita n. {{ $p->movimento->id }} in Prima Nota.">Se cambi prezzo o numeri, il totale del movimento si aggiorna (non su un anno chiuso).</x-avviso>
                @endif
                @if ($conTessere)
                    <x-card titolo="Motivo della correzione">
                        <x-campo name="motivo" label="Motivazione" tipo="textarea" required rows="3" minlength="15" maxlength="1000"
                                 aiuto="Il pacchetto ha tessere emesse: la motivazione resta nell'Audit Log. L'intervallo deve comprendere i numeri già usati." />
                    </x-card>
                @endif
            @endif

            <div class="form-actions">
                <a href="{{ route('tesseramento.index') }}" class="btn btn-ghost">Annulla</a>
                <button type="submit" class="btn btn-primary">{{ $nuovo ? 'Registra il pacchetto' : 'Salva la correzione' }}</button>
            </div>
        </form>

        <aside class="pn-colonna">
            <x-card titolo="Come funziona" class="pn-aiuto">
                <p>I numeri sono quelli stampati sulle tessere del pacchetto: all'emissione il gestionale propone il primo libero.</p>
                <p>I 4 € della tessera non si tolgono dalla quota: la quota entra intera, il costo delle tessere esce qui. Nei rinnovi compare la «quota netta».</p>
                <p>Un pacchetto con tessere già emesse non si elimina; si corregge con una motivazione.</p>
                <p>Le tessere già caricate dall'elenco del CSEN con un numero del pacchetto (stesso tipo) si collegano da sole.</p>
            </x-card>
            @if (! $nuovo && ! $conTessere)
                <x-card titolo="Eliminare il pacchetto?" class="pn-aiuto">
                    <p>Nessuna tessera emessa: il pacchetto si può eliminare{{ $p->movimento ? ', insieme alla sua uscita in Prima Nota e ai giustificativi' : '' }}.</p>
                    <form method="POST" action="{{ route('tesseramento.pacchetti.destroy', $p) }}" x-data x-on:submit="if (! confirm('Eliminare il pacchetto?')) $event.preventDefault()">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Elimina il pacchetto</button>
                    </form>
                </x-card>
            @endif
        </aside>
    </div>
@endsection
