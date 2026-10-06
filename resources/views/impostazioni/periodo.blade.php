{{--
    Nuovo periodo o modifica (profilo-ente.md; tavola "Profilo dell'ente —
    nuovo periodo"). Un periodo già cominciato mostra i dati in sola lettura:
    si cambiano solo RASD e atto, perché i documenti emessi nel periodo ne
    riportano i dati. Riferimento normativo in piccolo sotto forma e regime.
--}}
@extends('layouts.app')

@php
    $nuovo = ! $periodo->exists;
    $forme = \App\Models\AssociazionePeriodo::FORME;
    $regimi = \App\Models\AssociazionePeriodo::REGIMI;
    $formaScelta = old('forma_giuridica', $periodo->forma_giuridica);
    $azione = $nuovo ? route('impostazioni.periodi.store') : route('impostazioni.periodi.update', $periodo);
    $opzioniVersioni = fn (string $tipo, ?string $attuale) => collect($versioni[$tipo])
        ->mapWithKeys(fn ($v) => [$v => $v])
        ->when($attuale && ! in_array($attuale, $versioni[$tipo], true), fn ($c) => $c->put($attuale, $attuale . ' (non ancora pubblicata)'))
        ->all();
@endphp

@section('title', $nuovo ? 'Nuovo periodo' : 'Periodo dal ' . $periodo->dal->format('d/m/Y'))

@section('content')
    <x-page-header :titolo="$nuovo ? 'Nuovo' : 'Periodo'" :evidenza="$nuovo ? 'periodo' : 'dal ' . $periodo->dal->format('d/m/Y')"
                   sottotitolo="Dal giorno indicato documenti, pagine e moduli usano questi dati"
                   :indietro="route('impostazioni.index')" indietro-label="Profilo dell'ente" />

    @if ($iniziato)
        <x-avviso tipo="info" titolo="Periodo già cominciato.">I documenti emessi nel periodo ne riportano i dati: si possono cambiare solo l'iscrizione al RASD e l'atto. Per una nuova forma o denominazione si inserisce un nuovo periodo.</x-avviso>
    @endif

    <form method="POST" action="{{ $azione }}" class="form-pagina" novalidate x-data="{ forma: @js($formaScelta), inviato: false }" x-on:submit="inviato = true">
        @csrf
        @unless ($nuovo)
            @method('PUT')
        @endunless

        <x-card titolo="Dati del periodo">
            @if ($iniziato)
                <dl class="dati-griglia">
                    <x-dato label="Dal" :valore="$periodo->dal->format('d/m/Y')" />
                    <x-dato label="Denominazione" :valore="$periodo->denominazione" />
                    <x-dato label="Forma giuridica" :valore="$periodo->etichettaForma() . ($periodo->sigla ? ' · ' . $periodo->sigla : '')" />
                    <x-dato label="Sede legale" :valore="$periodo->sede_legale" />
                    <x-dato label="Regime fiscale" :valore="$periodo->etichettaRegime()" />
                    <x-dato label="Testi" :valore="'Statuto ' . $periodo->statuto_versione . ' · informativa ' . $periodo->informativa_versione" />
                </dl>
            @else
                <div class="periodo-riga periodo-riga-testa">
                    <x-campo name="dal" label="Dal" tipo="date" required :value="$periodo->dal" aiuto="Il periodo precedente finisce il giorno prima" />
                    <x-campo name="denominazione" label="Denominazione completa" required maxlength="255" :value="$periodo->denominazione" />
                    <x-campo name="sigla" label="Sigla dopo il nome" maxlength="20" :value="$periodo->sigla" aiuto="B.A.S., A.S.D.…" />
                </div>

                <fieldset @class(['scelta-gruppo', 'has-error' => $errors->has('forma_giuridica')])>
                    <legend>Forma giuridica <span class="obbligatorio" aria-hidden="true">*</span></legend>
                    <div class="scelta-opzioni">
                        @foreach ($forme as $codice => [$etichetta, $descrizione, $norma])
                            <label class="scelta scelta-estesa">
                                <input type="radio" name="forma_giuridica" value="{{ $codice }}" x-model="forma" @checked($formaScelta === $codice)>
                                <span class="scelta-testo">
                                    <span class="scelta-titolo">{{ $etichetta }}</span>
                                    <span class="scelta-desc">{{ [
                                        'associazione' => 'Come la B.A.S.: nessuna dicitura «sportiva dilettantistica».',
                                        'asd' => "Dicitura e sigla solo con l'iscrizione al RASD. Mostra Compliance RASD.",
                                        'ssd' => "Come l'A.S.D., in forma di società.",
                                        'aps' => 'Per gli enti del Terzo settore iscritti al RUNTS.',
                                    ][$codice] }}</span>
                                    <span class="norma">{{ $norma }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('forma_giuridica')<div class="form-error">{{ $message }}</div>@enderror
                </fieldset>

                <div class="periodo-riga periodo-riga-sede">
                    <x-campo name="sede_legale" label="Sede legale (forma ufficiale)" required maxlength="255" :value="$periodo->sede_legale" />
                    <x-campo name="forma_descrizione" label="Forma per esteso" maxlength="100" :value="$periodo->forma_descrizione" aiuto="Sotto il nome nella carta intestata, es. Base Associativa Sportiva" />
                </div>

                <fieldset @class(['scelta-gruppo', 'has-error' => $errors->has('regime_fiscale')])>
                    <legend>Regime fiscale <span class="obbligatorio" aria-hidden="true">*</span></legend>
                    <div class="scelta-opzioni tre">
                        @foreach ($regimi as $codice => [$etichetta, $norma])
                            <label class="scelta scelta-estesa">
                                <input type="radio" name="regime_fiscale" value="{{ $codice }}" @checked(old('regime_fiscale', $periodo->regime_fiscale) === $codice)>
                                <span class="scelta-testo">
                                    <span class="scelta-titolo">{{ $etichetta }}</span>
                                    <span class="scelta-desc">{{ [
                                        'non_commerciale' => 'Quote e corrispettivi specifici dei soci non imponibili.',
                                        'l398' => 'Attiva il monitoraggio del plafond e della tracciabilità.',
                                        'ordinario' => 'Nessun regime agevolato.',
                                    ][$codice] }}</span>
                                    @if ($norma)
                                        <span class="norma">{{ $norma }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('regime_fiscale')<div class="form-error">{{ $message }}</div>@enderror
                </fieldset>

                <div class="periodo-riga periodo-riga-testi">
                    <x-campo name="ente_affiliazione" label="Ente di affiliazione" maxlength="100" :value="$periodo->ente_affiliazione" />
                    <x-campo name="statuto_versione" label="Statuto in vigore" tipo="select" required :opzioni="$opzioniVersioni('statuto', $periodo->statuto_versione)" :value="$periodo->statuto_versione" aiuto="Versioni in resources/content/statuto" />
                    <x-campo name="informativa_versione" label="Informativa in vigore" tipo="select" required :opzioni="$opzioniVersioni('informativa', $periodo->informativa_versione)" :value="$periodo->informativa_versione" aiuto="Deve nominare la denominazione del periodo" />
                </div>
            @endif

            <div class="periodo-riga periodo-riga-rasd" @unless ($iniziato) x-show="forma === 'asd' || forma === 'ssd'" x-cloak @endunless>
                @if (! $iniziato || $periodo->sportivaDilettantistica())
                    <x-campo name="rasd_numero" label="Numero di iscrizione al RASD" maxlength="50" :value="$periodo->rasd_numero" aiuto="Da inserire quando arriva" />
                    <x-campo name="rasd_data_iscrizione" label="Data di iscrizione" tipo="date" :value="$periodo->rasd_data_iscrizione" />
                    <p class="norma">D.Lgs. 39/2021: con l'iscrizione si usa la dicitura «sportiva dilettantistica».</p>
                @endif
            </div>

            <x-campo name="atto" label="Atto che decide il periodo" maxlength="255" :value="$periodo->atto" aiuto="Es. Assemblea del … (verbale n. …)" />
        </x-card>

        <div class="form-actions">
            <a href="{{ route('impostazioni.index') }}" class="btn btn-ghost">Annulla</a>
            @if (! $nuovo && ! $iniziato)
                <button type="button" class="btn btn-danger" @click="$dispatch('apri-modale', 'elimina-periodo')"><x-icona nome="cestino" width="14" height="14" /> Elimina</button>
            @endif
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Salva il periodo</button>
        </div>
    </form>

    @if (! $nuovo && ! $iniziato)
        <x-modale nome="elimina-periodo" titolo="Eliminare il periodo?">
            <p>Il periodo dal {{ $periodo->dal->format('d/m/Y') }} non è ancora cominciato: si può eliminare. Il periodo precedente varrà anche dopo quella data.</p>
            <form method="POST" action="{{ route('impostazioni.periodi.destroy', $periodo) }}" id="form-elimina-periodo">
                @csrf
                @method('DELETE')
            </form>
            <x-slot:piede>
                <button type="button" class="btn btn-ghost" @click="aperta = false">Annulla</button>
                <button type="submit" form="form-elimina-periodo" class="btn btn-danger">Elimina</button>
            </x-slot:piede>
        </x-modale>
    @endif
@endsection
