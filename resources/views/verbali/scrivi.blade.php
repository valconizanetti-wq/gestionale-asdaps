{{--
    Verbale della riunione del CD per punti (v0.44.0, tavola VB-2). Per ogni
    punto dell'ordine del giorno la discussione e, se c'è, la delibera: tipo,
    testo e il voto di ciascun presente (art. 16.6). L'esito lo calcola il
    gestionale al salvataggio (Votazioni::esito: favorevoli contro
    contrari, astenuti fuori, in parità il Presidente; l'esclusione con la
    maggioranza dei componenti, art. 9.1 c). Mentre si vota, il conto dei
    voti si aggiorna sulla pagina.
    Variabili: $v, $s, $presenti, $costituita, $assenze, $documenti.
--}}
@extends('layouts.app')

@section('title', 'Verbale della riunione del ' . $s->data->format('d/m/Y'))

@use('App\Models\VerbalePunto')
@use('App\Models\VerbaleVoto')

@push('head')
    @vite('resources/js/editor.js')
@endpush

@section('content')
    <x-page-header titolo="Verbale della" evidenza="riunione"
                   :sottotitolo="'Consiglio Direttivo del ' . $s->data->format('d/m/Y') . ' · presiede ' . $v->presidente_assemblea . ' · verbalizza ' . $v->segretario_verbalizzante"
                   :indietro="route('verbali.show', $v)" indietro-label="Scheda del verbale">
        <div class="badge-riga">
            <x-badge variante="warn">Bozza</x-badge>
            <x-badge :variante="$costituita ? 'ok' : 'ko'">{{ $presenti->count() }} presenti su {{ $s->presenze->count() }}{{ $costituita ? ' · validamente costituito' : ' · non costituito' }}</x-badge>
        </div>
        <x-slot:azioni>
            <a href="{{ route('verbali.pdf', $v) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icona nome="download" width="14" height="14" /> Anteprima PDF</a>
            <a href="{{ route('verbali.riunioni.show', $s) }}" class="btn btn-secondary">Presenze</a>
        </x-slot:azioni>
    </x-page-header>

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Verbale non salvato.">{{ $errors->first() }}</x-avviso>
    @endif
    @if (! $costituita)
        <x-avviso tipo="warn" titolo="Il Consiglio non è validamente costituito.">Servono almeno {{ \App\Services\Votazioni::quorumCd($s->presenze->count()) }} presenti su {{ $s->presenze->count() }} (art. 16.3): si verbalizza la discussione, ma non si delibera.</x-avviso>
    @endif
    @foreach ($assenze as $nome => $date)
        <x-avviso tipo="info" titolo="{{ $nome }}: terza assenza di fila senza giustificazione.">Era assente anche il {{ implode(' e il ', $date) }}. Lo statuto prevede la decadenza dopo tre assenze consecutive senza giustificato motivo (art. 15.5): lo valuta il Consiglio.</x-avviso>
    @endforeach

    <form method="POST" action="{{ route('verbali.salva', $v) }}" enctype="multipart/form-data" class="vb-scrivi" x-data="{ inviato: false }" x-on:submit="inviato = true">
        @csrf
        @method('PUT')

        @foreach ($v->punti as $p)
            @php
                $voti = $presenti->mapWithKeys(fn ($pr) => [(string) $pr->socio_id => old("punti.{$p->id}.voti.{$pr->socio_id}", $p->voti->firstWhere('socio_id', $pr->socio_id)?->voto ?? '')]);
                $ha = (bool) old("punti.{$p->id}.ha_delibera", $p->ha_delibera);
            @endphp
            <x-card :titolo="$p->ordine . '. ' . $p->titolo">
                <div class="vb-punto" x-data="{ ha: @js($ha), voti: @js($voti),
                        conta(x) { return Object.values(this.voti).filter(v => v === x).length },
                        n(x, uno, tanti) { const c = this.conta(x); return c + ' ' + (c === 1 ? uno : tanti) },
                        get riepilogo() { return [this.n('favorevole', 'favorevole', 'favorevoli'), this.n('contrario', 'contrario', 'contrari'), this.n('astenuto', 'astenuto', 'astenuti')]
                            .concat(this.conta('non_partecipa') ? [this.n('non_partecipa', 'non partecipa', 'non partecipano')] : [])
                            .concat(this.conta('') ? ['mancano ' + this.conta('')] : []).join(' · ') },
                        tutti(x) { Object.keys(this.voti).forEach(k => this.voti[k] = x) } }">
                    <x-editor :name="'punti[' . $p->id . '][discussione_html]'" label="Discussione" :value="$p->discussione_html" :altezza="120"
                              aiuto="Chi interviene e che cosa dice, in breve." />

                    <input type="hidden" name="punti[{{ $p->id }}][ha_delibera]" value="0">
                    <label class="vb-spunta">
                        <input type="checkbox" name="punti[{{ $p->id }}][ha_delibera]" value="1" x-model="ha" @disabled(! $costituita)>
                        <span>Su questo punto il Consiglio delibera</span>
                    </label>

                    <div class="vb-delibera-campi" x-show="ha" @if (! $ha) x-cloak @endif>
                        <div class="form-field">
                            <label class="form-label" for="tipo-{{ $p->id }}">Tipo di delibera</label>
                            <select id="tipo-{{ $p->id }}" name="punti[{{ $p->id }}][tipo_delibera]" class="form-select">
                                @foreach (VerbalePunto::TIPI_CD as $codice => $nome)
                                    <option value="{{ $codice }}" @selected(old("punti.{$p->id}.tipo_delibera", $p->tipo_delibera ?? 'ordinaria') === $codice)>{{ $nome }}</option>
                                @endforeach
                            </select>
                            <div class="form-help">L'esclusione di un socio passa con i favorevoli pari almeno alla maggioranza dei componenti (art. 9.1 c); le altre con più favorevoli che contrari.</div>
                        </div>
                        <x-editor :name="'punti[' . $p->id . '][delibera_html]'" label="Testo della delibera" :value="$p->delibera_html" :altezza="110"
                                  aiuto="«Il Consiglio Direttivo delibera di …»" />

                        <fieldset class="vb-voti-campo">
                            <legend>Voto di ciascun presente</legend>
                            <div class="vb-voti-azioni">
                                <button type="button" class="btn btn-secondary btn-sm" x-on:click="tutti('favorevole')">Tutti favorevoli</button>
                                <span class="vb-conteggio" aria-live="polite"
                                      x-text="riepilogo"></span>
                            </div>
                            <div class="table-wrap">
                                <table class="data-table responsive vb-tabella-voti">
                                    <thead>
                                        <tr>
                                            <th scope="col">Componente</th>
                                            @foreach (['favorevole', 'contrario', 'astenuto', 'non_partecipa'] as $voto)
                                                <th scope="col" class="td-centro">{{ $voto === 'non_partecipa' ? 'Non partecipa' : VerbaleVoto::VOTI[$voto] }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($presenti as $pr)
                                            <tr>
                                                <td class="td-primario" data-label="Componente">{{ $pr->nome }}<span class="td-sub">{{ $pr->carica }}{{ $pr->presenza === 'video' ? ' · in videoconferenza' : '' }}</span></td>
                                                @foreach (['favorevole', 'contrario', 'astenuto', 'non_partecipa'] as $voto)
                                                    <td data-label="{{ $voto === 'non_partecipa' ? 'Non partecipa' : VerbaleVoto::VOTI[$voto] }}" class="td-centro">
                                                        <input type="radio" name="punti[{{ $p->id }}][voti][{{ $pr->socio_id }}]" value="{{ $voto }}"
                                                               x-model="voti['{{ $pr->socio_id }}']" aria-label="{{ $pr->nome }}: {{ VerbaleVoto::VOTI[$voto] }}">
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="form-help">«Non partecipa»: chi ha un interesse proprio nella delibera si astiene dal voto e non conta tra i votanti (art. 16.7).</p>
                        </fieldset>

                        @if ($p->esito_testo)
                            <x-avviso :tipo="$p->esito === 'approvata' ? 'ok' : 'ko'" titolo="Esito salvato.">{{ $p->esito_testo }}</x-avviso>
                        @else
                            <p class="form-help">L'esito si calcola al salvataggio, con tutti i voti.</p>
                        @endif
                    </div>
                </div>
            </x-card>
        @endforeach

        <x-card titolo="Chiusura e documenti">
            <div class="form-grid">
                <x-campo name="ora_chiusura" label="Ora di chiusura" tipo="time" :value="$v->ora_chiusura ? substr((string) $v->ora_chiusura, 0, 5) : null" />
            </div>
            @if ($documenti->isNotEmpty())
                <p class="form-help">Già allegati: {{ $documenti->pluck('nome')->implode(', ') }} (si tolgono dalla scheda del verbale).</p>
            @endif
            <x-campo-giustificativi id="documenti-scrivi" label="Documenti da allegare" accept=".pdf,application/pdf"
                                    aiuto="Solo PDF, fino a 8 MB ciascuno: per esempio il preventivo approvato. Nel testo li citi come «Allegato 1, 2…»." :errori="$errors->get('documenti.*')" />
        </x-card>

        <div class="form-actions form-actions-fine vb-piede">
            <button type="submit" name="dopo" value="scheda" class="btn btn-secondary" x-bind:disabled="inviato">Salva e torna alla scheda</button>
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Salva</button>
        </div>
    </form>
@endsection
