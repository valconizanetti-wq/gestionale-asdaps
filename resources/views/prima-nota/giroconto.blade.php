@extends('layouts.app')

@section('title', $g ? "Giroconto n. {$g->id}" : 'Giroconto')

@section('content')
    @php
        $da = old('conto_da', $g?->conto ?? 'cassa');
        $a = old('conto_a', $g?->giroconto?->conto ?? 'banca');
    @endphp

    <x-page-header :titolo="$g ? 'Modifica' : 'Nuovo'" :evidenza="$g ? 'giroconto n. ' . $g->id : 'giroconto'"
                   sottotitolo="Soldi spostati tra cassa e banca: due righe collegate, né entrata né uscita dell'anno"
                   :indietro="$g ? route('prima-nota.show', $g) : route('prima-nota.index')" :indietro-label="$g ? 'Giroconto' : 'Prima Nota'" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Controlla i dati." data-errori-elenco>Alcuni campi non sono validi: li trovi evidenziati qui sotto.</x-avviso>
    @endif

    <form method="POST" action="{{ $g ? route('prima-nota.update', $g) : route('prima-nota.giroconto.store') }}" class="form-pagina" novalidate
          x-data="{ inviato: false }" x-on:submit="inviato = true">
        @csrf
        @if ($g) @method('PUT') @endif

        @if ($chiuso)
            <x-card titolo="Correzione su anno chiuso">
                <p class="form-intro">L'anno {{ $g->annoMovimento() }} è chiuso: la correzione resta nell'Audit Log e i saldi finali dell'anno si aggiornano.</p>
                <x-campo name="motivazione" label="Motivazione" tipo="textarea" required rows="3" aiuto="Almeno 15 caratteri." />
            </x-card>
        @endif

        <x-card titolo="Giroconto">
            <div class="pn-scelte">
                @foreach (['conto_da' => ['Da', $da], 'conto_a' => ['A', $a]] as $campo => [$etichetta, $scelto])
                    <fieldset @class(['scelta-gruppo', 'has-error' => $errors->has($campo)])>
                        <legend>{{ $etichetta }} <span class="obbligatorio" aria-hidden="true">*</span></legend>
                        <div class="scelta-opzioni">
                            @foreach (\App\Models\PrimaNota::CONTI as $valore => $nome)
                                <label class="scelta">
                                    <input type="radio" name="{{ $campo }}" value="{{ $valore }}" @checked($scelto === $valore)>
                                    <span>{{ $nome }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error($campo)<div class="form-error">{{ $message }}</div>@enderror
                    </fieldset>
                @endforeach
            </div>
            <div class="form-grid form-grid-3 form-riga">
                <x-campo name="data" label="Data" tipo="date" required :value="$g?->data ?? today()" />
                <x-campo name="importo" label="Importo €" required inputmode="decimal" autocomplete="off"
                         :value="$g ? \App\Support\Importo::it($g->importo) : ''" />
                <x-campo name="descrizione" label="Descrizione" :value="$g?->descrizione" maxlength="255"
                         aiuto="Se la lasci vuota: «Versamento contanti in banca» o «Prelievo dalla banca per la cassa»." />
            </div>
            <div class="form-riga">
                <x-campo name="note" label="Note" tipo="textarea" rows="2" :value="$g?->note" maxlength="2000" />
            </div>
        </x-card>

        <div class="form-actions">
            <a href="{{ $g ? route('prima-nota.show', $g) : route('prima-nota.index') }}" class="btn btn-ghost">Annulla</a>
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">{{ $chiuso ? 'Salva la correzione' : 'Salva' }}</button>
        </div>
    </form>
@endsection
