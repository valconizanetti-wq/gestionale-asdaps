{{-- Tabella delle scadenze (tavola SC-1); $scadenze, $puo dalla pagina. --}}
@use('App\Support\Importo')
<div class="table-wrap">
    <table class="data-table responsive sc-tabella">
        <thead>
            <tr>
                <th scope="col">Scadenza</th>
                <th scope="col">Categoria</th>
                <th scope="col">Data</th>
                <th scope="col">Stato</th>
                <th scope="col">Ultimo pagamento</th>
                <th scope="col">Preavviso</th>
                <th scope="col"><span class="sr-only">Azioni</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($scadenze as $s)
                @php
                    $st = $s->stato();
                    $r = $s->ultimoRinnovo;
                @endphp
                <tr>
                    <td class="td-primario">
                        <a href="{{ route('scadenzario.edit', $s) }}" class="td-name">{{ $s->titolo }}</a>
                        @if ($s->descrizione)<span class="td-sub sc-descrizione">{{ $s->descrizione }}</span>@endif
                    </td>
                    <td data-label="Categoria">{{ $s->categoriaTesto() }}</td>
                    <td data-label="Data"><strong>{{ $s->data_scadenza->format('d/m/Y') }}</strong></td>
                    <td data-label="Stato"><x-badge :variante="$st['variante']">{{ $st['testo'] }}</x-badge></td>
                    <td data-label="Ultimo pagamento">
                        @if ($r)
                            <span class="td-pila">
                                <span>{{ $s->conPagamento() ? 'Pagata' : 'Fatta' }} il {{ $r->fatto_il->format('d/m/Y') }}{{ $r->importo !== null ? ' · ' . Importo::it($r->importo, true) : '' }}</span>
                                @if ($r->movimento && auth()->user()->puoAccedere('prima_nota'))
                                    <a href="{{ route('prima-nota.show', $r->movimento) }}" class="td-sub">uscita n. {{ $r->movimento->id }} in Prima Nota</a>
                                @elseif ($s->conPagamento() && ! $r->prima_nota_id)
                                    <span class="td-sub">senza uscita in Prima Nota</span>
                                @endif
                            </span>
                        @else
                            <span class="td-sub">—</span>
                        @endif
                    </td>
                    <td data-label="Preavviso">{{ $s->giorni_preavviso }} {{ $s->giorni_preavviso === 1 ? 'giorno' : 'giorni' }} prima</td>
                    <td class="td-azioni">
                        <div class="riga-azioni">
                            @if ($puo && ! $s->completato)
                                <button type="button" class="btn btn-secondary btn-sm" x-data x-on:click="$dispatch('apri-modale', 'rinnova-{{ $s->id }}')">{{ $s->conPagamento() ? 'Rinnovata' : 'Fatta' }}</button>
                            @endif
                            <a href="{{ route('scadenzario.edit', $s) }}" class="action-btn" aria-label="{{ $puo ? 'Modifica' : 'Apri' }} «{{ $s->titolo }}» e il suo storico"><x-icona :nome="$puo ? 'matita' : 'avanti'" /></a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
