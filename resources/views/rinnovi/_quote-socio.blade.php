{{--
    Quote associative nella scheda del socio (tavola RQ-5; rinnovi-quota.md).
    Visibile a chi accede a Rinnovi quota; registra e annulla chi la modifica.
    Variabile: $socio (con rinnovi caricati, annullati compresi).
--}}
@use('App\Support\Importo')
@use('App\Services\RinnoviQuota')
@php
    $puoQuote = auth()->user()->puoModificare('rinnovi_quota');
    $rinnovi = $socio->rinnovi->sortByDesc(fn ($r) => sprintf('%04d-%d-%010d', $r->anno, $r->isAnnullato() ? 0 : 1, $r->id));
    $annoCorrente = now()->year;
    $versataOra = $rinnovi->first(fn ($r) => ! $r->isAnnullato() && $r->anno === $annoCorrente);
    $attivo = $socio->stato === 'attivo' && ! $socio->pseudonimizzato;
    $situazione = RinnoviQuota::situazione((bool) $versataOra, $annoCorrente);
@endphp
<x-card titolo="Quote associative" senza-padding id="quote">
    <x-slot:azioni>
        @if ($attivo)
            @php [$t, $v] = RinnoviQuota::SITUAZIONI[$situazione]; @endphp
            <x-badge :variante="$v">{{ $annoCorrente }}: {{ $t }}</x-badge>
        @endif
        @if ($puoQuote && $attivo)
            <a href="{{ route('rinnovi.create', ['socio' => $socio->id, 'da' => 'socio']) }}" class="btn btn-primary btn-sm">
                <x-icona nome="piu" width="14" height="14" /> Registra quota
            </a>
        @endif
    </x-slot:azioni>

    @if ($rinnovi->isEmpty())
        <x-vuoto>Nessuna quota registrata nel gestionale.</x-vuoto>
    @else
        <div class="table-wrap">
            <table class="data-table responsive rq-tabella">
                <thead>
                    <tr>
                        <th scope="col">Anno</th>
                        <th scope="col" class="td-num">Quota €</th>
                        <th scope="col">Versata</th>
                        <th scope="col">Situazione</th>
                        <th scope="col">Ricevuta</th>
                        <th scope="col">Note</th>
                        <th scope="col"><span class="sr-only">Azioni</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rinnovi as $r)
                        <tr @class(['rq-annullata' => $r->isAnnullato()])>
                            <td class="td-primario"><strong>{{ $r->anno }}</strong></td>
                            <td data-label="Quota €" class="td-num">{{ Importo::it($r->importo) }}</td>
                            <td data-label="Versata">{{ $r->data_pagamento->format('d/m/Y') }} <span class="td-sub">{{ $r->metodoLabel() }}{{ $r->versato_da_nome ? ' · da ' . $r->versato_da_nome : '' }}</span></td>
                            <td data-label="Situazione">
                                @if ($r->isAnnullato())
                                    <x-badge variante="neutral">Annullata</x-badge>
                                @else
                                    <x-badge variante="ok">Versata</x-badge>
                                @endif
                            </td>
                            <td data-label="Ricevuta">
                                @if ($r->numero_ricevuta)
                                    <a href="{{ route('rinnovi.ricevuta', $r) }}" target="_blank" rel="noopener" class="rq-numero">{{ $r->numero_ricevuta }}</a>
                                    @if (! $r->isAnnullato())
                                        <span class="td-sub">{{ $r->ricevuta_stato === 'inviata' ? 'inviata il ' . $r->ricevuta_inviata_il?->format('d/m/Y') : ($r->ricevuta_stato === 'errore' ? 'invio non riuscito' : 'da inviare') }}</span>
                                    @endif
                                @else — @endif
                            </td>
                            <td data-label="Note" @class(['td-solo-desktop' => ! $r->note && ! $r->isAnnullato()])>
                                <span class="td-sub">
                                    @if ($r->isAnnullato())
                                        Annullata il {{ $r->annullata_il->format('d/m/Y') }}{{ $r->annullataDa ? ' da ' . $r->annullataDa->name : '' }}: «{{ $r->annullamento_motivo }}». Movimento tolto dalla Prima Nota.
                                    @else
                                        {{ $r->note }}{{ $r->assenso_minore_il ? ($r->note ? ' · ' : '') . 'Assenso del minore il ' . $r->assenso_minore_il->format('d/m/Y') : '' }}
                                    @endif
                                </span>
                            </td>
                            <td class="td-azioni">
                                @if ($r->numero_ricevuta)
                                    <a href="{{ route('rinnovi.ricevuta', [$r, 'scarica' => 1]) }}" class="action-btn" aria-label="Scarica la ricevuta {{ $r->numero_ricevuta }}"><x-icona nome="download" /></a>
                                @endif
                                @if ($puoQuote && ! $r->isAnnullato())
                                    <form method="POST" action="{{ route('rinnovi.invia', $r) }}">
                                        @csrf
                                        <button type="submit" class="action-btn" aria-label="Invia la ricevuta {{ $r->numero_ricevuta }}"><x-icona nome="mail" /></button>
                                    </form>
                                    <button type="button" class="action-btn" aria-label="Annulla la ricevuta {{ $r->numero_ricevuta }}"
                                            @click="$dispatch('apri-modale', 'annulla-{{ $r->id }}')"><x-icona nome="cestino" /></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>

@if ($puoQuote)
    @push('modals')
        @foreach ($rinnovi->reject->isAnnullato() as $r)
            @include('rinnovi._annulla-modale', ['r' => $r])
        @endforeach
    @endpush
@endif
