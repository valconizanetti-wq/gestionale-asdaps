@extends('layouts.app')

@section('title', 'Utenti')

@use('App\Services\Utenti')

@section('content')
    <x-page-header titolo="Utenti" evidenza="del gestionale"
                   sottotitolo="Chi entra nel gestionale · gli utenti del CD nascono dal Consiglio Direttivo">
        <x-slot:azioni>
            @if (auth()->user()->puoAccedere('governance_cd'))<a href="{{ route('governance.index') }}" class="btn btn-ghost">Consiglio Direttivo</a>@endif
            @if ($puoModificare)<a href="{{ route('utenti.create') }}" class="btn btn-primary"><x-icona nome="piu" width="14" height="14" /> Nuovo utente esterno</a>@endif
        </x-slot:azioni>
    </x-page-header>

    <x-sola-lettura sezione="utenti" />

    <x-card senza-padding>
        <div class="table-wrap">
            <table class="data-table responsive">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Ruoli</th>
                        <th scope="col">Da</th>
                        <th scope="col">Stato</th>
                        <th scope="col">Ultimo accesso</th>
                        @if ($puoModificare)<th scope="col"><span class="sr-only">Azioni</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($utenti as $u)
                        @php $bloccato = $puoModificare ? Utenti::motivoBlocco(auth()->user(), $u) : null; @endphp
                        <tr>
                            <td class="td-primario">{{ $u->cognomeNome() }}@if ($u->is(auth()->user())) <span class="td-sub">(tu)</span>@endif<span class="td-sub">{{ $u->email }}</span></td>
                            <td data-label="Ruoli">
                                <span class="ut-ruoli">
                                    @forelse ($u->ruoli as $r)
                                        <x-badge :variante="in_array($r->codice, ['presidente', 'vicepresidente', 'segretario', 'tesoriere'], true) ? 'info' : 'neutral'">{{ $r->label }}</x-badge>
                                    @empty
                                        <span class="td-sub">nessuno</span>
                                    @endforelse
                                </span>
                            </td>
                            <td data-label="Da">
                                @if (in_array($u->id, $nelCd, true))
                                    @if (auth()->user()->puoAccedere('governance_cd'))<a href="{{ route('governance.index') }}">Consiglio Direttivo</a>@else Consiglio Direttivo @endif
                                @elseif (Utenti::delCd($u) && $u->ruoli->isNotEmpty())
                                    <x-badge variante="warn">Non collegato al CD</x-badge>
                                @elseif ($u->socio_id)
                                    <span class="td-sub">uscito dal CD</span>
                                @else
                                    <span class="td-sub">esterno</span>
                                @endif
                            </td>
                            <td data-label="Stato">@if ($u->attivo)<x-badge variante="ok">Attivo</x-badge>@else<x-badge variante="neutral">Disattivato</x-badge>@endif</td>
                            <td data-label="Ultimo accesso">{{ $u->ultimo_accesso?->format('d/m/Y H:i') ?? 'mai' }}</td>
                            @if ($puoModificare)
                                <td class="td-azioni">
                                    @unless ($bloccato)
                                        @if ($u->attivo)
                                            <button type="button" class="action-btn" aria-label="Invia a {{ $u->name }} il link per la password"
                                                    x-data x-on:click="$dispatch('apri-modale', 'link-{{ $u->id }}')"><x-icona nome="lucchetto" /></button>
                                        @endif
                                        <a href="{{ route('utenti.edit', $u) }}" class="action-btn" aria-label="Modifica {{ $u->name }}"><x-icona nome="matita" /></a>
                                    @endunless
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    @if ($puoModificare)
        @push('modals')
        @foreach ($utenti as $u)
            @continue(! $u->attivo || Utenti::motivoBlocco(auth()->user(), $u))
            <x-modale :nome="'link-' . $u->id" titolo="Link per la password">
                <p>{{ $u->name }} riceve a <strong>{{ $u->email }}</strong> un link per scegliersi la password. Il link vale <strong>24 ore</strong> e si usa una volta sola.</p>
                <p>La password attuale resta valida finché non ne sceglie una nuova. Nessuno, nemmeno tu, conosce la password scelta.</p>
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                    <form method="POST" action="{{ route('utenti.link-password', $u) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Invia il link</button>
                    </form>
                </x-slot:piede>
            </x-modale>
        @endforeach
        @endpush
    @endif

    <x-card titolo="Come funzionano i ruoli" class="pn-aiuto">
        <p>I ruoli del CD (Presidente, Vicepresidente, Segretario, Tesoriere, Consigliere) vengono dalla carica nella pagina <strong>Consiglio Direttivo</strong>: chi entra nel CD diventa utente, chi esce perde i ruoli del CD. Segretario e Tesoriere possono essere la stessa persona <span class="norma">Statuto, art. 15.2</span>.</p>
        <p>Da qui si danno solo il <strong>Revisore</strong> (esterno alla Compagnia, senza altri ruoli <span class="norma">Statuto, art. 20.1</span>) e l'<strong>Amministratore di sistema</strong>, che si aggiunge ai ruoli tranne Presidente e Revisore. Uno solo ciascuno.</p>
        <p>Con più ruoli si vede l'unione delle sezioni, e dove un ruolo modifica e l'altro legge soltanto, <strong>modifica</strong>. Un utente non si elimina: si disattiva, e tutto quello che ha registrato resta collegato a lui.</p>
    </x-card>
@endsection
