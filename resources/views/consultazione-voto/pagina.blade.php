{{--
    Consultazione telematica del CD dal link personale (v0.44.0, tavola CT-2,
    telefono). Stati: aperta (proposta, documenti, voto), votato
    (riepilogo), chiusa prima del voto, link non valido.
    Variabili: $stato, $voto, $c, $token, $documenti.
--}}
@extends('layouts.pubblico')
@section('title', match ($stato) { 'aperta' => 'Consultazione del Consiglio Direttivo', 'votato' => 'Voto registrato', 'chiusa' => 'Consultazione chiusa', default => 'Link non valido' })
@section('stretto', '1')
@section('content')
<div @class(['pubblico-colonna', 'voto-pagina', 'pubblico-centrato' => $stato !== 'aperta'])>
    @if ($stato === 'votato')
        <div class="esito-icona ok"><x-icona nome="ok" /></div>
    @elseif ($stato !== 'aperta')
        <div class="esito-icona"><x-icona nome="lucchetto" /></div>
    @endif

    <div class="iscr-titolo">
        <span class="iscr-occhiello">Consiglio Direttivo · consultazione telematica</span>
        <h1>{{ match ($stato) { 'aperta' => $c->oggetto, 'votato' => 'Voto registrato', 'chiusa' => 'Consultazione chiusa', default => 'Link non valido' } }}</h1>
    </div>

    @if ($stato === 'non_valido')
        <p class="testo-secondario">Il link non corrisponde a nessuna consultazione. Controlla di aver aperto il collegamento completo dall'email; se il problema resta, scrivi alla segreteria.</p>
    @else
        @if ($stato === 'aperta')
            <p class="privacy-saluto">Ciao {{ $voto->nomeProprio() }}, il Presidente chiede al Consiglio di deliberare per via telematica su questa proposta (statuto, art. 16.4).</p>
        @elseif ($stato === 'votato')
            <p class="testo-secondario">Grazie {{ $voto->nomeProprio() }}. Il tuo voto su <strong>{{ $c->oggetto }}</strong> è registrato.</p>
        @else
            <p class="testo-secondario">La consultazione su <strong>{{ $c->oggetto }}</strong> si è chiusa prima del tuo voto, che quindi non è stato registrato.</p>
        @endif

        @if ($stato === 'aperta')
            <section class="voto-scheda" aria-labelledby="ct-proposta">
                <span class="iscr-occhiello" id="ct-proposta">Proposta di delibera</span>
                <div class="testo-editor">{!! $c->testo_html !!}</div>
                @if ($documenti->isNotEmpty())
                    <ul class="ct-documenti">
                        @foreach ($documenti as $d)
                            <li>
                                <a href="{{ route('consultazione.documento', [$token, $d->id]) }}" target="_blank" rel="noopener">
                                    <x-icona nome="documento" width="16" height="16" /> {{ $d->nome }}
                                </a>
                                <span class="testo-secondario">{{ $d->pesoTesto() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <div class="voto-termine" role="note">
                <x-icona nome="orologio" width="18" height="18" />
                <div>
                    <span class="voto-termine-etichetta">Vota entro</span>
                    <span class="voto-termine-data">{{ $c->termine->locale('it')->isoFormat('dddd D MMMM YYYY, [ore] HH:mm') }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('consultazione.vota', $token) }}" class="voto-modulo"
                  x-data="{ scelta: @js(old('voto')), inviato: false }" x-on:submit="inviato = true">
                @csrf
                <fieldset @class(['voto-scelte', 'has-error' => $errors->has('voto')])>
                    <legend>Il tuo voto <span class="obbligatorio" aria-hidden="true">*</span></legend>
                    @foreach ([
                        'favorevole' => ['favorevole', 'La proposta va approvata'],
                        'contrario' => ['contrario', 'La proposta va respinta'],
                        'astenuto' => ['neutro', 'Non entra nel conto della maggioranza'],
                        'riunione' => ['neutro', 'Con un terzo dei componenti la consultazione si ferma'],
                    ] as $valore => [$classe, $sotto])
                        <label class="voto-scelta {{ $classe }}">
                            <input type="radio" name="voto" value="{{ $valore }}" x-model="scelta" @checked(old('voto') === $valore) @if ($loop->first) required @endif>
                            <span><strong>{{ \App\Models\ConsultazioneVoto::VOTI[$valore] }}</strong><span>{{ $sotto }}</span></span>
                        </label>
                    @endforeach
                    @error('voto')<div class="form-error">{{ $message }}</div>@enderror
                    <div class="voto-motivo">
                        <x-campo name="nota" label="Nota (facoltativa)" tipo="textarea" rows="3" maxlength="1000" aiuto="Va nel verbale insieme al tuo voto." />
                    </div>
                </fieldset>
                <div class="voto-modulo-piede">
                    <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Registra il voto</button>
                </div>
            </form>
        @else
            <section class="voto-scheda">
                <dl class="voto-dati">
                    @if ($stato === 'votato')
                        <x-dato label="Il tuo voto">
                            <x-badge :variante="match ($voto->voto) { 'favorevole' => 'ok', 'contrario' => 'ko', 'riunione' => 'warn', default => 'neutral' }">{{ $voto->votoTesto() }}</x-badge>
                        </x-dato>
                        @if ($voto->nota)
                            <x-dato label="Nota" :valore="$voto->nota" />
                        @endif
                        <x-dato label="Registrato il" :valore="$voto->votato_il->locale('it')->isoFormat('D MMMM YYYY, HH:mm')" />
                    @endif
                    @if (in_array($c->stato, ['chiusa', 'ritirata'], true))
                        <x-dato label="Esito"><x-badge :variante="$c->statoVariante()">{{ $c->statoTesto() }}</x-badge></x-dato>
                        <x-dato label="Chiusa il" :valore="$c->chiusa_il?->locale('it')->isoFormat('D MMMM YYYY, HH:mm')" />
                    @endif
                </dl>
            </section>
            @if ($stato === 'votato' && $c->isInCorso())
                <x-avviso tipo="info"><strong>La consultazione è aperta</strong> fino a {{ $c->termine->locale('it')->isoFormat('dddd D MMMM, [ore] HH:mm') }}. Alla chiusura riceverai l'esito per email.</x-avviso>
            @elseif (in_array($c->stato, ['chiusa', 'ritirata'], true))
                <x-avviso tipo="info"><strong>{{ $c->stato === 'ritirata' ? 'La consultazione è stata ritirata.' : 'La consultazione è chiusa.' }}</strong> Te lo abbiamo scritto per email.</x-avviso>
            @endif
            <p class="testo-secondario">Puoi chiudere questa pagina.</p>
        @endif
    @endif

    @if ($stato !== 'aperta' && config('sito.piede.email'))
        <a href="mailto:{{ config('sito.piede.email') }}" class="btn btn-secondary">Scrivi alla segreteria</a>
    @endif
</div>
@endsection
