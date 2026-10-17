@extends('layouts.app')

@section('title', $utente->exists ? 'Modifica utente' : 'Nuovo utente')

{{--
    Utente del gestionale (v0.42.0, tavole UT-8a e UT-8b,
    analisi-collaudo-O.md §15). Gli utenti del CD nascono dalla pagina
    Consiglio Direttivo: qui ne cambiano solo email, Amministratore e stato.
    «Nuovo utente» serve agli esterni: Revisore o solo Amministratore.
--}}
@section('content')
    <x-page-header :titolo="$utente->exists ? 'Modifica' : 'Nuovo'" :evidenza="$utente->exists ? $utente->cognomeNome() : 'utente esterno'"
                   :indietro="route('utenti.index')" indietro-label="Utenti" />

    @if ($errors->has('ruoli') || $errors->has('attivo'))
        <x-avviso tipo="ko" titolo="Non salvato.">{{ $errors->first('ruoli') ?: $errors->first('attivo') }}</x-avviso>
    @endif

    <div class="pn-colonne">
        <x-card titolo="Dati e ruoli">
            <form method="POST" action="{{ $utente->exists ? route('utenti.update', $utente) : route('utenti.store') }}" class="form-grid">
                @csrf
                @if ($utente->exists) @method('PUT') @endif

                @if ($delCd)
                    <div class="form-field ut-intero">
                        <span class="form-label">Socio</span>
                        @if ($utente->socio)
                            <div class="ut-socio">
                                <div><strong>{{ $utente->socio->cognome }} {{ $utente->socio->nome }}</strong>
                                    @if ($utente->socio->stato === 'attivo')<x-badge variante="ok">Socio attivo</x-badge>@else<x-badge variante="neutral">Socio {{ $utente->socio->stato }}</x-badge>@endif
                                    <span class="td-sub">Cognome e nome dalla scheda del socio @if (auth()->user()->puoAccedere('soci'))· <a href="{{ route('soci.show', $utente->socio) }}">apri la scheda</a>@endif</span>
                                </div>
                            </div>
                        @else
                            <x-avviso tipo="warn" titolo="Non collegato a un socio.">{{ $utente->name }} ha ruoli del CD ma non è collegato al libro soci: si collega quando lo aggiungi nella pagina <a href="{{ route('governance.index') }}">Consiglio Direttivo</a> (stessa email).</x-avviso>
                        @endif
                    </div>
                @else
                    <x-campo name="cognome" label="Cognome" required :value="$utente->cognome" maxlength="100" autocomplete="off" />
                    <x-campo name="nome" label="Nome" required :value="$utente->nome" maxlength="100" autocomplete="off" />
                @endif

                <x-campo name="email" label="Email per entrare" tipo="email" required :value="$utente->email" maxlength="255" autocomplete="off" class="ut-intero"
                         aiuto="Serve per entrare e per le email «al ruolo» (tessere, scadenze, avvisi delle operazioni)." />

                @if ($delCd)
                    <div class="form-field ut-intero">
                        <span class="form-label">Ruoli dal Consiglio Direttivo</span>
                        <div class="ut-ruoli">
                            @forelse ($ruoliCd as $r)
                                <x-badge variante="info">{{ $etichette[$r] ?? $r }}</x-badge>
                            @empty
                                <span class="td-sub">nessuno: non è nel CD</span>
                            @endforelse
                        </div>
                        <div class="form-help">Si cambiano dalla pagina <a href="{{ route('governance.index') }}">Consiglio Direttivo</a>.</div>
                    </div>
                    <fieldset class="form-field ut-intero">
                        <legend class="form-label">In più</legend>
                        <label @class(['ut-ruolo', 'scelto' => $amministratore, 'spento' => $adminBloccato])>
                            <input type="checkbox" name="amministratore" value="1" @checked($amministratore) @disabled($adminBloccato)>
                            <span class="ut-ruolo-nome">Amministratore di sistema</span>
                            <span class="ut-ruolo-desc">{{ $descrizioni['amministratore'] }}@if ($adminBloccato) <strong>{{ $adminBloccato }}</strong>@endif</span>
                        </label>
                        @if ($adminBloccato && $amministratore)<input type="hidden" name="amministratore" value="1">@endif
                    </fieldset>
                @else
                    <fieldset class="form-field ut-intero">
                        <legend class="form-label">Ruolo<span class="obbligatorio" aria-hidden="true">*</span></legend>
                        <div class="ut-scelta-ruoli" x-data="{ ruolo: @js($ruoloEsterno) }">
                            <label class="ut-ruolo" :class="{ scelto: ruolo === 'revisore' }">
                                <input type="radio" name="ruolo" value="revisore" x-model="ruolo" @checked($ruoloEsterno === 'revisore') @disabled($propri && $adminBloccato)>
                                <span class="ut-ruolo-nome">Revisore</span>
                                <span class="ut-ruolo-desc">{{ $descrizioni['revisore'] }} <span class="norma">Statuto, art. 20.1</span></span>
                            </label>
                            <label class="ut-ruolo" :class="{ scelto: ruolo === 'amministratore' }">
                                <input type="radio" name="ruolo" value="amministratore" x-model="ruolo" @checked($ruoloEsterno === 'amministratore') @disabled($propri && $adminBloccato)>
                                <span class="ut-ruolo-nome">Solo Amministratore</span>
                                <span class="ut-ruolo-desc">Assistenza tecnica, senza cariche: legge tutto, scrive solo gli Utenti. Uno solo.</span>
                            </label>
                        </div>
                        @if ($propri && $adminBloccato)<input type="hidden" name="ruolo" value="{{ $ruoloEsterno }}">@endif
                        <div class="form-help">Per i membri del CD non serve creare l'utente: nasce quando li aggiungi nella pagina <a href="{{ route('governance.index') }}">Consiglio Direttivo</a>.</div>
                    </fieldset>
                @endif

                <div class="ut-intero">
                    @if ($propri)
                        <input type="hidden" name="attivo" value="1">
                        <p class="form-help">È il tuo utente: non puoi disattivarlo.</p>
                    @else
                        <x-spunta name="attivo" label="Attivo (può entrare nel gestionale)" :checked="(bool) old('attivo', $utente->attivo)" />
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
                <p>Gli utenti del CD nascono dalla pagina <strong>Consiglio Direttivo</strong>, scegliendo il socio: qui si cambiano solo email, Amministratore e stato.</p>
                <p>«Nuovo utente» serve agli <strong>esterni</strong>: il Revisore o un Amministratore che non è socio.</p>
                <p>Uno solo per ruolo fra gli utenti attivi: Revisore, Amministratore. L'Amministratore non si unisce a Presidente e Revisore.</p>
                <p>Deve restare <strong>almeno un Presidente attivo</strong>. Chi gestisce gli utenti senza essere Presidente non cambia i propri ruoli; l'utente del Presidente lo modificano solo il Presidente e l'Amministratore.</p>
            </x-card>
        </aside>
    </div>
@endsection
