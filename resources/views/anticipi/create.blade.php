{{--
    Passo 1 della nota di rimborso (PN-7) o della dichiarazione di rinuncia
    (AN-4b): le spese scelte in AN-3, la data, come sarà rimborsato (E3:
    la modalità fissa il conto del pagamento), l'IBAN solo per il bonifico.
    Variabili: $tipo, $socio, $carica, $spese, $totale, $minimo, $prossimo, $visto.
--}}
@extends('layouts.app')

@use('App\Support\Importo')

@php $rinuncia = $tipo === 'rinuncia'; @endphp

@section('title', $rinuncia ? 'Rinuncia al rimborso' : 'Nuova nota di rimborso')

@section('content')
    <x-page-header :titolo="$rinuncia ? 'Rinuncia al' : 'Nuova nota di'" evidenza="rimborso"
                   :sottotitolo="$socio->cognome . ' ' . $socio->nome . ($carica ? ' · ' . $carica : '') . ' · ' . \App\Services\NoteRimborso::spese($spese->count()) . ' · ' . Importo::it($totale, true)"
                   :indietro="route('prima-nota.anticipi.index')" indietro-label="Anticipi e note di rimborso" />

    <x-passi :corrente="1" :nomi="$rinuncia ? ['Dichiarazione', 'Firma', 'Registrazione'] : ['Nota', 'Firma', 'Pagamento']" etichetta="Passi della nota" class="an-passi" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Controlla i dati." data-errori-elenco>{{ $errors->first() }}</x-avviso>
    @endif

    <form method="POST" action="{{ route('prima-nota.anticipi.store') }}" class="form-pagina" novalidate
          x-data="{ modalita: @js(old('modalita', 'cassa')), inviato: false }" x-on:submit="inviato = true">
        @csrf
        <input type="hidden" name="socio_id" value="{{ $socio->id }}">
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        @foreach ($spese as $m)
            <input type="hidden" name="spese[]" value="{{ $m->id }}">
        @endforeach

        <div class="pn-colonne">
            <div class="pn-colonna">
                <x-card :titolo="$rinuncia ? '1 · Dichiarazione di rinuncia' : '1 · Nota di rimborso'">
                    <div class="form-grid form-grid-2">
                        <x-campo name="data_nota" :label="$rinuncia ? 'Data della dichiarazione' : 'Data della nota'" tipo="date" required
                                 :value="today()->toDateString()" :min="$minimo" :max="today()->toDateString()"
                                 aiuto="Non prima della spesa più recente." />
                    </div>

                    @unless ($rinuncia)
                        <fieldset @class(['scelta-gruppo', 'form-riga', 'has-error' => $errors->has('modalita')])>
                            <legend>Rimborso <span class="obbligatorio" aria-hidden="true">*</span></legend>
                            <div class="scelta-opzioni">
                                <label class="scelta">
                                    <input type="radio" name="modalita" value="cassa" x-model="modalita">
                                    <span class="scelta-testo">Cassa <span class="scelta-sub">In contanti</span></span>
                                </label>
                                <label class="scelta">
                                    <input type="radio" name="modalita" value="banca" x-model="modalita">
                                    <span class="scelta-testo">Banca <span class="scelta-sub">Bonifico all'IBAN del socio</span></span>
                                </label>
                            </div>
                            <div class="form-help">Il pagamento userà questo conto: la nota firmata e il movimento devono dire la stessa cosa. Per cambiarlo si annulla la nota e se ne fa un'altra.</div>
                            @error('modalita')<div class="form-error">{{ $message }}</div>@enderror
                        </fieldset>
                        <div class="form-riga" x-show="modalita === 'banca'" x-cloak>
                            <x-campo name="iban" label="IBAN del socio" codice obbligatorio-se="modalita === 'banca'" maxlength="40" autocomplete="off"
                                     aiuto="Va nella nota: il bonifico si fa a questo IBAN." />
                        </div>
                    @endunless

                    <div class="form-riga">
                        <x-campo name="note" label="Note" tipo="textarea" rows="2" maxlength="500" aiuto="Facoltative: compaiono nella nota." />
                    </div>
                </x-card>

                <x-card titolo="Spese" senza-padding>
                    <div class="table-wrap">
                        <table class="data-table responsive">
                            <thead>
                                <tr>
                                    <th scope="col">Data</th>
                                    <th scope="col">Descrizione</th>
                                    <th scope="col">Categoria</th>
                                    <th scope="col">Documento</th>
                                    <th scope="col" class="td-num">Importo €</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($spese as $m)
                                    <tr>
                                        <td data-label="Data">{{ $m->dataSpesa()->format('d/m/Y') }}</td>
                                        <td data-label="Descrizione" class="td-primario">{{ $m->descrizione }}@if ($m->soggetto)<span class="td-sub">{{ $m->soggetto }}</span>@endif</td>
                                        <td data-label="Categoria">{{ $m->categoria?->nome }}</td>
                                        <td data-label="Documento">{{ \App\Services\NoteRimborso::documento($m) }}</td>
                                        <td data-label="Importo €" class="td-num">{{ Importo::it($m->importo) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="td-num td-solo-desktop"><strong>Totale</strong></td>
                                    <td data-label="Totale €" class="td-num"><strong>{{ Importo::it($totale) }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </x-card>
            </div>

            <aside class="pn-colonna">
                <x-card titolo="Come funziona" class="pn-aiuto">
                    @if ($rinuncia)
                        <p>Il socio dichiara di aver pagato le spese e di <strong>rinunciare al rimborso</strong>, donando l'importo alla Compagnia.</p>
                        <p>Stessa numerazione delle note (circa <strong>{{ $prossimo }}</strong>): una sola serie, senza buchi.</p>
                        <p>Dopo la firma si registrano insieme, in cassa e nella stessa data, l'uscita delle spese e l'entrata della donazione: il saldo non cambia.</p>
                    @else
                        <p>Il gestionale assegna il numero (circa <strong>{{ $prossimo }}</strong>) e salva il PDF da stampare e far firmare dal socio.</p>
                        <p>Visto: <strong>{{ $visto['carica'] }}</strong>@if ($visto['nome']) ({{ $visto['nome'] }})@endif.</p>
                        <p>Con i contanti il socio firma anche la riga «Per quietanza» quando riceve i soldi.</p>
                        <p>Le spese restano fuori dai saldi fino al pagamento, che dà la data del movimento.</p>
                    @endif
                </x-card>
            </aside>
        </div>

        <div class="form-actions">
            <a href="{{ route('prima-nota.anticipi.index') }}" class="btn btn-ghost">Annulla</a>
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">{{ $rinuncia ? 'Genera la dichiarazione (PDF)' : 'Genera la nota (PDF)' }}</button>
        </div>
    </form>
@endsection
