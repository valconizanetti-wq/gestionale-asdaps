{{--
    Pagina di voto del consigliere (tavola "Voto 1"). Nessuna spiegazione
    della procedura: i consiglieri la conoscono (Paolo, 29/09/2026).
    La motivazione compare solo con "Contrario" ed è obbligatoria.
--}}
@extends('layouts.pubblico')
@section('title', 'Voto del Consiglio Direttivo')
@section('stretto', '1')
@section('content')
@php
    $motivoIniziale = old('motivo', '');
@endphp
<div class="pubblico-colonna voto-pagina">
    @include('voto-cd._intestazione', ['titolo' => 'Domanda di adesione'])

    <section class="voto-scheda" aria-labelledby="voto-richiedente">
        <span class="iscr-occhiello">Richiedente</span>
        <h2 id="voto-richiedente" class="voto-nome">{{ $domanda->nomeCognome() }}</h2>
        <dl class="voto-dati">
            <x-dato label="Categoria" :valore="($domanda->tipo_socio === 'minorenne' ? 'Socio giovane' : 'Socio ordinario') . ' · quota ' . number_format(\App\Services\Quota::importo($domanda->tipo_socio), 0, ',', '.') . ' €'" />
            <x-dato :label="$domanda->sesso === 'F' ? 'Nata' : 'Nato'" :valore="$domanda->data_nascita?->format('d/m/Y') . ' a ' . $domanda->luogo_nascita" />
            <x-dato label="Residente a" :valore="$domanda->citta . ' (' . $domanda->provincia . ')'" />
            <x-dato label="Domanda presentata il" :valore="$domanda->created_at->locale('it')->isoFormat('D MMMM YYYY, HH:mm')" />
        </dl>
    </section>

    <div class="voto-termine" role="note">
        <x-icona nome="orologio" width="18" height="18" />
        <div>
            <span class="voto-termine-etichetta">Vota entro</span>
            <span class="voto-termine-data">{{ $domanda->scadenza_cd->locale('it')->isoFormat('dddd D MMMM YYYY, [ore] HH:mm') }}</span>
        </div>
    </div>

    <form method="POST" action="{{ route('voto-cd.vota', $token) }}" class="voto-modulo"
          x-data="{ scelta: @js(old('voto')), testo: @js($motivoIniziale), inviato: false }"
          x-on:submit="inviato = true">
        @csrf
        <fieldset @class(['voto-scelte', 'has-error' => $errors->has('voto')])>
            <legend>Il tuo voto <span class="obbligatorio" aria-hidden="true">*</span></legend>
            <label class="voto-scelta favorevole">
                <input type="radio" name="voto" value="favorevole" x-model="scelta" required @checked(old('voto') === 'favorevole')>
                <span><strong>Favorevole</strong><span>La domanda va accolta</span></span>
            </label>
            <label class="voto-scelta contrario">
                <input type="radio" name="voto" value="contrario" x-model="scelta" @checked(old('voto') === 'contrario')>
                <span><strong>Contrario</strong><span>Serve la motivazione</span></span>
            </label>
            @error('voto')<div class="form-error">{{ $message }}</div>@enderror

            <div class="voto-motivo" x-show="scelta === 'contrario'" x-cloak>
                <x-campo name="motivo" label="Motivazione" tipo="textarea" rows="4" maxlength="1000"
                         obbligatorio-se="scelta === 'contrario'" x-model="testo" />
                <div class="voto-contatore" aria-live="polite"><span x-text="testo.trim().length"></span> / min. {{ $minimo }}</div>
            </div>
        </fieldset>
        <div class="voto-modulo-piede">
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Registra il voto</button>
        </div>
    </form>
</div>
@endsection
