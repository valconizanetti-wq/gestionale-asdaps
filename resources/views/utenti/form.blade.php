@extends('layouts.app')

@section('title', $utente->exists ? 'Modifica utente' : 'Nuovo utente')

@use('App\Services\Utenti')

@section('content')
    <x-page-header :titolo="$utente->exists ? 'Modifica' : 'Nuovo'" :evidenza="$utente->exists ? $utente->name : 'utente'"
                   :indietro="route('utenti.index')" indietro-label="Utenti" />

    @if ($errors->has('ruoli') || $errors->has('attivo'))
        <x-avviso tipo="ko" titolo="Non salvato.">{{ $errors->first('ruoli') ?: $errors->first('attivo') }}</x-avviso>
    @endif

    <div class="pn-colonne">
        <x-card titolo="Dati e ruoli">
            <form method="POST" action="{{ $utente->exists ? route('utenti.update', $utente) : route('utenti.store') }}" class="form-grid"
                  x-data="{
                      scelti: @js(array_values($scelti)),
                      cariche: @js(Utenti::CARICHE),
                      {{-- Le stesse regole di App\Services\Utenti::erroreCombinazione --}}
                      ammesso(codice, scelti) {
                          if (scelti.includes(codice)) return true;
                          if (codice === 'revisore') return scelti.length === 0;
                          if (scelti.includes('revisore')) return false;
                          if (codice === 'consigliere') return ! scelti.some(c => this.cariche.includes(c));
                          if (this.cariche.includes(codice)) return ! scelti.includes('consigliere');
                          return true;
                      },
                  }">
                @csrf
                @if ($utente->exists) @method('PUT') @endif

                <x-campo name="name" label="Nome e cognome" required :value="$utente->name" maxlength="200" autocomplete="off" />
                <x-campo name="email" label="Email" tipo="email" required :value="$utente->email" maxlength="255" autocomplete="off"
                         aiuto="Serve per entrare e per le email «al ruolo» (tessere, scadenze, avvisi delle operazioni)." />

                <fieldset class="form-field ut-intero">
                    <legend class="form-label">Ruoli<span class="obbligatorio" aria-hidden="true">*</span></legend>
                    <div class="ut-scelta-ruoli">
                        @foreach ($ruoli as $codice => $label)
                            @php $fisso = in_array($codice, $bloccati, true); @endphp
                            {{-- Spento: ruolo che chi compila non può cambiare (fisso), o combinazione non ammessa (Alpine) --}}
                            <label class="ut-ruolo"
                                   :class="{ scelto: scelti.includes('{{ $codice }}'), spento: {{ $fisso ? 'true' : 'false' }} || !ammesso('{{ $codice }}', scelti) }">
                                <input type="checkbox" name="ruoli[]" value="{{ $codice }}" x-model="scelti"
                                       @disabled($fisso) :disabled="{{ $fisso ? 'true' : 'false' }} || !ammesso('{{ $codice }}', scelti)">
                                <span class="ut-ruolo-nome">{{ $label }}</span>
                                <span class="ut-ruolo-desc">{{ $descrizioni[$codice] ?? '' }}
                                    @if ($codice === 'revisore') <span class="norma">Statuto, art. 20.1</span>@endif
                                </span>
                            </label>
                            @if ($fisso && in_array($codice, $scelti, true))
                                <input type="hidden" name="ruoli[]" value="{{ $codice }}">
                            @endif
                        @endforeach
                    </div>
                    <div class="form-help" x-show="scelti.includes('revisore') || scelti.includes('consigliere')" x-cloak>
                        <span x-show="scelti.includes('revisore')">Il Revisore non si unisce ad altri ruoli: l'Organo di controllo è fuori dal CD.</span>
                        <span x-show="scelti.includes('consigliere')">Consigliere è per chi è nel CD senza cariche: con Presidente, Segretario o Tesoriere non serve.</span>
                    </div>
                    @if ($motivoBloccati)<div class="form-help">{{ $motivoBloccati }}</div>@endif
                </fieldset>

                <div class="ut-intero">
                    @if ($propri)
                        <input type="hidden" name="attivo" value="1">
                        <p class="form-help">È il tuo utente: non puoi disattivarlo.</p>
                    @else
                        <x-spunta name="attivo" label="Attivo (può entrare nel gestionale)" :checked="(bool) $utente->attivo" />
                    @endif
                </div>

                @unless ($utente->exists)
                    <div class="ut-intero">
                        <x-spunta name="invia_link" label="Mandagli subito il link per scegliere la password" :checked="true" />
                    </div>
                @endunless

                <div class="form-actions ut-intero">
                    <a href="{{ route('utenti.index') }}" class="btn btn-ghost">Annulla</a>
                    <button type="submit" class="btn btn-primary">{{ $utente->exists ? 'Salva' : 'Crea l\'utente' }}</button>
                </div>
            </form>
        </x-card>

        <aside class="pn-colonna">
            <x-card titolo="Regole" class="pn-aiuto">
                <p>Almeno un ruolo per utente.</p>
                <p>Deve restare <strong>almeno un Presidente attivo</strong>: all'ultimo non si toglie il ruolo né si disattiva l'utente.</p>
                <p>Il Segretario non dà né toglie il ruolo di Presidente, non modifica l'utente del Presidente e non cambia i propri ruoli.</p>
                <p>I cambi di ruoli vanno nell'Audit Log con il prima e il dopo, e gli altri del CD che gestiscono gli utenti ricevono un avviso.</p>
            </x-card>
        </aside>
    </div>
@endsection
