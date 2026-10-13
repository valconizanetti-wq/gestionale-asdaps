@extends('layouts.app')

@section('title', 'Utenti')

@use('App\Services\Utenti')

@section('content')
    <x-page-header titolo="Utenti" evidenza="del gestionale"
                   sottotitolo="Chi entra nel gestionale e con quali ruoli · modificano Presidente e Segretario, il CD consulta">
        @if ($puoModificare)
            <x-slot:azioni>
                <a href="{{ route('utenti.create') }}" class="btn btn-primary"><x-icona nome="piu" width="14" height="14" /> Nuovo utente</a>
            </x-slot:azioni>
        @endif
    </x-page-header>

    <x-sola-lettura sezione="utenti" />

    <x-card senza-padding>
        <div class="table-wrap">
            <table class="data-table responsive">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Ruoli</th>
                        <th scope="col">Stato</th>
                        <th scope="col">Ultimo accesso</th>
                        @if ($puoModificare)<th scope="col"><span class="sr-only">Azioni</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($utenti as $u)
                        @php $bloccato = $puoModificare ? Utenti::motivoBlocco(auth()->user(), $u) : null; @endphp
                        <tr>
                            <td class="td-primario">{{ $u->name }}@if ($u->is(auth()->user())) <span class="td-sub">(tu)</span>@endif<span class="td-sub">{{ $u->email }}</span></td>
                            <td data-label="Ruoli">
                                <span class="ut-ruoli">
                                    @forelse ($u->ruoli as $r)
                                        <x-badge :variante="in_array($r->codice, Utenti::CARICHE, true) ? 'info' : 'neutral'">{{ $r->label }}</x-badge>
                                    @empty
                                        <x-badge variante="ko">Nessun ruolo</x-badge>
                                    @endforelse
                                </span>
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
    @endif

    <x-card titolo="Come funzionano i ruoli" class="pn-aiuto">
        <p>Un utente può avere più ruoli: vede l'unione delle sezioni, e dove un ruolo modifica e l'altro legge soltanto, <strong>modifica</strong>. Riceve una sola email anche se due suoi ruoli ne sono destinatari.</p>
        <p>Il <strong>Revisore</strong> non può avere altri ruoli <span class="norma">Statuto, art. 20.1</span>. Segretario e Tesoriere possono essere la stessa persona <span class="norma">Statuto, art. 15.2</span>.</p>
        <p>Il Segretario gestisce gli utenti, ma il ruolo di Presidente, l'utente del Presidente e i propri ruoli li cambia solo il Presidente. Un utente non si elimina: si disattiva, e tutto quello che ha registrato resta collegato a lui.</p>
    </x-card>
@endsection
