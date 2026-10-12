{{--
    Riga «Affiliazione e assicurazione» di Impostazioni › Ente (tavole SC-2,
    SC-3, v0.38.0). La scadenza la tiene lo Scadenzario: qui si legge, e
    «Rinnovata» la aggiorna insieme al pagamento (un solo dato). Stati del
    riquadro: valida, in scadenza (entro il preavviso), scaduta, senza giorni
    (la data è nella riga «Scadenza»; v0.38.1). «Modifica»
    solo al Presidente: codice, referente e contatto; compagnia, numero e
    copertura della polizza.
--}}
@use('App\Support\Importo')
@php
    $vaiAllaScadenza = fn ($s) => $s && auth()->user()->puoAccedere('scadenzario') ? route('scadenzario.edit', $s) : null;
    $statoRiquadro = function ($s) {
        if (! $s) {
            return ['Da inserire nello Scadenzario', 'neutral'];
        }
        $st = $s->stato();

        return match ($st['stato']) {
            // Solo lo stato: la data è nella riga «Scadenza» (collaudo M9, v0.38.1)
            'scaduta' => ['Scaduta', 'ko'],
            'in_scadenza' => ['In scadenza', 'warn'],
            default => ['Valida', 'ok'],
        };
    };
    $ultimo = function ($s) {
        $r = $s?->ultimoRinnovo;
        if (! $r) {
            return null;
        }

        return $r->fatto_il->format('d/m/Y') . ($r->importo !== null ? ' · ' . Importo::it($r->importo, true) : '');
    };
    $ente = $mostrato->ente_affiliazione;
    $bagA = $errors->getBag('affiliazione');
    $bagP = $errors->getBag('polizza');
@endphp

<div class="ente-griglia ente-riga">
    {{-- Affiliazione all'EPS (SC-2) --}}
    <x-card titolo="Affiliazione all'EPS" class="sc-riquadro">
        @if ($presidente)
            <x-slot:azioni><button type="button" class="btn btn-secondary btn-sm" x-data x-on:click="$dispatch('apri-modale', 'modifica-affiliazione')">Modifica</button></x-slot:azioni>
        @endif
        @php [$statoA, $varA] = $statoRiquadro($affiliazione); @endphp
        <dl class="dati-griglia">
            <x-dato label="Ente" :valore="$ente" />
            <x-dato label="Stato"><x-badge :variante="$varA">{{ $statoA }}</x-badge></x-dato>
            <x-dato label="Codice di affiliazione" :valore="$associazione->rete_affiliazione_codice" codice />
            <x-dato label="Scadenza">
                @if ($affiliazione)<strong>{{ $affiliazione->data_scadenza->format('d/m/Y') }}</strong>@else<span class="dato-vuoto">—</span>@endif
            </x-dato>
            <x-dato label="Comitato / referente">
                @if ($associazione->rete_affiliazione_referente || $associazione->rete_affiliazione_contatto)
                    {{ $associazione->rete_affiliazione_referente }}
                    @if ($associazione->rete_affiliazione_contatto)<span class="td-sub">{{ $associazione->rete_affiliazione_contatto }}</span>@endif
                @else
                    <span class="dato-vuoto">—</span>
                @endif
            </x-dato>
            <x-dato label="Ultimo rinnovo">
                @if ($u = $ultimo($affiliazione))
                    {{ $u }}
                    @if ($affiliazione->ultimoRinnovo->movimento && auth()->user()->puoAccedere('prima_nota'))
                        · <a href="{{ route('prima-nota.show', $affiliazione->ultimoRinnovo->movimento) }}" class="code">movimento PN</a>
                    @endif
                @else
                    <span class="dato-vuoto">—</span>
                @endif
            </x-dato>
        </dl>
        <div class="sc-riquadro-piede">
            <p class="form-help">La scadenza si aggiorna con «Rinnovata» nello Scadenzario, insieme al pagamento.</p>
            @if ($link = $vaiAllaScadenza($affiliazione))
                <a href="{{ $link }}" class="btn btn-secondary btn-sm">Vai alla scadenza</a>
            @endif
        </div>
    </x-card>

    {{-- Polizza RC (SC-3) --}}
    <x-card titolo="Polizza RC" class="sc-riquadro">
        @if ($presidente)
            <x-slot:azioni><button type="button" class="btn btn-secondary btn-sm" x-data x-on:click="$dispatch('apri-modale', 'modifica-polizza')">Modifica</button></x-slot:azioni>
        @endif
        @php [$statoP, $varP] = $statoRiquadro($polizza); @endphp
        <dl class="dati-griglia">
            <x-dato label="Compagnia" :valore="$associazione->polizza_compagnia" />
            <x-dato label="Stato"><x-badge :variante="$varP">{{ $statoP }}</x-badge></x-dato>
            <x-dato label="Numero di polizza" :valore="$associazione->polizza_numero" codice />
            <x-dato label="Scadenza">
                @if ($polizza)<strong>{{ $polizza->data_scadenza->format('d/m/Y') }}</strong>@else<span class="dato-vuoto">—</span>@endif
            </x-dato>
            <x-dato label="Copertura" :valore="$associazione->polizza_copertura" />
            <x-dato label="Ultimo rinnovo">
                @if ($u = $ultimo($polizza))
                    {{ $u }}
                    @if ($polizza->ultimoRinnovo->movimento && auth()->user()->puoAccedere('prima_nota'))
                        · <a href="{{ route('prima-nota.show', $polizza->ultimoRinnovo->movimento) }}" class="code">movimento PN</a>
                    @endif
                @else
                    <span class="dato-vuoto">—</span>
                @endif
            </x-dato>
        </dl>
        <div class="sc-riquadro-piede">
            <p class="form-help">Infortuni dei soci: assicurazione base della tessera {{ $ente ?: 'dell\'EPS' }}@if (Route::has('tesseramento.index') && auth()->user()->puoAccedere('tesseramento')) (pagina <a href="{{ route('tesseramento.index') }}">Tessere</a>)@endif.</p>
            @if ($link = $vaiAllaScadenza($polizza))
                <a href="{{ $link }}" class="btn btn-secondary btn-sm">Vai alla scadenza</a>
            @endif
        </div>
    </x-card>
</div>

@if ($avvisoAffiliazione && $avvisoAffiliazione['tipo'] === 'ko')
    <div class="sc-avviso sc-avviso-ko" role="alert">
        <div>
            <strong>{{ $avvisoAffiliazione['titolo'] }}</strong>
            <span>{{ $avvisoAffiliazione['testo'] }}</span>
        </div>
        @if ($link = $vaiAllaScadenza($affiliazione))
            <a href="{{ route('scadenzario.index') }}" class="btn btn-primary">Vai alla scadenza</a>
        @endif
    </div>
@endif

@if ($presidente)
    @push('modals')
        <x-modale nome="modifica-affiliazione" titolo="Affiliazione all'EPS" :aperta="$bagA->any()">
            <form method="POST" action="{{ route('impostazioni.ente.affiliazione') }}" class="form-modale" id="form-affiliazione">
                @csrf
                @method('PUT')
                @if ($bagA->any())<x-avviso tipo="ko">{{ $bagA->first() }}</x-avviso>@endif
                <div class="form-grid">
                    <div class="form-field">
                        <span class="form-label">Ente</span>
                        <span class="pr-valore">{{ $ente ?: '—' }}</span>
                        <div class="form-help">Dal periodo in vigore; si cambia nei periodi.</div>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="aff-codice">Codice di affiliazione</label>
                        <input id="aff-codice" name="rete_affiliazione_codice" type="text" class="form-input code" maxlength="255"
                               value="{{ $bagA->any() ? old('rete_affiliazione_codice') : $associazione->rete_affiliazione_codice }}">
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="aff-referente">Comitato / referente</label>
                        <input id="aff-referente" name="rete_affiliazione_referente" type="text" class="form-input" maxlength="255"
                               placeholder="Comitato · referente"
                               value="{{ $bagA->any() ? old('rete_affiliazione_referente') : $associazione->rete_affiliazione_referente }}">
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="aff-contatto">Contatto del referente</label>
                        <input id="aff-contatto" name="rete_affiliazione_contatto" type="text" class="form-input" maxlength="255" placeholder="email o telefono"
                               value="{{ $bagA->any() ? old('rete_affiliazione_contatto') : $associazione->rete_affiliazione_contatto }}">
                    </div>
                </div>
                <x-avviso tipo="info" titolo="La scadenza non si scrive qui.">La tiene lo Scadenzario: «Rinnovata» registra il pagamento e sposta la data di un anno. Così il dato è in un solo posto.</x-avviso>
            </form>
            <x-slot:piede>
                <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                <button type="submit" form="form-affiliazione" class="btn btn-primary">Salva</button>
            </x-slot:piede>
        </x-modale>

        <x-modale nome="modifica-polizza" titolo="Polizza RC" :aperta="$bagP->any()">
            <form method="POST" action="{{ route('impostazioni.ente.polizza') }}" class="form-modale" id="form-polizza">
                @csrf
                @method('PUT')
                @if ($bagP->any())<x-avviso tipo="ko">{{ $bagP->first() }}</x-avviso>@endif
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label" for="pol-compagnia">Compagnia</label>
                        <input id="pol-compagnia" name="polizza_compagnia" type="text" class="form-input" maxlength="255" placeholder="Unipol, tramite CSEN"
                               value="{{ $bagP->any() ? old('polizza_compagnia') : $associazione->polizza_compagnia }}">
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="pol-numero">Numero di polizza</label>
                        <input id="pol-numero" name="polizza_numero" type="text" class="form-input code" maxlength="100"
                               value="{{ $bagP->any() ? old('polizza_numero') : $associazione->polizza_numero }}">
                    </div>
                </div>
                <div class="form-field">
                    <label class="form-label" for="pol-copertura">Copertura</label>
                    <input id="pol-copertura" name="polizza_copertura" type="text" class="form-input" maxlength="255"
                           value="{{ $bagP->any() ? old('polizza_copertura') : $associazione->polizza_copertura }}">
                </div>
                <x-avviso tipo="info" titolo="La scadenza non si scrive qui.">La tiene lo Scadenzario: «Rinnovata» registra il pagamento e sposta la data di un anno. Così il dato è in un solo posto.</x-avviso>
            </form>
            <x-slot:piede>
                <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                <button type="submit" form="form-polizza" class="btn btn-primary">Salva</button>
            </x-slot:piede>
        </x-modale>
    @endpush
@endif
