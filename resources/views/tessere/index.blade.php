@extends('layouts.app')

@section('title', 'Tessere CSEN')

@use('App\Support\Importo')
@use('App\Models\Tesseramento')

@section('content')
    @php
        $nomiTipi = $tipi->pluck('nome', 'id');
        $breve = fn (string $nome) => trim(preg_replace('/\s+Base$/i', '', $nome)) ?: $nome;   // «Silver Base» → «Silver»
        $avvisi = $kpi['in_scadenza'] + ($kpi['senza']);
    @endphp

    <x-page-header titolo="Tessere" evidenza="CSEN"
                   sottotitolo="Tessere a pacchetti con numeri stampati · ogni tessera vale un anno dall'emissione · comprende l'assicurazione base del socio">
        @if ($puoModificare)
            <x-slot:azioni>
                <a href="{{ route('tesseramento.carica') }}" class="btn btn-secondary solo-desktop">Carica tessere già emesse</a>
                <button type="button" class="btn btn-secondary" x-data x-on:click="$dispatch('emetti-tessera', null)">Emetti tessera</button>
                <a href="{{ route('tesseramento.pacchetti.create') }}" class="btn btn-primary"><x-icona nome="piu" width="14" height="14" /> Nuovo pacchetto</a>
            </x-slot:azioni>
        @endif
    </x-page-header>

    <x-sola-lettura sezione="tesseramento" />

    @if ($avvisi > 0)
        <x-avviso tipo="warn" :titolo="collect([
                $kpi['in_scadenza'] ? $kpi['in_scadenza'] . ($kpi['in_scadenza'] === 1 ? ' tessera scade' : ' tessere scadono') . ' nei prossimi 30 giorni' : null,
                $kpi['senza'] ? $kpi['senza'] . ($kpi['senza'] === 1 ? ' socio senza tessera valida' : ' soci senza tessera valida') : null,
            ])->filter()->implode(' · ') . '.'">
            Senza tessera valida il socio non ha l'assicurazione base: non dovrebbe tirare al campo finché non è rinnovata.
            <a href="#da-rinnovare">Mostra</a>
        </x-avviso>
    @endif

    <div class="kpi-row kpi-row-5">
        <x-kpi label="Tessere valide" :valore="$kpi['valide']" variante="ok" :sub="'su ' . $kpi['soci'] . ' soci attivi'"
               :href="route('tesseramento.index', ['stato' => 'valida']) . '#elenco'" />
        <x-kpi label="In scadenza" :valore="$kpi['in_scadenza']" :variante="$kpi['in_scadenza'] ? 'warn' : null" sub="nei prossimi 30 giorni"
               :href="route('tesseramento.index', ['stato' => 'in_scadenza']) . '#elenco'" />
        <x-kpi label="Scadute o mancanti" :valore="$kpi['senza']" :variante="$kpi['senza'] ? 'ko' : null"
               :sub="$kpi['mancanti'] ? $kpi['mancanti'] . ($kpi['mancanti'] === 1 ? ' mai tesserato' : ' mai tesserati') . ' · senza assicurazione base' : 'senza assicurazione base'" />
        <x-kpi label="Disponibili" :valore="$kpi['disponibili']"
               :sub="$disponibiliPerTipo->isEmpty() ? 'nessun pacchetto con numeri liberi' : $disponibiliPerTipo->map(fn ($n, $id) => $breve($nomiTipi[$id]) . ' ' . $n)->implode(' · ')" />
        <x-kpi :label="'Costo tessere ' . $anno" :valore="Importo::it($kpi['costo'], true)"
               :sub="$kpi['emesse'] . ($kpi['emesse'] === 1 ? ' emessa' : ' emesse') . ' · quota netta ' . Importo::it($kpi['netta'], true)" />
    </div>

    {{-- Da rinnovare (TS-1): in scadenza, scadute, mai tesserati --}}
    <x-card titolo="Da rinnovare" senza-padding id="da-rinnovare">
        <x-slot:azioni><x-badge variante="neutral">30 giorni</x-badge></x-slot:azioni>
        @if ($daRinnovare->isEmpty())
            <x-vuoto>Tutti i soci attivi hanno una tessera valida per più di 30 giorni.</x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive ts-tabella">
                    <thead>
                        <tr>
                            <th scope="col">Socio</th>
                            <th scope="col">Tessera</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Scade il</th>
                            <th scope="col">Quota {{ $anno }}</th>
                            <th scope="col"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daRinnovare as $s)
                            @php
                                $sit = $situazioni[$s->id];
                                $t = $sit['tessera'];
                                [$qTesto, $qVar] = $quote[$s->id];
                                $scadenza = match ($sit['stato']) {
                                    'mancante' => ['Mai tesserato', 'ko'],
                                    'scaduta' => [$t->scadenza ? 'scaduta il ' . $t->scadenza->format('d/m/Y') : 'da controllare', 'ko'],
                                    'futura' => ['valida dal ' . $t->data_rilascio->format('d/m/Y'), 'warn'],
                                    default => [($sit['giorni'] === 0 ? 'oggi' : ($sit['giorni'] === 1 ? 'domani' : 'tra ' . $sit['giorni'] . ' giorni')) . ' · ' . $t->scadenza->format('d/m/Y'), 'warn'],
                                };
                            @endphp
                            <tr>
                                <td class="td-primario">
                                    @if ($vediSoci)
                                        <a href="{{ route('soci.show', $s) }}#tessera" class="pn-desc">{{ $s->cognome }} {{ $s->nome }}</a>
                                    @else
                                        <span class="pn-desc">{{ $s->cognome }} {{ $s->nome }}</span>
                                    @endif
                                </td>
                                <td data-label="Tessera" @class(['td-solo-desktop' => ! $t])><span class="ts-numero">{{ $t?->numero_tessera_csen ?? '—' }}</span></td>
                                <td data-label="Tipo" @class(['td-solo-desktop' => ! $t])>{{ $t?->tipoNome() ?? '—' }}</td>
                                <td data-label="Scade il"><x-badge :variante="$scadenza[1]">{{ $scadenza[0] }}</x-badge></td>
                                <td data-label="Quota"><x-badge :variante="$qVar">{{ $qTesto }}</x-badge></td>
                                <td class="td-azioni">
                                    @if ($puoModificare && $sit['stato'] !== 'futura')
                                        <button type="button" @class(['btn btn-sm', 'btn-primary' => $qVar === 'ok', 'btn-secondary' => $qVar !== 'ok'])
                                                x-data x-on:click="$dispatch('emetti-tessera', {{ $s->id }})">{{ $t ? 'Rinnova' : 'Emetti' }}</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    {{-- Disponibili per tipo (TS-5, anche da telefono) e tipi di tessera --}}
    <x-card titolo="Disponibili">
        @if ($puoModificare)
            <x-slot:azioni>
                <button type="button" class="btn btn-ghost btn-sm" x-data x-on:click="$dispatch('apri-modale', 'tipo-nuovo')">Nuovo tipo</button>
            </x-slot:azioni>
        @endif
        @if ($tipi->isEmpty())
            <p class="form-help">Nessun tipo di tessera: aggiungi i tipi del listino CSEN (Silver Base, Gold Base…).</p>
        @else
            <div class="ts-disponibili">
                @foreach ($tipi as $tipo)
                    @php $aperti = $pacchetti->where('tipo_id', $tipo->id)->filter(fn ($p) => $p->liberi > 0); @endphp
                    <div @class(['ts-tipo', 'ts-tipo-spento' => ! $tipo->attivo])>
                        <div class="ts-tipo-testa">
                            <span class="kpi-label">{{ $tipo->nome }}</span>
                            @if ($puoModificare)
                                <button type="button" class="action-btn" aria-label="Modifica il tipo {{ $tipo->nome }}" x-data
                                        x-on:click="$dispatch('apri-modale', 'tipo-{{ $tipo->id }}')"><x-icona nome="matita" /></button>
                            @endif
                        </div>
                        <strong>{{ $disponibiliPerTipo[$tipo->id] ?? 0 }}</strong>
                        <span class="kpi-sub">
                            {{ $aperti->map(fn ($p) => $p->intervallo())->implode(' · ') ?: 'nessun pacchetto aperto' }}
                        </span>
                        <span class="kpi-sub">
                            @if ($tipo->prezzo !== null){{ Importo::it($tipo->prezzo, true) }} a tessera @else <span class="ts-da-fare">prezzo da scrivere</span> @endif
                            @unless ($tipo->attivo) · non in uso @endunless
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    {{-- Pacchetti (TS-1) --}}
    <x-card titolo="Pacchetti" senza-padding>
        @if ($pacchetti->isEmpty())
            <x-vuoto>Nessun pacchetto registrato. @if ($puoModificare)<a href="{{ route('tesseramento.pacchetti.create') }}">Registra il primo</a>@endif</x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive ts-tabella">
                    <thead>
                        <tr>
                            <th scope="col">Acquistato</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Numeri</th>
                            <th scope="col">Emesse</th>
                            <th scope="col">Disponibili</th>
                            <th scope="col" class="td-num">Costo €</th>
                            <th scope="col">Movimento</th>
                            <th scope="col"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pacchetti as $p)
                            @php $restano = $p->liberi; $usate = $p->quantita() - $restano; @endphp
                            <tr>
                                <td class="td-primario">{{ $p->acquistato_il->format('d/m/Y') }}</td>
                                <td data-label="Tipo">{{ $p->tipo->nome }}</td>
                                <td data-label="Numeri"><span class="ts-numero">{{ $p->intervallo() }}</span></td>
                                <td data-label="Emesse">
                                    <span class="ts-emesse">{{ $usate }} di {{ $p->quantita() }}</span>
                                    <progress class="ts-barra" value="{{ $usate }}" max="{{ $p->quantita() }}" aria-hidden="true"></progress>
                                </td>
                                <td data-label="Disponibili">@if ($restano)<strong>{{ $restano }}</strong>@else <span class="td-sub">esaurito</span>@endif</td>
                                <td data-label="Costo €" class="td-num">{{ Importo::it($p->totale) }}</td>
                                <td data-label="Movimento">
                                    @if ($p->movimento)
                                        @if (auth()->user()->puoAccedere('prima_nota'))
                                            <a href="{{ route('prima-nota.show', $p->movimento) }}" class="rq-numero">n. {{ $p->movimento->id }}</a>
                                        @else
                                            <span class="rq-numero">n. {{ $p->movimento->id }}</span>
                                        @endif
                                    @else
                                        <span class="td-sub">{{ $p->acquistato_il->year < \App\Services\RinnoviQuota::annoAvvio() ? 'vecchio gestionale' : 'da collegare' }}</span>
                                    @endif
                                </td>
                                <td class="td-azioni">
                                    @if ($puoModificare)
                                        <a href="{{ route('tesseramento.pacchetti.edit', $p) }}" class="action-btn" aria-label="Correggi il pacchetto {{ $p->intervallo() }}"><x-icona nome="matita" /></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <p class="form-help ts-nota">Il numero proposto all'emissione è il primo libero del pacchetto di quel tipo; si può scegliere un altro numero del pacchetto (la tessera consegnata a mano), mai uno già usato. Il costo delle tessere entra in Prima Nota all'acquisto del pacchetto.</p>

    {{-- Tutte le tessere --}}
    <form method="GET" action="{{ route('tesseramento.index') }}#elenco" class="filter-bar" id="elenco">
        <div class="form-field filtro-cerca">
            <label class="form-label" for="f-q">Cerca</label>
            <input id="f-q" name="q" type="search" value="{{ $filtro['q'] }}" class="form-input" placeholder="Cognome, nome, numero" autocomplete="off">
        </div>
        <div class="form-field">
            <label class="form-label" for="f-stato">Tessere</label>
            <select id="f-stato" name="stato" class="form-select">
                <option value="">Tutte</option>
                @foreach (\App\Http\Controllers\TessereController::FILTRI as $v => $l)
                    <option value="{{ $v }}" @selected($filtro['stato'] === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filtra</button>
    </form>

    <x-card titolo="Tessere emesse" senza-padding>
        @if ($filtro['stato'] !== '' || $filtro['q'] !== '')
            <x-slot:azioni><a href="{{ route('tesseramento.index') }}#elenco" class="btn btn-ghost btn-sm">Togli i filtri</a></x-slot:azioni>
        @endif
        @if ($elenco->isEmpty())
            <x-vuoto>{{ $filtro['stato'] !== '' || $filtro['q'] !== '' ? 'Nessuna tessera corrisponde ai filtri.' : 'Nessuna tessera registrata.' }}</x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive ts-tabella">
                    <thead>
                        <tr>
                            <th scope="col">Socio</th>
                            <th scope="col">Numero</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Emessa</th>
                            <th scope="col">Scade</th>
                            <th scope="col">Stato</th>
                            <th scope="col">Portale CSEN</th>
                            <th scope="col"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($elenco as $t)
                            @php [$sTesto, $sVar] = Tesseramento::STATI[$t->statoOggi()]; @endphp
                            <tr>
                                <td class="td-primario">
                                    @if ($vediSoci && $t->socio)
                                        <a href="{{ route('soci.show', $t->socio) }}#tessera" class="pn-desc">{{ $t->socio->cognome }} {{ $t->socio->nome }}</a>
                                    @else
                                        <span class="pn-desc">{{ $t->socio?->cognome }} {{ $t->socio?->nome }}</span>
                                    @endif
                                    @if ($t->senza_quota)<span class="td-sub">rinnovata senza quota versata</span>@endif
                                </td>
                                <td data-label="Numero"><span class="ts-numero">{{ $t->numero_tessera_csen }}</span>
                                    @if ($t->origine === 'caricata')<span class="td-sub">dall'elenco CSEN</span>@endif</td>
                                <td data-label="Tipo">{{ $t->tipoNome() }}</td>
                                <td data-label="Emessa">{{ $t->data_rilascio->format('d/m/Y') }}</td>
                                <td data-label="Scade">{{ $t->scadenza?->format('d/m/Y') ?? '—' }}</td>
                                <td data-label="Stato"><x-badge :variante="$sVar">{{ $sTesto }}</x-badge></td>
                                <td data-label="Portale CSEN">
                                    @if ($puoModificare)
                                        <form method="POST" action="{{ route('tesseramento.portale', $t) }}">
                                            @csrf
                                            <button type="submit" @class(['btn btn-sm', 'btn-ghost' => $t->portale_csen_il, 'btn-secondary' => ! $t->portale_csen_il])
                                                    aria-label="{{ $t->portale_csen_il ? 'Segna la tessera ' . $t->numero_tessera_csen . ' come da inserire sul portale' : 'Segna la tessera ' . $t->numero_tessera_csen . ' come inserita sul portale' }}">
                                                {{ $t->portale_csen_il ? '✓ Inserita' : 'Da inserire' }}
                                            </button>
                                        </form>
                                    @else
                                        {{ $t->portale_csen_il ? 'Inserita' : 'Da inserire' }}
                                    @endif
                                </td>
                                <td class="td-azioni">
                                    @if ($puoModificare)
                                        <button type="button" class="action-btn" aria-label="Elimina la tessera {{ $t->numero_tessera_csen }}" x-data
                                                x-on:click="$dispatch('apri-modale', 'elimina-{{ $t->id }}')"><x-icona nome="cestino" /></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-paginazione :elementi="$elenco" nome="tessere" />
        @endif
    </x-card>

    @if ($puoModificare)
        @push('modals')
            @include('tessere._emissione')

            @foreach ($elenco as $t)
                @php $bag = $errors->getBag('elimina' . $t->id); @endphp
                <x-modale nome="elimina-{{ $t->id }}" :titolo="'Eliminare la tessera ' . $t->numero_tessera_csen . '?'" :aperta="$bag->any()">
                    <form method="POST" action="{{ route('tesseramento.destroy', $t) }}" class="form-modale" id="form-elimina-{{ $t->id }}">
                        @csrf
                        @method('DELETE')
                        <p class="form-help">Solo per una tessera registrata per errore: il numero torna libero nel pacchetto. La motivazione resta nell'Audit Log.
                            @if ($t->portale_csen_il) Se è già sul portale CSEN, va tolta anche lì. @endif</p>
                        <div @class(['form-field', 'has-error' => $bag->has('motivo')])>
                            <label class="form-label" for="motivo-t{{ $t->id }}">Motivazione<span class="obbligatorio" aria-hidden="true">*</span></label>
                            <textarea id="motivo-t{{ $t->id }}" name="motivo" rows="3" required minlength="15" maxlength="1000" class="form-textarea">{{ $bag->any() ? old('motivo') : '' }}</textarea>
                            @foreach ($bag->get('motivo') as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                        </div>
                    </form>
                    <x-slot:piede>
                        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Indietro</button>
                        <button type="submit" form="form-elimina-{{ $t->id }}" class="btn btn-danger">Elimina la tessera</button>
                    </x-slot:piede>
                </x-modale>
            @endforeach

            @foreach ($tipi->concat([null]) as $tipo)
                @php $chiave = $tipo?->id ?? 'nuovo'; $bag = $errors->getBag('tipo' . $chiave); @endphp
                <x-modale nome="tipo-{{ $chiave }}" :titolo="$tipo ? 'Tipo ' . $tipo->nome : 'Nuovo tipo di tessera'" :aperta="$bag->any()">
                    <form method="POST" action="{{ $tipo ? route('tesseramento.tipi.update', $tipo) : route('tesseramento.tipi.store') }}" class="form-modale" id="form-tipo-{{ $chiave }}">
                        @csrf
                        @if ($tipo) @method('PUT') @endif
                        <div class="form-grid">
                            <div @class(['form-field', 'has-error' => $bag->has('nome')])>
                                <label class="form-label" for="tipo-nome-{{ $chiave }}">Nome<span class="obbligatorio" aria-hidden="true">*</span></label>
                                <input id="tipo-nome-{{ $chiave }}" name="nome" class="form-input" maxlength="50" required
                                       value="{{ $bag->any() ? old('nome') : $tipo?->nome }}" placeholder="Gold Base">
                                @foreach ($bag->get('nome') as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                            </div>
                            <div @class(['form-field', 'has-error' => $bag->has('prezzo')])>
                                <label class="form-label" for="tipo-prezzo-{{ $chiave }}">Prezzo per tessera €<span class="obbligatorio" aria-hidden="true">*</span></label>
                                <input id="tipo-prezzo-{{ $chiave }}" name="prezzo" class="form-input" inputmode="decimal" required
                                       value="{{ $bag->any() ? old('prezzo') : ($tipo?->prezzo !== null ? Importo::it($tipo->prezzo) : '') }}">
                                @foreach ($bag->get('prezzo') as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                            </div>
                        </div>
                        <p class="form-help">Dal listino CSEN. È il prezzo proposto nei pacchetti nuovi; quelli già registrati non cambiano.</p>
                        @if ($tipo)
                            <label class="spunta">
                                <input type="checkbox" name="attivo" value="1" @checked($bag->any() ? old('attivo') : $tipo->attivo)>
                                <span><strong>In uso</strong><span class="scelta-sub">Tolta la spunta, il tipo non si propone nei pacchetti nuovi; le tessere già emesse restano.</span></span>
                            </label>
                        @endif
                    </form>
                    <x-slot:piede>
                        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                        <button type="submit" form="form-tipo-{{ $chiave }}" class="btn btn-primary">Salva</button>
                    </x-slot:piede>
                </x-modale>
            @endforeach
        @endpush
    @endif
@endsection
