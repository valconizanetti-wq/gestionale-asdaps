@extends('layouts.app')

@section('title', 'Categorie e tag')

@use('App\Support\Importo')
@use('App\Http\Controllers\CategorieController')

@section('content')
    @php
        $puoModificare = auth()->user()->puoModificare('prima_nota');
        $tutte = $uscite->concat($entrate);

        // Dopo un errore la finestra si riapre con i dati scritti e il
        // messaggio (bag per finestra, CategorieController). Il campo _id
        // dice quale categoria o tag si stava modificando.
        $erroreNuova = $errors->nuovaCategoria->any();
        $erroreCategoria = $errors->categoria->any() && $tutte->contains('id', (int) old('_id'));
        $erroreTag = $errors->tag->any() && $tags->contains('id', (int) old('_id'));
        $statoIniziale = [
            'c' => $erroreCategoria
                ? ['nome' => old('nome'), 'natura' => old('natura', 'istituzionale'), 'azione' => route('prima-nota.categorie.update', (int) old('_id')), 'id' => (int) old('_id')]
                : ['nome' => '', 'natura' => 'istituzionale', 'azione' => '', 'id' => null],
            't' => $erroreTag
                ? ['nome' => old('nome'), 'azione' => route('prima-nota.tag.update', (int) old('_id')), 'id' => (int) old('_id')]
                : ['nome' => '', 'azione' => '', 'id' => null],
            'nuova' => $erroreNuova && old('tipo') === 'entrata' ? 'entrata' : 'uscita',
        ];
    @endphp

    <div x-data="@js($statoIniziale)" class="form-pagina">
        <x-page-header titolo="Categorie e" evidenza="tag"
                       :sottotitolo="$tutte->count() . ' categorie (' . $tutte->where('attiva', true)->count() . ' attive) e ' . $tags->count() . ' ' . ($tags->count() === 1 ? 'tag' : 'tag')"
                       :indietro="route('prima-nota.index')" indietro-label="Prima Nota">
            @if ($puoModificare)
                <x-slot:azioni>
                    <button type="button" class="btn btn-primary" @click="$dispatch('apri-modale', 'nuova-categoria')">
                        <x-icona nome="piu" width="14" height="14" /> Nuova categoria
                    </button>
                </x-slot:azioni>
            @endif
        </x-page-header>

        <x-sola-lettura sezione="prima_nota" />

        @if ($errors->any())
            <x-avviso tipo="ko" titolo="Operazione non eseguita.">{{ $errors->first() }}</x-avviso>
        @endif

        <x-avviso tipo="info" titolo="Regole.">
            Il tipo (entrata o uscita) non cambia dopo la creazione. Una categoria già usata non si elimina: si disattiva e resta sui movimenti registrati.
            «Solo bilancio» non compare nei movimenti; «Di sistema» la assegna il gestionale. La natura commerciale conta dal 2028 (regime 398).
        </x-avviso>

        @foreach (['Uscite' => $uscite, 'Entrate' => $entrate] as $titolo => $elenco)
            <x-card :titolo="$titolo . ' (' . $elenco->count() . ')'" senza-padding>
                <div class="table-wrap">
                    <table class="data-table responsive pn-categorie">
                        <thead>
                            <tr>
                                <th scope="col">Nome</th>
                                <th scope="col">Uso</th>
                                <th scope="col">Natura</th>
                                <th scope="col">Stato</th>
                                <th scope="col" class="td-num">Movimenti</th>
                                <th scope="col"><span class="sr-only">Azioni</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($elenco as $cat)
                                @php
                                    $natura = $cat->flag_non_istituzionale ? 'commerciale' : ($cat->natura_da_verificare ? 'da_verificare' : 'istituzionale');
                                @endphp
                                <tr id="categoria-{{ $cat->id }}" @class(['pn-categoria-spenta' => ! $cat->attiva])>
                                    <td class="td-primario">
                                        <span class="pn-desc">{{ $cat->nome }}</span>
                                        @if ($cat->codice)<span class="td-sub code">{{ $cat->codice }}</span>@endif
                                    </td>
                                    {{-- Telefono: uso e natura solo se diversi dal solito, per schede più corte --}}
                                    <td data-label="Uso" @class(['td-solo-desktop' => $cat->uso === 'movimenti'])>
                                        @if ($cat->uso === 'movimenti')
                                            Movimenti
                                        @else
                                            <x-badge variante="info">{{ \App\Models\PrimaNotaCategoria::USI[$cat->uso] ?? $cat->uso }}</x-badge>
                                        @endif
                                    </td>
                                    <td data-label="Natura" @class(['td-solo-desktop' => $natura === 'istituzionale'])>
                                        @if ($natura === 'istituzionale')
                                            Istituzionale
                                        @else
                                            <x-badge :variante="$natura === 'commerciale' ? 'ko' : 'warn'">{{ $cat->naturaLabel() }}</x-badge>
                                        @endif
                                    </td>
                                    <td data-label="Stato">
                                        @if ($cat->attiva)<x-badge variante="ok">Attiva</x-badge>@else<x-badge variante="neutral">Disattivata</x-badge>@endif
                                    </td>
                                    <td data-label="Movimenti" class="td-num">{{ $cat->movimenti_count }}</td>
                                    <td class="td-azioni">
                                        @if ($puoModificare)
                                            @foreach (['su' => 'Sposta su', 'giu' => 'Sposta giù'] as $verso => $label)
                                                <form method="POST" action="{{ route('prima-nota.categorie.sposta', $cat) }}">
                                                    @csrf
                                                    <input type="hidden" name="verso" value="{{ $verso }}">
                                                    <button type="submit" class="action-btn" aria-label="{{ $label }}: {{ $cat->nome }}">
                                                        <x-icona nome="giu" @class(['pn-su' => $verso === 'su']) />
                                                    </button>
                                                </form>
                                            @endforeach
                                            @unless ($cat->isSistema())
                                                <button type="button" class="action-btn" aria-label="Modifica {{ $cat->nome }}"
                                                        @click="c = { nome: @js($cat->nome), natura: @js($natura), azione: @js(route('prima-nota.categorie.update', $cat)), id: {{ $cat->id }} }; $dispatch('apri-modale', 'categoria')">
                                                    <x-icona nome="matita" />
                                                </button>
                                                <form method="POST" action="{{ route('prima-nota.categorie.attiva', $cat) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-ghost btn-sm">{{ $cat->attiva ? 'Disattiva' : 'Attiva' }}</button>
                                                </form>
                                                @if (! $cat->codice && $cat->movimenti_count === 0)
                                                    <form method="POST" action="{{ route('prima-nota.categorie.destroy', $cat) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="action-btn" aria-label="Elimina {{ $cat->nome }}"><x-icona nome="cestino" /></button>
                                                    </form>
                                                @endif
                                            @endunless
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach

        <x-card titolo="Tag" senza-padding>
            @if ($tags->isEmpty())
                <x-vuoto>Nessun tag ancora: si crea scrivendolo nel campo «Tag» di un movimento (es. «Tettoia»).</x-vuoto>
            @else
                <div class="table-wrap">
                    <table class="data-table responsive pn-categorie">
                        <thead>
                            <tr>
                                <th scope="col">Tag</th>
                                <th scope="col" class="td-num">Movimenti</th>
                                <th scope="col" class="td-num">Uscite €</th>
                                <th scope="col" class="td-num">Entrate €</th>
                                <th scope="col">Anni</th>
                                <th scope="col"><span class="sr-only">Azioni</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tags as $tag)
                                <tr>
                                    <td class="td-primario"><a href="{{ route('prima-nota.index', ['tag' => $tag->id, 'anno' => explode(',', $anniTag[$tag->id] ?? now()->year)[0]]) }}" class="badge-tag">{{ $tag->nome }}</a></td>
                                    <td data-label="Movimenti" class="td-num">{{ $tag->movimenti_count }}</td>
                                    <td data-label="Uscite €" class="td-num"><strong>{{ Importo::it($tag->uscite ?? 0) }}</strong></td>
                                    <td data-label="Entrate €" class="td-num">{{ Importo::it($tag->entrate ?? 0) }}</td>
                                    <td data-label="Anni">{{ str_replace(',', ', ', $anniTag[$tag->id] ?? '—') }}</td>
                                    <td class="td-azioni">
                                        @if ($puoModificare)
                                            <button type="button" class="action-btn" aria-label="Rinomina {{ $tag->nome }}"
                                                    @click="t = { nome: @js($tag->nome), azione: @js(route('prima-nota.tag.update', $tag)), id: {{ $tag->id }} }; $dispatch('apri-modale', 'tag')">
                                                <x-icona nome="matita" />
                                            </button>
                                            @if ($tag->movimenti_count === 0)
                                                <form method="POST" action="{{ route('prima-nota.tag.destroy', $tag) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="action-btn" aria-label="Elimina {{ $tag->nome }}"><x-icona nome="cestino" /></button>
                                                </form>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="table-footer pn-totali">Un tag per movimento, valido per tutti gli anni. Le colonne sommano tutti gli anni.</p>
            @endif
        </x-card>

        @if ($puoModificare)
            <x-modale nome="categoria" titolo="Modifica la categoria" :aperta="$erroreCategoria">
                <form method="POST" x-bind:action="c.azione" class="form-modale" id="form-categoria">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_id" x-bind:value="c.id">
                    <div @class(['form-field', 'has-error' => $erroreCategoria && $errors->categoria->has('nome')])>
                        <label class="form-label" for="categoria-nome">Nome<span class="obbligatorio" aria-hidden="true">*</span></label>
                        <input id="categoria-nome" name="nome" class="form-input" maxlength="150" required x-model="c.nome">
                        @if ($erroreCategoria)
                            @foreach ($errors->categoria->all() as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                        @endif
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="categoria-natura">Natura</label>
                        <select id="categoria-natura" name="natura" class="form-select" x-model="c.natura">
                            @foreach (CategorieController::NATURE as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                        </select>
                        <div class="form-help">Commerciale: provento soggetto al plafond del regime 398 (dal 2028). Nel dubbio, «da verificare».</div>
                    </div>
                </form>
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                    <button type="submit" form="form-categoria" class="btn btn-primary">Salva</button>
                </x-slot:piede>
            </x-modale>

            <x-modale nome="nuova-categoria" titolo="Nuova categoria" :aperta="$erroreNuova">
                <form method="POST" action="{{ route('prima-nota.categorie.store') }}" class="form-modale" id="form-nuova-categoria">
                    @csrf
                    <fieldset class="scelta-gruppo">
                        <legend>Tipo</legend>
                        <div class="scelta-opzioni">
                            <label class="scelta"><input type="radio" name="tipo" value="uscita" x-model="nuova"> <span>Uscita</span></label>
                            <label class="scelta"><input type="radio" name="tipo" value="entrata" x-model="nuova"> <span>Entrata</span></label>
                        </div>
                    </fieldset>
                    <div @class(['form-field', 'has-error' => $erroreNuova && $errors->nuovaCategoria->has('nome')])>
                        <label class="form-label" for="nuova-nome">Nome<span class="obbligatorio" aria-hidden="true">*</span></label>
                        <input id="nuova-nome" name="nome" class="form-input" maxlength="150" required value="{{ $erroreNuova ? old('nome') : '' }}">
                        <div class="form-help">Il tipo non si potrà più cambiare. La categoria va in fondo all'elenco: si sposta con le frecce.</div>
                        @if ($erroreNuova)
                            @foreach ($errors->nuovaCategoria->all() as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                        @endif
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="nuova-natura">Natura</label>
                        <select id="nuova-natura" name="natura" class="form-select">
                            @foreach (CategorieController::NATURE as $v => $l)<option value="{{ $v }}" @selected($erroreNuova && old('natura') === $v)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                </form>
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                    <button type="submit" form="form-nuova-categoria" class="btn btn-primary">Aggiungi</button>
                </x-slot:piede>
            </x-modale>

            <x-modale nome="tag" titolo="Rinomina il tag" :aperta="$erroreTag">
                <form method="POST" x-bind:action="t.azione" class="form-modale" id="form-tag">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_id" x-bind:value="t.id">
                    <div @class(['form-field', 'has-error' => $erroreTag])>
                        <label class="form-label" for="tag-nome">Nome<span class="obbligatorio" aria-hidden="true">*</span></label>
                        <input id="tag-nome" name="nome" class="form-input" maxlength="100" required x-model="t.nome">
                        <div class="form-help">Il nuovo nome vale per tutti i movimenti con questo tag.</div>
                        @if ($erroreTag)
                            @foreach ($errors->tag->all() as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                        @endif
                    </div>
                </form>
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                    <button type="submit" form="form-tag" class="btn btn-primary">Salva</button>
                </x-slot:piede>
            </x-modale>
        @endif
    </div>
@endsection
