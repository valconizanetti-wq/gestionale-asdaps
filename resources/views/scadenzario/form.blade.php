{{--
    Nuova scadenza o scheda di una scadenza (scadenzario.md §3): dati, con
    la categoria di Prima Nota per i pagamenti, e storico dei rinnovi con
    «Annulla l'ultimo». Revisore: tutto in sola lettura.
--}}
@extends('layouts.app')

@section('title', $s->exists ? $s->titolo : 'Nuova scadenza')

@use('App\Models\Scadenza')
@use('App\Support\Importo')

@section('content')
    @php
        $nuova = ! $s->exists;
        $st = $nuova ? null : $s->stato();
        $categorie = collect(Scadenza::CATEGORIE)->reject(fn ($l, $k) => $k === 'runts' && $s->categoria !== 'runts')->all();
        $ultimo = $nuova ? null : $s->rinnovi->sortByDesc('id')->first();   // l'ultimo registrato: quello che si annulla
    @endphp

    <x-page-header :titolo="$nuova ? 'Nuova' : $s->titolo" :evidenza="$nuova ? 'scadenza' : null"
                   :sottotitolo="$nuova ? 'Ricorrente ogni anno o una tantum, con preavviso; con una categoria di Prima Nota diventa un pagamento' : $s->categoriaTesto() . ' · ' . ($s->ricorrente() ? 'ogni anno' : 'una tantum') . ' · scade il ' . $s->data_scadenza->format('d/m/Y')"
                   :indietro="route('scadenzario.index')" indietro-label="Scadenze dell'associazione">
        @if (! $nuova && $st)
            <x-badge :variante="$st['variante']" class="sc-stato-testa">{{ $st['testo'] }}</x-badge>
        @endif
    </x-page-header>

    @if (! $puo)
        <x-avviso tipo="info" titolo="Sola lettura.">Il tuo ruolo consulta lo Scadenzario ma non lo modifica.</x-avviso>
    @endif
    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Controlla i dati.">Alcuni campi non sono validi: li trovi evidenziati qui sotto.</x-avviso>
    @endif

    <div class="pn-colonne">
        <form method="POST" action="{{ $nuova ? route('scadenzario.store') : route('scadenzario.update', $s) }}" class="pn-colonna" id="form-scadenza">
            @csrf
            @unless ($nuova) @method('PUT') @endunless
            <fieldset class="pr-fieldset" @disabled(! $puo)>
                <x-card titolo="Scadenza">
                    <x-campo name="titolo" label="Titolo" required :value="$s->titolo" maxlength="255" />
                    <div class="form-riga">
                        <x-campo name="descrizione" label="Descrizione" tipo="textarea" :value="$s->descrizione" rows="2" maxlength="1000" />
                    </div>
                    <div class="form-grid form-grid-3 form-riga">
                        <x-campo name="tipo" label="Ripetizione" tipo="select" required :value="$s->tipo"
                                 :opzioni="['ricorrente' => 'Ogni anno', 'una_tantum' => 'Una tantum']" />
                        <x-campo name="data_scadenza" label="Data" tipo="date" required :value="$s->data_scadenza"
                                 :aiuto="$nuova ? 'Ogni anno: il giorno e il mese si ripetono.' : 'Per spostarla dopo un pagamento usa «Rinnovata».'" />
                        <x-campo name="giorni_preavviso" label="Preavviso (giorni)" tipo="number" required :value="$s->giorni_preavviso" min="0" max="365"
                                 aiuto="Da quanti giorni prima è «in scadenza»." />
                    </div>
                    <div class="form-grid form-riga">
                        <x-campo name="categoria" label="Categoria" tipo="select" required :value="$s->categoria" :opzioni="$categorie" />
                        <x-campo name="prima_nota_categoria_id" label="Pagamento: categoria dell'uscita" tipo="select" :value="$s->prima_nota_categoria_id"
                                 :opzioni="$categoriePn" vuota="Nessuna: è un adempimento («Fatta»)"
                                 aiuto="Con una categoria, «Rinnovata» registra l'uscita in Prima Nota." />
                    </div>
                    @if ($s->chiave)
                        <p class="form-help form-riga">Scadenza usata dal gestionale ({{ $s->chiave === Scadenza::AFFILIAZIONE ? 'riquadro «Affiliazione all\'EPS» in Impostazioni › Ente e avvisi' : ($s->chiave === Scadenza::POLIZZA ? 'riquadro «Polizza RC» in Impostazioni › Ente' : 'avvisi') }}): si modifica, non si elimina.</p>
                    @endif
                </x-card>
            </fieldset>

            @if ($puo)
                <div class="form-actions">
                    <a href="{{ route('scadenzario.index') }}" class="btn btn-ghost">Annulla</a>
                    <button type="submit" class="btn btn-primary">{{ $nuova ? 'Aggiungi la scadenza' : 'Salva' }}</button>
                </div>
            @endif
        </form>

        @unless ($nuova)
            <aside class="pn-colonna">
                <x-card titolo="Storico" senza-padding>
                    @if ($s->rinnovi->isEmpty())
                        <x-vuoto>Nessun rinnovo registrato.</x-vuoto>
                    @else
                        <ul class="pr-storico">
                            @foreach ($s->rinnovi as $r)
                                <li>
                                    <span>{{ $s->conPagamento() ? 'Pagata' : 'Fatta' }} il {{ $r->fatto_il->format('d/m/Y') }}{{ $r->importo !== null ? ' · ' . Importo::it($r->importo, true) : '' }}
                                        <span class="td-sub">scadenza del {{ $r->data_scadenza->format('d/m/Y') }}{{ $r->prossima_scadenza ? ' → ' . $r->prossima_scadenza->format('d/m/Y') : ', chiusa' }}{{ $r->registratoDa ? ' · ' . $r->registratoDa->name : '' }}</span>
                                        @if ($r->note)<span class="td-sub">{{ $r->note }}</span>@endif
                                    </span>
                                    <span class="td-sub">
                                        @if ($r->movimento && auth()->user()->puoAccedere('prima_nota'))
                                            <a href="{{ route('prima-nota.show', $r->movimento) }}">uscita n. {{ $r->movimento->id }}</a>
                                        @elseif ($s->conPagamento())
                                            senza uscita
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($puo)
                            <div class="card-body">
                                <button type="button" class="btn btn-danger-outline btn-sm" x-data x-on:click="$dispatch('apri-modale', 'annulla-rinnovo')">Annulla l'ultimo rinnovo</button>
                                <p class="form-help">Per un errore di registrazione: la scadenza torna al {{ $ultimo->data_scadenza->format('d/m/Y') }}{{ $ultimo->prima_nota_id ? ' e l\'uscita in Prima Nota si elimina' : '' }}.</p>
                            </div>
                        @endif
                    @endif
                </x-card>

                @if ($puo && ! $s->chiave && $s->rinnovi->isEmpty())
                    <x-card titolo="Eliminare la scadenza?" class="pn-aiuto">
                        <p>Nessun rinnovo registrato: si può eliminare.</p>
                        <form method="POST" action="{{ route('scadenzario.destroy', $s) }}" x-data x-on:submit="if (! confirm('Eliminare la scadenza?')) $event.preventDefault()">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Elimina la scadenza</button>
                        </form>
                    </x-card>
                @endif
            </aside>
        @endunless
    </div>

    @if (! $nuova && $puo && $ultimo)
        @push('modals')
            <x-modale nome="annulla-rinnovo" titolo="Annullare l'ultimo rinnovo?" :aperta="$errors->has('motivo')">
                <form method="POST" action="{{ route('scadenzario.annulla', $s) }}" class="form-modale" id="form-annulla-rinnovo">
                    @csrf
                    <p>{{ $s->conPagamento() ? 'Pagamento' : 'Adempimento' }} del {{ $ultimo->fatto_il->format('d/m/Y') }}: la scadenza torna al <strong>{{ $ultimo->data_scadenza->format('d/m/Y') }}</strong>{{ $ultimo->prima_nota_id ? ' e l\'uscita n. ' . $ultimo->prima_nota_id . ' si elimina dalla Prima Nota, con i giustificativi' : '' }}.</p>
                    <x-campo name="motivo" label="Motivazione" tipo="textarea" required rows="3" minlength="15" maxlength="1000"
                             aiuto="Almeno 15 caratteri: resta nell'Audit Log." />
                </form>
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Indietro</button>
                    <button type="submit" form="form-annulla-rinnovo" class="btn btn-danger">Annulla il rinnovo</button>
                </x-slot:piede>
            </x-modale>
        @endpush
    @endif
@endsection
