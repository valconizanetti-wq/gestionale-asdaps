{{--
    Impostazioni › Ente (profilo-ente.md; tavole "Profilo dell'ente —
    dati, periodi, iscrizioni online" e "— telefono"). Ente in vigore oggi,
    dati fissi, periodi con lo stato del passaggio, apertura delle
    iscrizioni online. Le azioni compaiono solo al Presidente.
--}}
@extends('layouts.app')

@section('title', "Profilo dell'ente")

@section('content')
    @php
        $oggi = today();
        $forme = \App\Models\AssociazionePeriodo::FORME;
        $regimi = \App\Models\AssociazionePeriodo::REGIMI;
    @endphp

    <x-page-header titolo="Profilo" evidenza="dell'ente" sottotitolo="Denominazione, forma, sede e regime per periodo · iscrizioni online">
        <x-slot:azioni>
            @if ($presidente)
                <a href="{{ route('impostazioni.periodi.create') }}" class="btn btn-primary"><x-icona nome="piu" width="14" height="14" /> Nuovo periodo</a>
            @endif
        </x-slot:azioni>
    </x-page-header>

    @if ($periodi->isEmpty())
        <x-avviso tipo="warn" titolo="Nessun periodo inserito.">Documenti e pagine usano i valori provvisori (denominazione «{{ $mostrato->denominazione }}»). Inserisci il primo periodo dell'ente.</x-avviso>
    @elseif (! $inVigore)
        <x-avviso tipo="warn" titolo="Nessun periodo in vigore oggi ({{ $oggi->format('d/m/Y') }}).">Il primo periodo comincia il {{ $periodi->first()->dal->format('d/m/Y') }}: fino ad allora documenti e pagine usano i suoi dati.</x-avviso>
    @endif

    <div class="ente-griglia">
        <x-card :titolo="$inVigore ? 'Ente in vigore oggi' : ($periodi->isEmpty() ? 'Valori provvisori' : 'Primo periodo, dal ' . $mostrato->dal->format('d/m/Y'))">
            <dl class="dati-griglia">
                <x-dato label="Denominazione" :valore="$mostrato->denominazione" />
                <x-dato label="Forma giuridica">
                    {{ $mostrato->etichettaForma() }}{{ $mostrato->sigla ? ' · ' . $mostrato->sigla : '' }}
                    @if ($forme[$mostrato->forma_giuridica][2] ?? null)
                        <span class="norma dato-norma">{{ $forme[$mostrato->forma_giuridica][2] }}</span>
                    @endif
                </x-dato>
                <x-dato label="Sede legale" :valore="$mostrato->sede_legale" />
                <x-dato label="Regime fiscale">
                    {{ $mostrato->etichettaRegime() }}
                    @if ($regimi[$mostrato->regime_fiscale][1] ?? null)
                        <span class="norma dato-norma">{{ $regimi[$mostrato->regime_fiscale][1] }}</span>
                    @endif
                </x-dato>
                <x-dato label="Ente di affiliazione" :valore="$mostrato->ente_affiliazione" />
                <x-dato label="Statuto e informativa" :valore="'Statuto ' . $mostrato->statuto_versione . ' · informativa ' . $mostrato->informativa_versione" />
                @if ($mostrato->sportivaDilettantistica())
                    <x-dato label="Iscrizione al RASD" :valore="$mostrato->iscrittoRasd() ? 'N. ' . $mostrato->rasd_numero . ' del ' . $mostrato->rasd_data_iscrizione->format('d/m/Y') : null" />
                @endif
            </dl>
        </x-card>

        <x-card titolo="Dati fissi dell'ente">
            @if ($presidente)
                <x-slot:azioni><a href="{{ route('impostazioni.ente.dati') }}" class="btn btn-secondary btn-sm">Modifica</a></x-slot:azioni>
            @endif
            <dl class="dati-griglia">
                <x-dato label="Codice fiscale" :valore="$associazione->codice_fiscale" codice />
                <x-dato label="Email" :valore="$associazione->email" />
                <x-dato label="PEC" :valore="$associazione->email_pec" />
                <x-dato label="Telefono" :valore="$associazione->telefono" />
                <x-dato label="Banca" :valore="$associazione->banca_nome" />
                <x-dato label="IBAN">
                    @if ($associazione->iban)
                        <span class="code">{{ $associazione->iban }}</span>
                    @else
                        <span class="testo-urgente">Da inserire</span>
                        <span class="norma dato-norma">Senza IBAN l'email di accoglimento mostra un segnaposto</span>
                    @endif
                </x-dato>
                <x-dato label="BIC" :valore="$associazione->bic_swift" codice />
            </dl>
        </x-card>
    </div>

    <x-card titolo="Periodi dell'ente" senza-padding class="ente-periodi">
        @if ($periodi->isEmpty())
            <x-vuoto>Nessun periodo.</x-vuoto>
        @else
            <table class="data-table responsive">
                <thead>
                    <tr>
                        <th scope="col">Dal</th>
                        <th scope="col">Denominazione e forma</th>
                        <th scope="col">Regime fiscale</th>
                        <th scope="col">Testi</th>
                        <th scope="col">Stato</th>
                        <th scope="col"><span class="sr-only">Azioni</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($periodi as $periodo)
                        @php
                            $fine = \App\Services\ProfiloEnte::fine($periodo);
                            $futuro = $periodo->dal->gt($oggi);
                            $lista = $liste[$periodo->id] ?? null;
                            $fatte = $lista ? collect($lista)->where('fatta', true)->count() : 0;
                        @endphp
                        <tr>
                            <td class="td-primario">
                                <span class="td-name">{{ $periodo->dal->format('d/m/Y') }}</span>
                                <span class="td-sub">{{ $fine ? 'al ' . $fine->format('d/m/Y') : 'senza fine' }}</span>
                            </td>
                            <td data-label="Denominazione e forma">
                                <span class="td-pila">
                                <span>{{ $periodo->denominazione }}</span>
                                <span class="td-sub">{{ $periodo->etichettaForma() . ($periodo->sportivaDilettantistica() ? ' · ' . ($periodo->iscrittoRasd() ? 'RASD n. ' . $periodo->rasd_numero : 'RASD da inserire') : '') }}</span>
                                </span>
                            </td>
                            <td data-label="Regime fiscale">{{ $periodo->etichettaRegime() }}</td>
                            <td data-label="Testi">Statuto {{ $periodo->statuto_versione }} · informativa {{ $periodo->informativa_versione }}</td>
                            <td data-label="Stato">
                                @if ($inVigore && $inVigore->is($periodo))
                                    <x-badge variante="ok">In vigore</x-badge>
                                @elseif (! $futuro)
                                    <x-badge variante="neutral">Concluso</x-badge>
                                @elseif ($fatte === count($lista))
                                    <x-badge variante="ok">Pronto · {{ $fatte }} su {{ count($lista) }}</x-badge>
                                @else
                                    <x-badge variante="warn">Passaggio · {{ $fatte }} su {{ count($lista) }}</x-badge>
                                @endif
                            </td>
                            <td class="td-azioni">
                                <div class="riga-azioni">
                                    @if ($futuro)
                                        <a href="{{ route('impostazioni.periodi.passaggio', $periodo) }}" class="btn btn-ghost btn-sm">Lista di controllo</a>
                                    @endif
                                    @if ($presidente)
                                        <a href="{{ route('impostazioni.periodi.edit', $periodo) }}" class="btn btn-ghost btn-sm">Modifica</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="table-footer">Ogni documento usa il periodo della sua data: una ricevuta del {{ $periodi->first()->dal->year }} ristampata dopo il passaggio riporta ancora i dati di allora. Un periodo finisce il giorno prima del successivo.</p>
        @endif
    </x-card>

    <x-card titolo="Iscrizioni online">
        <div class="iscrizioni-stato">
            <div>
                <p class="iscrizioni-stato-titolo">Le iscrizioni online sono <strong @class(['testo-urgente' => ! $iscrizioniAperte])>{{ $iscrizioniAperte ? 'aperte' : 'chiuse' }}</strong></p>
                <p class="form-help">
                    @if ($iscrizioniAperte)
                        Chiunque apre /iscrizione può presentare una domanda.
                    @else
                        Chi apre /iscrizione vede «Iscrizioni online non ancora aperte». Chi è collegato all'area riservata può provare il modulo: le domande nascono di prova.
                    @endif
                </p>
            </div>
            @if ($presidente)
                <form method="POST" action="{{ route('impostazioni.iscrizioni') }}">
                    @csrf
                    <input type="hidden" name="aperte" value="{{ $iscrizioniAperte ? 0 : 1 }}">
                    <button type="submit" @class(['btn', 'btn-primary' => ! $iscrizioniAperte, 'btn-secondary' => $iscrizioniAperte])>{{ $iscrizioniAperte ? 'Chiudi le iscrizioni' : 'Apri le iscrizioni' }}</button>
                </form>
            @endif
        </div>
    </x-card>
@endsection
