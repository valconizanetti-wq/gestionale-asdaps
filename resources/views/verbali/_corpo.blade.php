{{--
    Testo del verbale, uguale nella scheda (VB-3) e nel PDF (VB-4): per la
    riunione presenze, punti, delibere con il voto di ciascuno e l'esito;
    per la consultazione la proposta, i voti con data, ora e collegamento
    (art. 16.4 e) e l'esito; per i verbali delle domande e quelli registrati
    da fuori il testo salvato. I testi dell'editor sono già ripuliti da
    HtmlSanitizer al salvataggio.
    Variabili: $v (Verbale con punti.voti, seduta.presenze, consultazione.voti), $documenti.
--}}
@use('App\Models\SedutaPresenza')
@use('App\Models\VerbaleVoto')
@use('App\Models\VerbalePunto')
@php
    $s = $v->seduta;
    $c = $v->consultazione;
    $dataLunga = fn ($d) => $d ? $d->locale('it')->isoFormat('D MMMM YYYY') : '—';
@endphp

@if ($v->tipo === 'cd_riunione' && ! $v->da_fuori && $s)
    @php
        $presenti = $s->presenze->filter->presente();
        $componenti = $s->presenze->count();
        $costituita = \App\Services\Riunioni::costituita($s);
    @endphp
    <p>
        Il giorno {{ $dataLunga($s->data) }}, alle ore {{ substr((string) $s->ora_inizio, 0, 5) }},
        @if ($s->luogo && $s->videoconferenza) in {{ $s->luogo }} e in videoconferenza,
        @elseif ($s->videoconferenza) in videoconferenza,
        @elseif ($s->luogo) in {{ $s->luogo }},
        @endif
        si è riunito il Consiglio Direttivo, convocato il {{ $s->convocazione_inviata_il?->format('d/m/Y') ?? '—' }}
        @if ($s->urgente) con urgenza ({{ $s->motivo_urgenza }}), @endif
        per discutere e deliberare sul seguente ordine del giorno:
    </p>
    <ol>
        @foreach ($v->punti as $p)<li>{{ $p->titolo }}</li>@endforeach
    </ol>

    <h2>Presenze</h2>
    <table class="vb-tabella">
        <thead><tr><th>Componente</th><th>Carica</th><th>Presenza</th></tr></thead>
        <tbody>
            @foreach ($s->presenze as $pr)
                <tr>
                    <td>{{ $pr->nome }}</td>
                    <td>{{ $pr->carica }}</td>
                    <td>{{ SedutaPresenza::PRESENZE[$pr->presenza] }}@if ($pr->presenza === 'assente') {{ $pr->giustificato ? '(giustificato)' : '(non giustificato)' }}@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p>
        Presiede {{ $v->presidente_assemblea }}; verbalizza {{ $v->segretario_verbalizzante }}.
        Sono presenti {{ $presenti->count() }} componenti su {{ $componenti }}:
        @if ($costituita)
            il Consiglio è validamente costituito (maggioranza dei componenti, art. 16.3).
        @else
            il Consiglio non è validamente costituito e non può deliberare (art. 16.3).
        @endif
    </p>

    @foreach ($v->punti as $p)
        <h2>{{ $p->ordine }}. {{ $p->titolo }}</h2>
        @if (! \App\Support\HtmlSanitizer::vuoto($p->discussione_html))
            <div class="vb-html">{!! $p->discussione_html !!}</div>
        @endif
        @if ($p->ha_delibera)
            <div class="vb-delibera">
                <p class="vb-delibera-titolo"><strong>Delibera</strong>@if ($p->tipo_delibera && $p->tipo_delibera !== 'ordinaria') · {{ $p->tipoTesto() }}@endif</p>
                <div class="vb-html">{!! $p->delibera_html !!}</div>
                @if ($p->voti->isNotEmpty())
                    <table class="vb-tabella vb-voti">
                        <thead><tr><th>Componente</th><th>Voto</th></tr></thead>
                        <tbody>
                            @foreach ($p->voti as $vt)
                                <tr><td>{{ $vt->nome }}@if ($vt->presidente) · Presidente @endif</td><td>{{ VerbaleVoto::VOTI[$vt->voto] ?? '—' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @if ($p->esito_testo)
                    <p class="vb-esito"><strong>{{ $p->esito_testo }}</strong></p>
                @endif
            </div>
        @endif
    @endforeach

    <p class="vb-chiusura">
        Esauriti i punti all'ordine del giorno, la riunione si chiude{{ $v->ora_chiusura ? ' alle ore ' . substr((string) $v->ora_chiusura, 0, 5) : '' }}.
    </p>

@elseif ($v->tipo === 'cd_consultazione' && $c)
    <p>
        Il {{ $dataLunga($c->avviata_il) }}, su iniziativa del Presidente{{ $c->in_sostituzione ? ' (avviata dal Vicepresidente in sua sostituzione, art. 18.3)' : '' }},
        il Consiglio Direttivo è stato consultato per via telematica (statuto, art. 16.4) sulla proposta di delibera qui sotto.
        Ogni componente ha ricevuto per email un link personale; il termine per il voto era il {{ $c->termine->format('d/m/Y') }} alle ore {{ $c->termine->format('H:i') }}.
        La consultazione si è chiusa il {{ $c->chiusa_il?->format('d/m/Y') }} alle ore {{ $c->chiusa_il?->format('H:i') }}.
    </p>
    <h2>Proposta: {{ $c->oggetto }}</h2>
    <div class="vb-html">{!! $c->testo_html !!}</div>

    <h2>Voti</h2>
    <table class="vb-tabella vb-voti">
        <thead><tr><th>Componente</th><th>Voto</th><th>Data e ora</th><th>Collegamento</th></tr></thead>
        <tbody>
            @foreach ($c->voti as $vt)
                <tr>
                    <td>{{ $vt->nome }} · {{ $vt->carica }}@if ($vt->nota)<br><span class="vb-nota">Nota: {{ $vt->nota }}</span>@endif</td>
                    <td>{{ $vt->haVotato() ? $vt->votoTesto() : 'Non ha votato' }}</td>
                    <td>{{ $vt->votato_il?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $vt->haVotato() ? 'link personale ' . $vt->collegamento() : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="vb-esito"><strong>{{ $c->esito_testo }}</strong></p>

@else
    @if ($v->da_fuori)
        <table class="vb-dati">
            <tr><td class="etichetta">Presidente della seduta</td><td>{{ $v->presidente_assemblea }}</td></tr>
            <tr><td class="etichetta">Segretario verbalizzante</td><td>{{ $v->segretario_verbalizzante }}</td></tr>
            @if ($v->soci_presenti)<tr><td class="etichetta">Presenti</td><td>{{ $v->soci_presenti }}</td></tr>@endif
            @if ($v->approva_rendiconto_anno)<tr><td class="etichetta">Rendiconto</td><td>Approva il rendiconto dell'anno {{ $v->approva_rendiconto_anno }}</td></tr>@endif
        </table>
        <h2>Delibere in breve</h2>
        <p class="vb-nota">Il testo completo è nella copia firmata del verbale.</p>
    @endif
    <div class="vb-html">{!! $v->corpo_html !!}</div>
@endif

@if ($documenti->isNotEmpty())
    <h2>Documenti allegati</h2>
    <ol>
        @foreach ($documenti as $d)<li>Allegato {{ $loop->iteration }}: {{ $d->nome }}</li>@endforeach
    </ol>
@endif
