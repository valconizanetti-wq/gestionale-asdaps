{{--
    Finestra «Rinnovata» / «Fatta» (tavola SC-1) per la scadenza $s.
    Pagamento: data, importo (proposto dall'ultimo), conto, categoria di
    Prima Nota, prossima scadenza (un anno dopo, modificabile), uscita in
    Prima Nota con il giustificativo. Adempimento: data, prossima scadenza,
    note. Dopo un errore si riapre con i dati scritti (bag «rinnovo»).
--}}
@use('App\Support\Importo')
@use('App\Services\Scadenzario')
@php
    $bag = $errors->getBag('rinnovo');
    $aperta = (int) session('rinnovo_aperto') === $s->id;
    $v = fn (string $k, $def) => $aperta ? old($k, $def) : $def;
    $pagamento = $s->conPagamento();
    $proposto = Scadenzario::importoProposto($s);
    $movimentoIniziale = $pagamento && $puoPrimaNota && (bool) $v('movimento', today()->year >= $annoAvvio);
    $titolo = $s->titolo . ($pagamento ? ' rinnovata' : ' fatta');
    $id = 'sc-' . $s->id;
@endphp
<x-modale :nome="'rinnova-' . $s->id" :titolo="$titolo" :aperta="$aperta">
    <form method="POST" action="{{ route('scadenzario.rinnova', $s) }}" enctype="multipart/form-data" class="form-modale"
          id="form-rinnova-{{ $s->id }}" x-data="{ movimento: @js($movimentoIniziale) }">
        @csrf
        <input type="hidden" name="per_scadenza" value="{{ $s->data_scadenza->toDateString() }}">

        <p class="form-help sc-modale-testa">
            {{ $pagamento ? 'Registra il pagamento' : 'Segna l\'adempimento come fatto' }}{{ $s->ricorrente() ? ' e sposta la scadenza al prossimo anno.' : ': la scadenza si chiude.' }}
            Scadenza di adesso: <strong>{{ $s->data_scadenza->format('d/m/Y') }}</strong>.
        </p>

        @if ($aperta && $bag->any())
            <x-avviso tipo="ko" titolo="Controlla i dati.">{{ collect($bag->all())->implode(' ') }}</x-avviso>
        @endif

        <div @class(['form-grid', 'form-grid-3' => $pagamento])>
            <div @class(['form-field', 'has-error' => $aperta && $bag->has('fatto_il')])>
                <label class="form-label" for="{{ $id }}-fatto">{{ $pagamento ? 'Pagata il' : 'Fatta il' }}<span class="obbligatorio" aria-hidden="true">*</span></label>
                <input id="{{ $id }}-fatto" name="fatto_il" type="date" class="form-input" required max="{{ today()->toDateString() }}"
                       value="{{ $v('fatto_il', today()->toDateString()) }}">
            </div>
            @if ($pagamento)
                <div @class(['form-field', 'has-error' => $aperta && $bag->has('importo')])>
                    <label class="form-label" for="{{ $id }}-importo">Importo €<span class="obbligatorio" aria-hidden="true" x-show="movimento" x-cloak>*</span></label>
                    <input id="{{ $id }}-importo" name="importo" type="text" inputmode="decimal" autocomplete="off" class="form-input"
                           x-bind:required="movimento" value="{{ $v('importo', $proposto !== null ? Importo::it($proposto) : '') }}" aria-describedby="{{ $id }}-importo-aiuto">
                    <div class="form-help" id="{{ $id }}-importo-aiuto">{{ $proposto !== null ? 'Proposto dall\'ultimo pagamento.' : 'Primo pagamento registrato qui.' }}</div>
                </div>
                <div @class(['form-field', 'has-error' => $aperta && $bag->has('conto')])>
                    <label class="form-label" for="{{ $id }}-conto">Conto<span class="obbligatorio" aria-hidden="true" x-show="movimento" x-cloak>*</span></label>
                    <select id="{{ $id }}-conto" name="conto" class="form-select" x-bind:disabled="! movimento" x-bind:required="movimento">
                        @foreach (\App\Models\PrimaNota::CONTI as $k => $l)
                            <option value="{{ $k }}" @selected($v('conto', 'banca') === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <div class="form-grid">
            @if ($pagamento)
                <div class="form-field">
                    <span class="form-label">Categoria</span>
                    <span class="pr-valore">{{ $s->categoriaPrimaNota?->nome }}</span>
                </div>
            @endif
            @if ($s->ricorrente())
                <div @class(['form-field', 'has-error' => $aperta && $bag->has('prossima_scadenza')])>
                    <label class="form-label" for="{{ $id }}-prossima">Prossima scadenza<span class="obbligatorio" aria-hidden="true">*</span></label>
                    <input id="{{ $id }}-prossima" name="prossima_scadenza" type="date" class="form-input" required
                           min="{{ $s->data_scadenza->copy()->addDay()->toDateString() }}"
                           value="{{ $v('prossima_scadenza', $s->prossimaData()->toDateString()) }}" aria-describedby="{{ $id }}-prossima-aiuto">
                    <div class="form-help" id="{{ $id }}-prossima-aiuto">Un anno dopo; si può cambiare.</div>
                </div>
            @endif
        </div>

        @if ($pagamento)
            <label class="spunta">
                <input type="hidden" name="movimento" value="0">
                <input type="checkbox" name="movimento" value="1" x-model="movimento" @disabled(! $puoPrimaNota)>
                <span><strong>Registra l'uscita in Prima Nota</strong>
                    <span class="scelta-sub">
                        @if ($puoPrimaNota)
                            Togli la spunta solo se il pagamento è già registrato (es. nel vecchio gestionale, prima del {{ $annoAvvio }}).
                        @else
                            Il tuo ruolo non registra uscite: segna il rinnovo, l'uscita la registra il Tesoriere in Prima Nota.
                        @endif
                    </span></span>
            </label>
            @if ($puoPrimaNota)
                <div x-show="movimento" x-cloak>
                    <x-campo-giustificativi :id="$id . '-documenti'" label="Giustificativo" :tenuti="$aperta ? $tenuti : []"
                                            :errori="$aperta ? $bag->get('documenti') : []"
                                            aiuto="Quietanza, ricevuta, bonifico. JPG, PNG o PDF, fino a 8 MB ciascuno." />
                </div>
            @endif
        @endif

        <div class="form-field">
            <label class="form-label" for="{{ $id }}-note">Note</label>
            <textarea id="{{ $id }}-note" name="note" class="form-textarea" rows="2" maxlength="1000">{{ $v('note', '') }}</textarea>
        </div>
    </form>
    <x-slot:piede>
        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
        <button type="submit" form="form-rinnova-{{ $s->id }}" class="btn btn-primary">Registra</button>
    </x-slot:piede>
</x-modale>
