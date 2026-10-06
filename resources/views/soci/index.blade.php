@extends('layouts.app')

@section('title', 'Soci')

@section('content')
    @php $puoModificare = auth()->user()->puoModificare('soci'); @endphp

    <x-page-header titolo="Gestione" evidenza="Soci" sottotitolo="Anagrafica, quote associative e tessere">
        <x-slot:azioni>
            <a href="{{ route('soci.pdf', request()->query()) }}" class="btn btn-secondary solo-desktop">
                <x-icona nome="download" width="14" height="14" /> PDF
            </a>
            <a href="{{ route('soci.export', request()->query()) }}" class="btn btn-secondary">
                <x-icona nome="download" width="14" height="14" /> CSV
            </a>
            @if ($linkPrivacy && ($linkPrivacy['senza'] + $linkPrivacy['precedente']) > 0)
                <button type="button" class="btn btn-secondary" @click="$dispatch('apri-modale', 'link-privacy')">
                    Link privacy <span class="conteggio-ko">{{ $linkPrivacy['senza'] + $linkPrivacy['precedente'] }}</span>
                </button>
            @endif
            @if ($puoModificare)
                <a href="{{ route('soci.create') }}" class="btn btn-primary">
                    <x-icona nome="piu" width="14" height="14" /> Nuovo socio
                </a>
            @endif
        </x-slot:azioni>
    </x-page-header>

    <x-sola-lettura sezione="soci" />

    <div class="kpi-row">
        <x-kpi label="Soci attivi" :valore="$kpi['attivi']" sub="Stato attivo"
               :href="route('soci.index')"
               :attivo="$filtri['stato'] === 'attivo' && $filtri['quota'] === '' && $filtri['tipo'] === '' && $filtri['q'] === ''" />
        <x-kpi label="Quota valida" :valore="$kpi['versata']" variante="ok"
               :sub="$kpi['attivi'] ? round($kpi['versata'] / $kpi['attivi'] * 100) . '% dei soci attivi' : null"
               :href="route('soci.index', ['quota' => 'versata'])" :attivo="$filtri['quota'] === 'versata'" />
        <x-kpi label="Da sollecitare" :valore="$kpi['da_versare']" :variante="$kpi['da_versare'] ? 'warn' : null"
               sub="Quota scaduta o non versata"
               :href="route('soci.index', ['quota' => 'da_versare'])" :attivo="$filtri['quota'] === 'da_versare'" />
        <x-kpi label="Minorenni" :valore="$kpi['minorenni']" sub="Calcolati dalla data di nascita" />
    </div>

    {{-- Filtri: su telefono resta visibile solo la ricerca, il resto si apre con "Filtri" --}}
    <form method="GET" action="{{ route('soci.index') }}" class="filter-bar" x-data="{ aperti: false }">
        <div class="form-field filtro-cerca">
            <label class="form-label" for="f-q">Cerca</label>
            <div class="form-input-wrap">
                <input id="f-q" name="q" type="search" value="{{ $filtri['q'] }}" class="form-input"
                       placeholder="Nome, cognome o codice fiscale" autocomplete="off">
            </div>
        </div>

        <button type="button" class="btn btn-secondary filtri-toggle" @click="aperti = !aperti" :aria-expanded="aperti.toString()" aria-controls="filtri-extra">
            <x-icona nome="filtro" width="14" height="14" /> Filtri
        </button>

        <div id="filtri-extra" class="filtri-extra" :class="{ 'aperti': aperti }">
            <div class="form-field">
                <label class="form-label" for="f-stato">Stato</label>
                <select id="f-stato" name="stato" class="form-select">
                    <option value="attivo" @selected($filtri['stato'] === 'attivo')>Attivi</option>
                    <option value="sospeso" @selected($filtri['stato'] === 'sospeso')>Sospesi</option>
                    <option value="cancellato" @selected($filtri['stato'] === 'cancellato')>Cancellati</option>
                    <option value="tutti" @selected($filtri['stato'] === 'tutti')>Tutti</option>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="f-tipo">Tipo socio</label>
                <select id="f-tipo" name="tipo" class="form-select">
                    <option value="">Tutti</option>
                    @foreach (\App\Http\Controllers\SociController::TIPI as $valore => $label)
                        <option value="{{ $valore }}" @selected($filtri['tipo'] === $valore)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="f-quota">Quota</label>
                <select id="f-quota" name="quota" class="form-select">
                    <option value="">Tutte</option>
                    <option value="versata" @selected($filtri['quota'] === 'versata')>Valida</option>
                    <option value="da_versare" @selected($filtri['quota'] === 'da_versare')>Da sollecitare</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Filtra</button>
    </form>

    <x-card senza-padding>
        @if ($soci->isEmpty())
            <x-vuoto>
                @if ($filtri['q'] !== '' || $filtri['tipo'] !== '' || $filtri['quota'] !== '' || $filtri['stato'] !== 'attivo')
                    Nessun socio corrisponde ai filtri. <a href="{{ route('soci.index') }}">Mostra tutti i soci attivi</a>
                @elseif ($puoModificare)
                    Ancora nessun socio. <a href="{{ route('soci.create') }}">Inserisci il primo socio</a>
                @else
                    Ancora nessun socio registrato.
                @endif
            </x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive">
                    <thead>
                        <tr>
                            @if ($mostraTessera)<th scope="col">N. tessera</th>@endif
                            <th scope="col">Cognome e nome</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Codice fiscale</th>
                            <th scope="col">Tesseram. CSEN</th>
                            <th scope="col">Quota</th>
                            <th scope="col">Documenti</th>
                            <th scope="col">Iscritto dal</th>
                            <th scope="col"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($soci as $socio)
                            @php
                                $quotaValida = $socio->quota_scadenza && $socio->quota_scadenza->gte(today());
                                $telefono = $socio->telefono;
                                if (! $telefono && $socio->isMinorenne()) { $telefono = $socio->tutore_telefono; }
                                $email = $socio->email ?: ($socio->isMinorenne() ? $socio->tutore_email : null);
                            @endphp
                            <tr>
                                @if ($mostraTessera)
                                    <td data-label="Tessera" class="td-solo-desktop"><span class="code">{{ $socio->numero_tessera ?? '—' }}</span></td>
                                @endif
                                <td class="td-primario">
                                    <a href="{{ route('soci.show', $socio) }}" class="td-name">{{ $socio->cognome }} {{ $socio->nome }}</a>
                                    @if ($socio->stato !== 'attivo')
                                        <x-badge-stato dominio="socio" :valore="$socio->stato" />
                                    @endif
                                </td>
                                <td data-label="Tipo">
                                    {{ \App\Http\Controllers\SociController::TIPI[$socio->tipo] ?? $socio->tipo }}
                                    @if ($socio->tipoIncoerenteConEta())
                                        <x-badge variante="warn">da aggiornare</x-badge>
                                    @endif
                                </td>
                                <td data-label="Codice fiscale" class="td-solo-desktop"><span class="code">{{ $socio->codice_fiscale }}</span></td>
                                <td data-label="CSEN">
                                    @if ($socio->tesserato)
                                        <x-badge variante="info">Tesserato</x-badge>
                                    @else
                                        <x-badge variante="warn">Da tesserare</x-badge>
                                    @endif
                                </td>
                                <td data-label="Quota">
                                    @if ($quotaValida)
                                        <x-badge variante="ok">✓ fino al {{ $socio->quota_scadenza->format('d/m/Y') }}</x-badge>
                                    @else
                                        <x-badge variante="warn">Da versare</x-badge>
                                    @endif
                                </td>
                                <td data-label="Documenti">
                                    @php $mancanti = $socio->documentiMancanti($certificatoRichiesto); @endphp
                                    @if ($mancanti === 0)
                                        <x-badge variante="ok">✓ Completi</x-badge>
                                    @else
                                        <a href="{{ route('soci.show', $socio) }}" class="badge-link"><x-badge variante="warn">{{ $mancanti }} {{ $mancanti === 1 ? 'mancante' : 'mancanti' }}</x-badge></a>
                                    @endif
                                </td>
                                <td data-label="Iscritto dal" class="td-solo-desktop">{{ $socio->data_prima_iscrizione?->format('d/m/Y') }}</td>
                                <td class="td-azioni">
                                    @if ($telefono)
                                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $telefono) }}" class="action-btn solo-mobile" aria-label="Chiama {{ $socio->nome }} {{ $socio->cognome }}"><x-icona nome="telefono" /></a>
                                    @endif
                                    @if ($email)
                                        <a href="mailto:{{ $email }}" class="action-btn" aria-label="Scrivi a {{ $socio->nome }} {{ $socio->cognome }}"><x-icona nome="mail" /></a>
                                    @endif
                                    <a href="{{ route('soci.show', $socio) }}" class="action-btn" aria-label="Apri la scheda di {{ $socio->nome }} {{ $socio->cognome }}"><x-icona nome="avanti" /></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-paginazione :elementi="$soci" nome="soci" />
        @endif
    </x-card>
@endsection

@push('modals')
    @if ($linkPrivacy && ($linkPrivacy['senza'] + $linkPrivacy['precedente']) > 0)
        @php
            $daInviare = $linkPrivacy['da_inviare']->count();
            $conRinvio = $linkPrivacy['con_rinvio']->count();
        @endphp
        <x-modale nome="link-privacy" titolo="Invia il link privacy">
            <form method="POST" action="{{ route('soci.privacy-link.tutti') }}" id="form-link-privacy" class="form-modale"
                  x-data="{ rinvio: false, inviato: false }" x-on:submit="inviato = true">
                @csrf
                <p>Ogni socio attivo che non ha letto l'informativa in vigore (versione {{ $linkPrivacy['versione'] }}) riceve un'email con il suo link personale; per i minori va al genitore.</p>
                <dl class="link-privacy-conti">
                    <div><dt>Senza consenso</dt><dd>{{ $linkPrivacy['senza'] }}</dd></div>
                    <div><dt>Versione precedente</dt><dd>{{ $linkPrivacy['precedente'] }}</dd></div>
                    <div class="evidenza"><dt>Riceveranno l'email</dt><dd x-text="rinvio ? {{ $conRinvio }} : {{ $daInviare }}">{{ $daInviare }}</dd></div>
                </dl>
                @if ($linkPrivacy['senza_email']->isNotEmpty())
                    <x-avviso tipo="warn"><strong>{{ $linkPrivacy['senza_email']->count() }} senza email</strong> (né propria né del tutore): {{ $linkPrivacy['senza_email']->map(fn ($s) => "{$s->cognome} {$s->nome}")->implode(', ') }}. Per loro: email nella scheda, oppure consenso in presenza o su modulo.</x-avviso>
                @endif
                @if ($linkPrivacy['in_attesa'] > 0)
                    <label class="link-privacy-rinvio">
                        <input type="checkbox" name="anche_in_attesa" value="1" x-model="rinvio">
                        <span>Manda di nuovo anche {{ $linkPrivacy['in_attesa'] === 1 ? "all'unico socio che ha" : "ai {$linkPrivacy['in_attesa']} che hanno" }} già un link non usato (il link nuovo sostituisce il vecchio)</span>
                    </label>
                    <p class="form-help">Senza questa spunta, chi ha già un link in attesa non riceve nulla: così un secondo clic non manda doppioni.</p>
                @endif
                @if ($conRinvio > 0)
                    <details class="link-privacy-elenco">
                        <summary>Vedi l'elenco</summary>
                        <ul>
                            @foreach ($linkPrivacy['con_rinvio'] as $s)
                                <li x-show="{{ $s->privacyLinkAperto ? 'rinvio' : 'true' }}">{{ $s->cognome }} {{ $s->nome }}{{ $s->privacyLinkAperto ? ' · link in attesa' : '' }}{{ $s->privacy_accettata_il ? ' · versione ' . $s->privacy_versione : '' }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </form>
            <x-slot:piede>
                <button type="button" class="btn btn-ghost" @click="aperta = false">Annulla</button>
                <button type="submit" form="form-link-privacy" class="btn btn-primary" @disabled($conRinvio === 0)>Invia le email</button>
            </x-slot:piede>
        </x-modale>
    @endif
@endpush
