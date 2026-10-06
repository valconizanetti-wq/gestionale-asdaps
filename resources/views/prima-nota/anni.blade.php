@extends('layouts.app')

@section('title', 'Anni contabili')

@use('App\Support\Importo')

@section('content')
    @php
        $utente = auth()->user();
        $puoModificare = $utente->puoModificare('prima_nota');
        $saldiModificabili = $puoModificare && ! $rigaSaldi?->isChiuso();
        $serveMotivo = $movimentiSaldi > 0 && $rigaSaldi?->saldo_apertura_cassa !== null;
    @endphp

    <x-page-header titolo="Anni" evidenza="contabili" sottotitolo="Saldi di apertura, chiusura dell'anno e riapertura"
                   :indietro="route('prima-nota.index')" indietro-label="Prima Nota" />

    <x-sola-lettura sezione="prima_nota" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Operazione non eseguita.">{{ $errors->first() }}</x-avviso>
    @endif

    <x-card titolo="Anni" senza-padding>
        @if ($elenco->isEmpty())
            <x-vuoto>Nessun anno ancora: l'anno {{ $annoSaldi }} si apre con il primo movimento o salvando qui sotto i saldi di partenza.</x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive">
                    <thead>
                        <tr>
                            <th scope="col">Anno</th>
                            <th scope="col">Stato</th>
                            <th scope="col" class="td-num">Apertura cassa €</th>
                            <th scope="col" class="td-num">Apertura banca €</th>
                            <th scope="col" class="td-num">Saldo cassa €</th>
                            <th scope="col" class="td-num">Saldo banca €</th>
                            <th scope="col">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($elenco as $e)
                            @php $r = $e['riga']; @endphp
                            <tr>
                                <td class="td-primario"><a href="{{ route('prima-nota.index', ['anno' => $r->anno]) }}" class="pn-desc">{{ $r->anno }}</a></td>
                                <td data-label="Stato">
                                    @if ($r->isChiuso())
                                        <x-badge variante="neutral">Chiuso</x-badge>
                                    @else
                                        <x-badge variante="ok">Aperto</x-badge>
                                    @endif
                                </td>
                                <td data-label="Apertura cassa €" class="td-num">{{ $e['apertura']['da_impostare'] ? '—' : Importo::it($e['apertura']['cassa']) }}</td>
                                <td data-label="Apertura banca €" class="td-num">{{ $e['apertura']['da_impostare'] ? '—' : Importo::it($e['apertura']['banca']) }}</td>
                                <td data-label="Saldo cassa €" class="td-num"><strong>{{ Importo::it($e['finali']['cassa']) }}</strong></td>
                                <td data-label="Saldo banca €" class="td-num"><strong>{{ Importo::it($e['finali']['banca']) }}</strong></td>
                                <td data-label="Note">
                                    @if ($r->isChiuso())
                                        Chiuso il {{ $r->chiuso_il?->format('d/m/Y') }}@if ($r->approvazione_rif) · rendiconto approvato il {{ $r->approvazione_data?->format('d/m/Y') }} ({{ $r->approvazione_rif }})@endif
                                    @elseif ($e['apertura']['da_impostare'])
                                        <x-badge variante="warn">Saldi di apertura da impostare</x-badge>
                                    @elseif ($e['apertura']['da_anno_prima'])
                                        Apertura dai saldi finali dell'anno prima
                                    @else
                                        {{ $r->saldi_fonte ?: 'Saldi di apertura scritti a mano' }}
                                    @endif
                                    <span class="td-sub">{{ $e['movimenti'] }} {{ $e['movimenti'] === 1 ? 'movimento' : 'movimenti' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="scheda-griglia">
        <x-card :titolo="'Saldi di apertura ' . $annoSaldi">
            @if ($saldiModificabili)
                <form method="POST" action="{{ route('prima-nota.anni.saldi', $annoSaldi) }}" enctype="multipart/form-data" class="form-modale"
                      x-data="{ inviato: false }" x-on:submit="inviato = true">
                    @csrf
                    @method('PUT')
                    <p class="form-help">Solo per il primo anno del gestionale: dagli anni dopo l'apertura sono i saldi finali dell'anno prima. Per la Compagnia: la chiusura 2026 del vecchio gestionale.</p>
                    <div class="form-grid">
                        <x-campo name="saldo_apertura_cassa" label="Cassa €" required inputmode="decimal" autocomplete="off"
                                 :value="$rigaSaldi?->saldo_apertura_cassa !== null ? Importo::it($rigaSaldi->saldo_apertura_cassa) : ''" />
                        <x-campo name="saldo_apertura_banca" label="Banca €" required inputmode="decimal" autocomplete="off"
                                 :value="$rigaSaldi?->saldo_apertura_banca !== null ? Importo::it($rigaSaldi->saldo_apertura_banca) : ''" />
                    </div>
                    <x-campo name="saldi_fonte" label="Da dove vengono" :value="$rigaSaldi?->saldi_fonte" maxlength="255"
                             aiuto="Es. «Chiusura 2026 del vecchio gestionale, verificata con l'estratto conto»." />
                    <div class="form-field">
                        <label class="form-label" for="campo-documento">Documento</label>
                        @if ($rigaSaldi?->saldi_documento_path)
                            <span class="pn-allegato"><a href="{{ route('prima-nota.anni.documento', $annoSaldi) }}">{{ $rigaSaldi->saldi_documento_nome }}</a></span>
                        @endif
                        <input id="campo-documento" type="file" name="documento" class="form-input pn-file" accept=".pdf,.csv,.txt,.jpg,.jpeg,.png,.xlsx,.xls">
                        <div class="form-help">Facoltativo: estratto conto, esportazione dal vecchio gestionale. Fino a 8 MB.</div>
                        @error('documento')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    @if ($serveMotivo)
                        <x-campo name="motivazione" label="Motivazione" tipo="textarea" required rows="2"
                                 aiuto="L'anno ha già movimenti: almeno 15 caratteri, restano nell'Audit Log." />
                    @endif
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Salva i saldi</button>
                    </div>
                </form>
            @else
                <dl class="dati-griglia">
                    <x-dato label="Cassa" :valore="$aperturaSaldi['da_impostare'] ? null : Importo::it($aperturaSaldi['cassa'], true)" />
                    <x-dato label="Banca" :valore="$aperturaSaldi['da_impostare'] ? null : Importo::it($aperturaSaldi['banca'], true)" />
                    <x-dato label="Da dove vengono" :valore="$rigaSaldi?->saldi_fonte" class="col-span-2" />
                </dl>
            @endif
        </x-card>

        <div class="pn-colonna">
            @if ($daChiudere)
                <x-card :titolo="'Chiudi l\'anno ' . $daChiudere->anno">
                    <ul class="pn-controlli">
                        @foreach ($controlli as [$testo, $fatto, $nota])
                            <li @class(['pn-controllo', 'fatto' => $fatto, 'manca' => ! $fatto])>
                                <x-icona :nome="$fatto ? 'ok' : 'avviso'" />
                                <div><strong>{{ $testo }}</strong><span>{{ $nota }}</span></div>
                            </li>
                        @endforeach
                    </ul>
                    @if ($puoModificare)
                        <form method="POST" action="{{ route('prima-nota.anni.chiudi', $daChiudere->anno) }}" class="form-modale form-riga"
                              x-data="{ inviato: false }" x-on:submit="inviato = true">
                            @csrf
                            <div class="form-grid">
                                <x-campo name="approvazione_data" label="Rendiconto approvato il" tipo="date" required />
                                <x-campo name="approvazione_rif" label="Verbale dell'Assemblea" required maxlength="255"
                                         aiuto="Es. «Assemblea ordinaria, verbale n. 2/2028»." />
                            </div>
                            <p class="form-help">Alla chiusura i saldi finali diventano l'apertura del {{ $daChiudere->anno + 1 }}; dopo, ogni correzione chiede una motivazione.</p>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" @disabled(! $chiudibile) x-bind:disabled="inviato || {{ $chiudibile ? 'false' : 'true' }}">Chiudi l'anno {{ $daChiudere->anno }}</button>
                            </div>
                        </form>
                    @endif
                </x-card>
            @endif

            @if ($daRiaprire && $utente->ruolo === 'presidente')
                <x-card :titolo="'Riapri l\'anno ' . $daRiaprire->anno">
                    <form method="POST" action="{{ route('prima-nota.anni.riapri', $daRiaprire->anno) }}" class="form-modale">
                        @csrf
                        <p class="form-help">Solo per errori gravi scoperti dopo la chiusura: la riapertura resta nell'Audit Log come operazione ad alto rischio e i saldi finali tornano a essere calcolati.</p>
                        <div class="form-field">
                            <label class="form-label" for="motivazione-riapri">Motivazione<span class="obbligatorio" aria-hidden="true">*</span></label>
                            <textarea id="motivazione-riapri" name="motivazione" rows="2" required minlength="15" maxlength="1000" class="form-textarea"
                                      aria-describedby="motivazione-riapri-aiuto"></textarea>
                            <div class="form-help" id="motivazione-riapri-aiuto">Almeno 15 caratteri.</div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-danger-outline">Riapri l'anno {{ $daRiaprire->anno }}</button>
                        </div>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
@endsection
