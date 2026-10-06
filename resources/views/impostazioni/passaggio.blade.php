{{--
    Lista di controllo del passaggio a un nuovo periodo (profilo-ente.md;
    tavola "Profilo dell'ente — lista di controllo del passaggio"). Le voci
    automatiche le verifica il gestionale; le altre le spunta il Presidente.
    Alla data di inizio non serve nessuna azione: il periodo vale da sé.
--}}
@extends('layouts.app')

@php
    $fatte = collect($voci)->where('fatta', true)->count();
    $titolo = $periodo->sigla ? 'a ' . $periodo->sigla : 'al nuovo periodo';
@endphp

@section('title', 'Passaggio ' . $titolo)

@section('content')
    <x-page-header titolo="Passaggio" :evidenza="$titolo"
                   :sottotitolo="'Periodo dal ' . $periodo->dal->format('d/m/Y') . ' · ' . $fatte . ' voci su ' . count($voci) . ' completate'"
                   :indietro="route('impostazioni.index')" indietro-label="Profilo dell'ente">
        <x-slot:azioni>
            @if ($presidente)
                <a href="{{ route('impostazioni.periodi.edit', $periodo) }}" class="btn btn-secondary">Modifica il periodo</a>
            @endif
        </x-slot:azioni>
    </x-page-header>

    <div class="passaggio-colonne">
        <x-card titolo="Lista di controllo" senza-padding>
            <ol class="lista-controllo">
                @foreach ($voci as $voce)
                    <li class="lista-controllo-voce">
                        @if ($voce['stato'] === 'manuale')
                            @if ($presidente)
                                <form method="POST" action="{{ route('impostazioni.periodi.spunta', $periodo) }}" class="lista-controllo-spunta">
                                    @csrf
                                    <input type="hidden" name="codice" value="{{ $voce['codice'] }}">
                                    <input type="hidden" name="fatta" value="{{ $voce['fatta'] ? 0 : 1 }}">
                                    <button type="submit" @class(['lista-controllo-casella', 'fatta' => $voce['fatta']]) aria-label="{{ $voce['titolo'] }}: {{ $voce['fatta'] ? 'togli la spunta' : 'segna come fatto' }}">
                                        @if ($voce['fatta'])
                                            <x-icona nome="ok" width="14" height="14" />
                                        @endif
                                    </button>
                                </form>
                            @else
                                <span @class(['lista-controllo-casella', 'fatta' => $voce['fatta']]) aria-hidden="true"></span>
                            @endif
                        @else
                            <span @class(['lista-controllo-segno', 'ok' => $voce['fatta'], 'ko' => ! $voce['fatta']]) aria-hidden="true">
                                @if ($voce['fatta'])
                                    <x-icona nome="ok" width="12" height="12" />
                                @endif
                            </span>
                        @endif
                        <div class="lista-controllo-testo">
                            <span class="lista-controllo-titolo">{{ $voce['titolo'] }}</span>
                            <span class="lista-controllo-desc">{{ $voce['descrizione'] }}</span>
                            @if ($voce['norma'])
                                <span class="norma">{{ $voce['norma'] }}</span>
                            @endif
                        </div>
                        <div class="lista-controllo-stato">
                            @if ($voce['stato'] === 'manuale')
                                <x-badge :variante="$voce['fatta'] ? 'ok' : 'neutral'">{{ $voce['fatta'] ? 'Fatto' : 'A mano' }}</x-badge>
                            @else
                                <x-badge :variante="$voce['fatta'] ? 'ok' : 'ko'">{{ $voce['fatta'] ? 'Verificato' : 'Da fare' }}</x-badge>
                            @endif
                            @if ($voce['link'] === 'modifica' && ! $voce['fatta'] && $presidente)
                                <a href="{{ route('impostazioni.periodi.edit', $periodo) }}">Modifica il periodo</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-card>

        <div class="passaggio-laterale">
            <x-avviso tipo="info" :titolo="'Cosa succede il ' . $periodo->dal->format('d/m/Y') . '.'">Senza altre azioni: denominazione e sede nuove su documenti, email e pagine pubbliche; menu secondo la nuova forma (per esempio Compliance RASD); testi in vigore del periodo. Le domande presentate prima conservano statuto e informativa che hanno accettato.</x-avviso>
            <x-card titolo="Prima e dopo">
                <dl class="dati-elenco">
                    @if ($precedente)
                        <x-dato :label="'Fino al ' . $periodo->dal->copy()->subDay()->format('d/m/Y')" :valore="$precedente->denominazione . ' · ' . $precedente->etichettaRegime()" />
                    @endif
                    <x-dato :label="'Dal ' . $periodo->dal->format('d/m/Y')" :valore="$periodo->denominazione . ' · ' . $periodo->etichettaRegime()" />
                </dl>
            </x-card>
        </div>
    </div>
@endsection
