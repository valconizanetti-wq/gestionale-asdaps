{{-- Dati fissi dell'ente: non cambiano con la forma giuridica (profilo-ente.md §2). --}}
@extends('layouts.app')

@section('title', "Dati fissi dell'ente")

@section('content')
    <x-page-header titolo="Dati fissi" evidenza="dell'ente" sottotitolo="Codice fiscale, recapiti e banca: valgono per tutti i periodi"
                   :indietro="route('impostazioni.index')" indietro-label="Profilo dell'ente" />

    <form method="POST" action="{{ route('impostazioni.ente.dati.update') }}" class="form-pagina" novalidate x-data="{ inviato: false }" x-on:submit="inviato = true">
        @csrf
        @method('PUT')
        <x-card titolo="Dati fissi">
            <div class="periodo-riga ente-dati-recapiti">
                <x-campo name="codice_fiscale" label="Codice fiscale" required codice maxlength="16" :value="$associazione->codice_fiscale" />
                <x-campo name="email" label="Email" tipo="email" maxlength="255" :value="$associazione->email" aiuto="Segreteria: avvisi e «rispondi a» delle email" />
                <x-campo name="email_pec" label="PEC" tipo="email" maxlength="255" :value="$associazione->email_pec" />
                <x-campo name="telefono" label="Telefono" maxlength="30" :value="$associazione->telefono" />
            </div>
            <div class="periodo-riga ente-dati-banca">
                <x-campo name="banca_nome" label="Banca" maxlength="255" :value="$associazione->banca_nome" />
                <x-campo name="iban" label="IBAN" codice maxlength="40" :value="$associazione->iban" aiuto="Va nell'email di accoglimento per il bonifico della quota. Gli spazi si tolgono da soli." />
                <x-campo name="bic_swift" label="BIC" codice maxlength="15" :value="$associazione->bic_swift" />
            </div>
        </x-card>
        <div class="form-actions">
            <a href="{{ route('impostazioni.index') }}" class="btn btn-ghost">Annulla</a>
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Salva</button>
        </div>
    </form>
@endsection
