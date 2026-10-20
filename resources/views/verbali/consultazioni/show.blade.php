{{--
    Scheda della consultazione telematica (v0.44.0, tavole CT-1 e CT-3).
    Bozza: proposta, modifica, «Avvia la consultazione» (solo il Presidente,
    o il Vicepresidente con la spunta «in sostituzione», V16). In corso:
    voti di ciascuno con data e ora, validità (votanti almeno la
    maggioranza dei componenti) ed esito calcolato fin qui, sollecito,
    ritiro. Chiusa: esito e verbale nel libro del CD.
    Variabili: $c, $documenti, $calcolo, $puoAvviare, $vice, $termineMinimo.
--}}
@extends('layouts.app')

@section('title', 'Consultazione · ' . $c->oggetto)

@section('content')
    @php
        $puoModificare = auth()->user()->puoModificare('verbali');
        $passo = match ($c->stato) { 'bozza' => 1, 'in_corso' => 2, default => 4 };
        $nomi = [
            'Bozza',
            'Voto' . ($c->avviata_il ? ' · dal ' . $c->avviata_il->format('d/m') : ''),
            'Chiusura' . ($c->chiusa_il ? ' · ' . $c->chiusa_il->format('d/m') : ''),
        ];
        $votati = $c->voti->whereNotNull('voto')->count();
        $termineCorto = $c->isBozza() && $c->termine->lt($termineMinimo);
    @endphp

    <x-page-header titolo="Consultazione" evidenza="telematica" :sottotitolo="$c->oggetto"
                   :indietro="route('verbali.index')" indietro-label="Verbali">
        <div class="badge-riga">
            <x-badge :variante="$c->statoVariante()">{{ $c->statoTesto() }}</x-badge>
            @if ($c->in_sostituzione)<x-badge variante="neutral">Avviata dal Vicepresidente</x-badge>@endif
        </div>
        <x-slot:azioni>
            @if ($puoModificare && $c->isBozza())
                <a href="{{ route('verbali.consultazioni.edit', $c) }}" class="btn btn-secondary"><x-icona nome="matita" width="14" height="14" /> Modifica</a>
                <form method="POST" action="{{ route('verbali.consultazioni.destroy', $c) }}" x-data x-on:submit="if (! confirm('Eliminare la bozza della consultazione?')) $event.preventDefault()">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger-outline">Elimina la bozza</button>
                </form>
            @endif
            @if ($c->verbale)
                <a href="{{ route('verbali.show', $c->verbale) }}" class="btn btn-primary">Verbale</a>
            @endif
        </x-slot:azioni>
    </x-page-header>

    <x-sola-lettura sezione="verbali" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Operazione non eseguita.">{{ $errors->first() }}</x-avviso>
    @endif

    @if ($c->stato === 'ritirata')
        <x-avviso tipo="info" titolo="Ritirata il {{ $c->chiusa_il?->format('d/m/Y \a\l\l\e H:i') }}.">Motivo: {{ $c->motivo_ritiro }}. I voti non contano; non c'è verbale.</x-avviso>
    @else
        <x-passi :corrente="$passo" :nomi="$nomi" etichetta="Passi della consultazione" class="an-passi" />
    @endif

    <div class="pn-colonne">
        <div class="pn-colonna">
            @if ($c->isBozza())
                <x-card titolo="Avvio">
                    @if ($termineCorto)
                        <x-avviso tipo="warn" titolo="Termine troppo vicino.">Dall'avvio servono almeno 3 giorni: il termine deve venire dopo il {{ $termineMinimo->format('d/m/Y H:i') }}. Modifica la consultazione.</x-avviso>
                    @endif
                    <p class="form-intro">
                        Con «Avvia la consultazione» ogni componente in carica riceve per email la proposta, i documenti e il suo link personale per votare,
                        fino al <strong>{{ $c->termine->locale('it')->isoFormat('D MMMM YYYY, [ore] HH:mm') }}</strong>. Da lì la proposta non si modifica più.
                    </p>
                    @if ($puoAvviare)
                        <form method="POST" action="{{ route('verbali.consultazioni.avvia', $c) }}" class="form-modale" x-data="{ inviato: false, sost: false }" x-on:submit="inviato = true">
                            @csrf
                            @if ($vice)
                                <label class="vb-spunta">
                                    <input type="checkbox" name="in_sostituzione" value="1" x-model="sost" required>
                                    <span>Avvio in sostituzione del Presidente, assente o impedito (art. 18.3)</span>
                                </label>
                            @endif
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" @disabled($termineCorto) x-bind:disabled="inviato{{ $vice ? ' || ! sost' : '' }}{{ $termineCorto ? ' || true' : '' }}">Avvia la consultazione</button>
                            </div>
                        </form>
                    @else
                        <p class="form-help">La avvia il Presidente (art. 16.4) o, in sua sostituzione, il Vicepresidente: la trova nell'elenco dei verbali, tra quelle in corso.</p>
                    @endif
                </x-card>
            @else
                <x-card titolo="Voti">
                    <div class="table-wrap">
                        <table class="data-table responsive">
                            <thead>
                                <tr><th scope="col">Componente</th><th scope="col">Voto</th><th scope="col">Data e ora</th><th scope="col">Collegamento</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($c->voti as $vt)
                                    <tr>
                                        <td class="td-primario" data-label="Componente">{{ $vt->nome }}<span class="td-sub">{{ $vt->carica }}</span>
                                            @if ($vt->nota)<span class="td-sub">Nota: {{ $vt->nota }}</span>@endif</td>
                                        <td data-label="Voto">
                                            @if ($vt->haVotato())
                                                <x-badge :variante="match ($vt->voto) { 'favorevole' => 'ok', 'contrario' => 'ko', 'riunione' => 'warn', default => 'neutral' }">{{ $vt->votoTesto() }}</x-badge>
                                            @else
                                                <span class="testo-secondario">non ha votato{{ $vt->sollecitato_il ? ' · sollecitato il ' . $vt->sollecitato_il->format('d/m') : '' }}</span>
                                            @endif
                                        </td>
                                        <td data-label="Data e ora">{{ $vt->votato_il?->format('d/m/Y H:i') ?? '—' }}</td>
                                        <td data-label="Collegamento">{{ $vt->haVotato() ? $vt->collegamento() : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($c->isInCorso() && $puoModificare && $votati < $c->voti->count())
                        <form method="POST" action="{{ route('verbali.consultazioni.sollecita', $c) }}" class="an-azioni" x-data="{ inviato: false }" x-on:submit="inviato = true">
                            @csrf
                            <button type="submit" class="btn btn-secondary" x-bind:disabled="inviato">Sollecita chi non ha votato</button>
                            <span class="an-scelte">Il sollecito riporta lo stesso link personale.</span>
                        </form>
                    @endif
                </x-card>

                <x-card :titolo="$c->isInCorso() ? 'Esito fin qui' : 'Esito'">
                    <div class="kpi-row">
                        <x-kpi label="Votanti" :valore="$calcolo['votanti'] . ' su ' . $c->componenti" :variante="$calcolo['votanti'] >= $calcolo['servono'] ? 'ok' : 'warn'" :sub="'Ne servono ' . $calcolo['servono'] . ' (art. 16.4 c)'" />
                        <x-kpi label="Chiedono la riunione" :valore="$c->voti->where('voto', 'riunione')->count()" :sub="'Con ' . \App\Services\Consultazioni::sogliaRiunione((int) $c->componenti) . ' si ferma'" />
                    </div>
                    <x-avviso :tipo="match ($calcolo['esito']) { 'approvata' => 'ok', 'respinta' => 'ko', default => 'warn' }"
                              :titolo="$c->isInCorso() ? 'Se si chiudesse adesso:' : null">{{ $c->isInCorso() ? $calcolo['testo'] : $c->esito_testo }}</x-avviso>
                    @if ($c->isInCorso())
                        <p class="form-help">Si chiude da sola al termine, {{ $c->termine->format('d/m/Y \a\l\l\e H:i') }}, o prima se votano tutti o se almeno un terzo chiede la riunione. Alla chiusura il verbale nasce in bozza nel libro del CD e l'esito parte per email.</p>
                    @endif
                </x-card>
            @endif

            <x-card titolo="Proposta">
                <h3 class="vb-oggetto">{{ $c->oggetto }}</h3>
                <div class="documento-testo vb-testo">{!! $c->testo_html !!}</div>
            </x-card>
        </div>

        <div class="pn-colonna">
            <x-card titolo="Dati">
                <dl class="dati-griglia dati-una-colonna">
                    <x-dato label="Termine" :valore="$c->termine->format('d/m/Y H:i')" />
                    <x-dato label="Preparata da" :valore="$c->preparataDa?->name" />
                    @if ($c->avviata_il)
                        <x-dato label="Avviata" :valore="$c->avviata_il->format('d/m/Y H:i') . ($c->avviataDa ? ' · ' . $c->avviataDa->name : '') . ($c->in_sostituzione ? ' (in sostituzione del Presidente)' : '')" />
                        <x-dato label="Componenti" :valore="$c->componenti" />
                    @endif
                    @if ($c->chiusa_il)<x-dato label="Chiusa" :valore="$c->chiusa_il->format('d/m/Y H:i')" />@endif
                </dl>
            </x-card>
            <x-card titolo="Documenti">
                @if ($documenti->isEmpty())
                    <p class="form-help">Nessun documento.</p>
                @else
                    <ul class="ct-documenti">
                        @foreach ($documenti as $d)
                            <li>
                                <a href="{{ route('verbali.consultazioni.documenti.show', [$c, $d]) }}" target="_blank" rel="noopener"><x-icona nome="documento" width="16" height="16" /> {{ $d->nome }}</a>
                                <span class="testo-secondario">{{ $d->pesoTesto() }}</span>
                                @if ($puoModificare && $c->isBozza())
                                    <form method="POST" action="{{ route('verbali.consultazioni.documenti.destroy', [$c, $d]) }}" class="vb-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="action-btn" aria-label="Togli {{ $d->nome }}"><x-icona nome="cestino" width="14" height="14" /></button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
            @if ($c->isInCorso() && $puoAvviare)
                <x-card titolo="Ritiro">
                    <p class="form-help">Se la proposta non serve più, la consultazione si ritira: i voti non contano e i componenti ricevono un'email.</p>
                    <button type="button" class="btn btn-danger-outline" x-data x-on:click="$dispatch('apri-modale', 'ritira')">Ritira la consultazione</button>
                </x-card>
            @endif
        </div>
    </div>

    @if ($c->isInCorso() && $puoAvviare)
        @push('modals')
            <x-modale nome="ritira" titolo="Ritirare la consultazione?" :aperta="$errors->ritiro->any()">
                <form method="POST" action="{{ route('verbali.consultazioni.ritira', $c) }}" class="form-modale" id="form-ritira">
                    @csrf
                    <div @class(['form-field', 'has-error' => $errors->ritiro->has('motivo_ritiro')])>
                        <label class="form-label" for="motivo_ritiro">Motivo<span class="obbligatorio" aria-hidden="true">*</span></label>
                        <textarea id="motivo_ritiro" name="motivo_ritiro" rows="3" required minlength="10" maxlength="500" class="form-textarea">{{ $errors->ritiro->any() ? old('motivo_ritiro') : '' }}</textarea>
                        @foreach ($errors->ritiro->get('motivo_ritiro') as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                    </div>
                </form>
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Indietro</button>
                    <button type="submit" form="form-ritira" class="btn btn-danger">Ritira</button>
                </x-slot:piede>
            </x-modale>
        @endpush
    @endif
@endsection
