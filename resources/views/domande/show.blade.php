{{--
    Scheda della domanda (tavole "Domanda — in votazione", "— accolta",
    "— non accolta", "— voti pari"). In alto l'azione che serve adesso
    (decisione, diniego, quota), poi il voto del CD e la cronologia; a
    destra richiedente e dati della domanda.
--}}
@extends('layouts.app')

@section('title', 'Domanda ' . $domanda->cognome . ' ' . $domanda->nome)

@section('content')
    @php
        $minore = $domanda->tipo_socio === 'minorenne';
        $totale = $domanda->voti->count();
        $esitoOggi = $domanda->isInAttesa() ? $domanda->esitoAttuale() : null;
        $etichettaEsito = [
            \App\Models\Domanda::ESITO_APPROVATA => 'Accolta',
            \App\Models\Domanda::ESITO_RIFIUTATA => 'Non accolta',
            \App\Models\Domanda::ESITO_D_UFFICIO => "Accolta d'ufficio",
            \App\Models\Domanda::ESITO_PARITA => 'Decide il Presidente',
        ];
        $etichettaVoto = fn ($v) => ['favorevole' => 'Favorevole', 'contrario' => 'Contrario'][$v->voto] ?? null;
        $senzaEmail = $domanda->voti->whereNull('consigliere_email');
        $giornoSollecito = $domanda->created_at->copy()->addDays(\App\Models\Domanda::GIORNO_SOLLECITO);
        $promoPossibile = $promozione || $domanda->quota_promo;
        $documento = trim((\App\Http\Requests\IscrizioneDatiRequest::DOCUMENTI[$domanda->documento_tipo] ?? '') . ' ' . $domanda->documento_numero);
    @endphp

    <x-page-header :titolo="$domanda->cognome" :evidenza="$domanda->nome" :indietro="route('domande.index')" indietro-label="Domande">
        <div class="badge-riga">
            @include('domande._stato')
            <x-badge variante="neutral">{{ $minore ? 'Socio giovane' : 'Socio ordinario' }}</x-badge>
        </div>
        <x-slot:azioni>
            @if ($domanda->ricevuta_path)
                <a href="{{ route('domande.ricevuta', $domanda) }}" class="btn btn-secondary"><x-icona nome="download" width="14" height="14" /> Ricevuta PDF</a>
            @endif
            @if ($domanda->is_test && $puoModificare && ! $domanda->socio_id)
                <button type="button" class="btn btn-danger" @click="$dispatch('apri-modale', 'elimina-prova')"><x-icona nome="cestino" width="14" height="14" /> Elimina</button>
            @endif
        </x-slot:azioni>
    </x-page-header>

    <x-sola-lettura sezione="domande" />

    @if ($domanda->is_test)
        <x-avviso tipo="info" titolo="Domanda di prova.">Creata con la modalità di prova attiva: non entra nell'audit e non si iscrive nel libro soci. Si può eliminare.</x-avviso>
    @endif

    {{-- ── Azione del momento ─────────────────────────────────────── --}}
    @if ($domanda->isInParita())
        <x-avviso tipo="warn" titolo="Voti pari e Presidente assente.">
            La votazione si è chiusa il {{ $domanda->votazione_chiusa_il?->format('d/m/Y H:i') }} con {{ $domanda->riepilogoVoti(false) }}: decide il Presidente (statuto, art. 16.4).
        </x-avviso>
        @if ($presidente)
            <x-card titolo="Decisione">
                <form method="POST" action="{{ route('domande.parita', $domanda) }}" class="form-pagina" x-data="{ decisione: @js(old('decisione')), inviato: false }" x-on:submit="inviato = true">
                    @csrf
                    <fieldset @class(['scelta-gruppo', 'has-error' => $errors->has('decisione')])>
                        <legend>Decisione del Presidente <span class="obbligatorio" aria-hidden="true">*</span></legend>
                        <div class="scelta-opzioni">
                            <label class="scelta"><input type="radio" name="decisione" value="accogli" x-model="decisione" required> Accogli la domanda</label>
                            <label class="scelta"><input type="radio" name="decisione" value="respingi" x-model="decisione"> Non accogliere</label>
                        </div>
                        @error('decisione')<div class="form-error">{{ $message }}</div>@enderror
                    </fieldset>
                    <div x-show="decisione === 'respingi'" x-cloak>
                        <x-campo name="motivo" label="Motivazione per il richiedente" tipo="textarea" rows="4" maxlength="2000"
                                 obbligatorio-se="decisione === 'respingi'"
                                 aiuto="Va nell'email al richiedente e nel verbale. Almeno 20 caratteri." />
                    </div>
                    <p class="form-help">Con «Accogli» parte l'email con la quota; con «Non accogliere» il diniego motivato, con il diritto di appello all'Assemblea.</p>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Registra la decisione</button>
                    </div>
                </form>
            </x-card>
        @endif
    @elseif ($domanda->isDaRifiutare())
        <x-avviso tipo="warn" :titolo="'Da inviare entro ' . $domanda->scadenzaDiniego()?->locale('it')->isoFormat('dddd D MMMM')">
            (10 giorni dalla delibera del {{ $domanda->esito_il?->format('d/m/Y') }}, statuto art. 6.5).
            @unless ($presidente) Il diniego lo scrive e lo invia il Presidente. @endunless
        </x-avviso>
        @if ($presidente)
            <x-card titolo="Comunica il diniego">
                <form method="POST" action="{{ route('domande.diniego', $domanda) }}" class="form-pagina" x-data="{ inviato: false }" x-on:submit="inviato = true">
                    @csrf
                    <x-campo name="motivo" label="Motivazione per il richiedente" tipo="textarea" rows="6" required maxlength="2000"
                             aiuto="Va nell'email al richiedente e nel verbale. Le motivazioni dei singoli consiglieri restano solo nel verbale." />
                    <x-avviso tipo="info">L'email indica anche il <strong>diritto di appello all'Assemblea entro 30 giorni</strong> (statuto, art. 6.5) e la conservazione dei dati per 12 mesi.</x-avviso>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Invia il diniego</button>
                    </div>
                </form>
            </x-card>
        @endif
    @elseif ($domanda->socio_id)
        <x-avviso tipo="ok" titolo="Iscritta nel libro soci.">
            Quota versata il {{ $domanda->quota_versata_il?->format('d/m/Y') }}.
            @if ($domanda->socio)
                <a href="{{ route('soci.show', $domanda->socio) }}">Apri la scheda del socio</a>
            @endif
        </x-avviso>
    @elseif ($domanda->isApprovata())
        <x-avviso tipo="ok" :titolo="'Accolta il ' . $domanda->esito_il?->format('d/m/Y')">
            {{ $domanda->silenzio_assenso ? "d'ufficio: alla scadenza ha votato meno della maggioranza del Consiglio." : ($domanda->decisione_presidente_il ? 'su decisione del Presidente, con voti pari.' : 'con ' . $domanda->riepilogoVoti() . '.') }}
            @if ($domanda->esito_comunicato_il)
                L'email con quota, IBAN e termine è partita il {{ $domanda->esito_comunicato_il->format('d/m/Y H:i') }}.
            @else
                L'email al richiedente non è partita: controlla il registro degli invii.
            @endif
        </x-avviso>
        @unless ($iban)
            <x-avviso tipo="warn" titolo="IBAN non registrato.">Nella scheda Associazione manca l'IBAN: nell'email di accoglimento compare il segnaposto «{{ \App\Services\Quota::IBAN_SEGNAPOSTO }}».</x-avviso>
        @endunless
        <x-card titolo="Quota e iscrizione">
            <dl class="dati-griglia">
                <x-dato label="Quota" :valore="number_format((float) $domanda->quota_importo, 2, ',', '.') . ' € · ' . ($minore ? 'socio giovane' : 'socio ordinario')" />
                <x-dato label="Versare entro">
                    @if ($domanda->quota_scadenza && today()->gt($domanda->quota_scadenza))
                        {{-- Termine passato: margine per l'accredito prima della decadenza automatica --}}
                        <x-badge variante="warn">Termine passato il {{ $domanda->quota_scadenza->format('d/m/Y') }}</x-badge>
                        · decade il {{ $domanda->dataDecadenza()->format('d/m/Y') }} se il versamento non è registrato
                    @else
                        {{ $domanda->quota_scadenza?->format('d/m/Y') }} · {{ $domanda->quota_scadenza ? 'tra ' . (int) today()->diffInDays($domanda->quota_scadenza) . ' giorni' : '' }}
                    @endif
                </x-dato>
                <x-dato label="IBAN comunicato" :valore="$iban ?: \App\Services\Quota::IBAN_SEGNAPOSTO" codice />
                <x-dato label="Causale" :valore="\App\Services\Quota::causale($domanda)" />
            </dl>
            @if ($puoModificare && $domanda->is_test)
                {{-- Collaudo K8: il riquadro mancava senza spiegazione --}}
                <x-avviso tipo="info" titolo="Domanda di prova: non si iscrive nel libro soci.">
                    È stata mandata da collegati, con le iscrizioni chiuse: serve a provare il modulo e il voto, non crea soci né quote.
                    Per provare l'iscrizione con la quota, apri le iscrizioni (Impostazioni › Ente) e manda una domanda da una finestra anonima.
                </x-avviso>
            @endif
            @if ($puoModificare && ! $domanda->is_test)
                <form method="POST" action="{{ route('domande.iscrivi', $domanda) }}" class="form-pagina quota-iscrivi"
                      x-data="{ data: @js(old('quota_versata_il', today()->toDateString())), inviato: false }" x-on:submit="inviato = true">
                    @csrf
                    <h3 class="sottotitolo-card">Quota versata: iscrivi nel libro soci</h3>
                    <div class="form-grid">
                        <x-campo name="quota_versata_il" label="Data del versamento" tipo="date" required x-model="data" :max="today()->toDateString()" />
                        <x-campo name="metodo" label="Modalità" tipo="select" required :opzioni="$metodi" value="bonifico" />
                    </div>
                    @if ($promoPossibile)
                        <div x-show="Number(data.slice(5, 7)) >= {{ \App\Services\Quota::MESE_PROMOZIONE }}" x-cloak>
                            <x-spunta name="promo" :checked="old('promo', $domanda->quota_promo || $promozione)"
                                      :label="'Promozione ottobre–dicembre: la quota vale fino al 31/12 dell\'anno dopo'" />
                        </div>
                    @endif
                    <ul class="elenco-spunte">
                        <li><x-icona nome="ok" width="14" height="14" /> Crea il socio e registra la quota (rinnovo e Prima Nota)</li>
                        <li><x-icona nome="ok" width="14" height="14" /> Copia anagrafica, residenza, contatti{{ $minore ? ' e dati del genitore come tutore' : '' }}</li>
                        <li><x-icona nome="ok" width="14" height="14" /> Privacy: metodo «online», data e IP della domanda, informativa v{{ $domanda->informativa_versione }}</li>
                        <li><x-icona nome="ok" width="14" height="14" /> Collega la domanda alla scheda del socio; da qui non si torna indietro</li>
                    </ul>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Iscrivi nel libro soci</button>
                    </div>
                </form>
            @endif
        </x-card>
    @elseif ($domanda->isRifiutata())
        <x-avviso tipo="info" titolo="Non accolta.">
            Diniego inviato il {{ $domanda->esito_comunicato_il?->format('d/m/Y') ?? '—' }}; appello all'Assemblea possibile entro 30 giorni dalla comunicazione. I dati si cancellano dopo 12 mesi.
        </x-avviso>
    @elseif ($domanda->isDecaduta())
        <x-avviso tipo="info" titolo="Decaduta.">Quota non versata entro il {{ $domanda->quota_scadenza?->format('d/m/Y') }} (statuto, art. 6.6). I dati si cancellano dopo 12 mesi.</x-avviso>
    @elseif ($domanda->isInVerifica())
        <x-avviso tipo="info" titolo="Da confermare.">Domanda presentata con il vecchio flusso: il richiedente deve confermarla dal link dell'email entro il {{ $domanda->token_conferma_scadenza?->format('d/m/Y H:i') }}.</x-avviso>
    @endif

    <div class="scheda-colonne">
        <div class="scheda-colonna">
            {{-- ── Voto del Consiglio Direttivo ─────────────────────────── --}}
            <x-card titolo="Voto del Consiglio Direttivo">
                @if ($totale === 0)
                    <p class="form-help">
                        @if ($domanda->isInAttesa())
                            Nessun consigliere in carica registrato: la votazione non ha convocati. Alla scadenza la domanda sarà accolta d'ufficio.
                        @else
                            Nessun voto registrato.
                        @endif
                    </p>
                @else
                    @if ($domanda->isInAttesa())
                        <div class="mini-kpi-riga">
                            <div class="mini-kpi">
                                <span class="mini-kpi-etichetta">Termine</span>
                                <span class="mini-kpi-valore">{{ $domanda->scadenza_cd?->format('d/m · H:i') }}</span>
                                <span class="mini-kpi-nota">{{ $domanda->scadenza_cd?->locale('it')->diffForHumans() }}</span>
                            </div>
                            <div class="mini-kpi">
                                <span class="mini-kpi-etichetta">Quorum</span>
                                <span class="mini-kpi-valore">{{ $domanda->quorum() }} su {{ $totale }}</span>
                                <span class="mini-kpi-nota">Maggioranza dei componenti</span>
                            </div>
                            <div class="mini-kpi">
                                <span class="mini-kpi-etichetta">Voti espressi</span>
                                <span class="mini-kpi-valore">{{ $domanda->votiEspressi() }}</span>
                                <span class="mini-kpi-nota">{{ $domanda->riepilogoVoti(false) }}</span>
                            </div>
                            <div class="mini-kpi">
                                <span class="mini-kpi-etichetta">Con i voti di oggi</span>
                                <span class="mini-kpi-valore evidenza">{{ $etichettaEsito[$esitoOggi] ?? '—' }}</span>
                                <span class="mini-kpi-nota">{{ $esitoOggi === \App\Models\Domanda::ESITO_D_UFFICIO ? 'Quorum non raggiunto' : 'Maggioranza dei voti espressi' }}</span>
                            </div>
                        </div>
                    @endif

                    @if ($senzaEmail->isNotEmpty())
                        <x-avviso tipo="warn" :titolo="$senzaEmail->count() === 1 ? 'Un consigliere senza email.' : $senzaEmail->count() . ' consiglieri senza email.'">
                            {{ $senzaEmail->pluck('consigliere_nome')->join(', ', ' e ') }} non {{ $senzaEmail->count() === 1 ? 'ha' : 'hanno' }} un indirizzo email nella scheda socio: nessun link, ma {{ $senzaEmail->count() === 1 ? 'conta' : 'contano' }} nel quorum come non {{ $senzaEmail->count() === 1 ? 'votante' : 'votanti' }}. Il Presidente è stato avvisato.
                        </x-avviso>
                    @endif

                    <div class="table-wrap">
                        <table class="data-table responsive">
                            <thead>
                                <tr>
                                    {{-- Tre colonne: la carica sta sotto il nome, il "quando" sotto il voto (niente scorrimento orizzontale nella colonna della scheda) --}}
                                    <th scope="col">Consigliere</th>
                                    <th scope="col">Voto</th>
                                    <th scope="col"><span class="sr-only">Azioni</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($domanda->voti as $voto)
                                    <tr>
                                        <td class="td-primario">
                                            <span class="td-name-testo">{{ $voto->consigliere_nome }}</span>
                                            <span class="td-sub">
                                                {{ $voto->membroCd?->carica ?? ($voto->presidente ? 'Presidente' : 'Consigliere') }}
                                                @if ($voto->presidente)
                                                    <x-badge variante="info">Decide in parità</x-badge>
                                                @endif
                                            </span>
                                        </td>
                                        <td data-label="Voto">
                                            @if ($voto->voto)
                                                <x-badge :variante="$voto->voto === 'favorevole' ? 'ok' : 'ko'">{{ $etichettaVoto($voto) }}</x-badge>
                                            @else
                                                <x-badge variante="neutral">{{ $domanda->isInAttesa() ? 'Da votare' : 'Non ha votato' }}</x-badge>
                                            @endif
                                            <span class="td-sub">
                                                @if ($voto->votato_il)
                                                    {{ $voto->votato_il->format('d/m, H:i') }}
                                                @elseif (! $voto->consigliere_email)
                                                    Senza email
                                                @elseif ($voto->link_inviato_il)
                                                    Link del {{ $voto->link_inviato_il->format('d/m') }}
                                                @else
                                                    Link non partito
                                                @endif
                                            </span>
                                        </td>
                                        <td class="td-azioni">
                                            @if ($domanda->isInAttesa() && ! $voto->voto && $voto->consigliere_email && $puoModificare)
                                                <form method="POST" action="{{ route('domande.reinvia', [$domanda, $voto]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-secondary btn-sm">Invia di nuovo</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($voto->voto === 'contrario' && filled($voto->motivo_rifiuto))
                                        <tr class="riga-nota">
                                            <td colspan="3"><strong>Motivazione di {{ $voto->consigliere_nome }}:</strong> {{ $voto->motivo_rifiuto }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($domanda->isInAttesa())
                        <p class="form-help">
                            @if (! $domanda->sollecito_inviato_il && $giornoSollecito->isFuture())
                                {{ ucfirst($giornoSollecito->locale('it')->isoFormat('dddd D MMMM')) }} parte il sollecito automatico a chi non ha votato.
                            @endif
                            La votazione si chiude alla scadenza, o prima quando hanno votato tutti. Nessuno può votare al posto di un consigliere.
                        </p>
                    @endif
                @endif

                @if ($domanda->verbaleCd)
                    <div class="riquadro-riga">
                        <div>
                            <strong>Bozza di verbale del CD</strong>
                            <span class="td-sub">Consultazione telematica {{ $domanda->created_at->format('d/m') }}–{{ $domanda->votazione_chiusa_il?->format('d/m/Y') }} · voto di ciascun consigliere</span>
                        </div>
                        <div class="riga-azioni">
                            <x-badge variante="neutral">{{ ucfirst($domanda->verbaleCd->stato) }}</x-badge>
                            <a href="{{ route('domande.verbale', $domanda) }}">Apri il verbale</a>
                        </div>
                    </div>
                @endif
            </x-card>

            {{-- ── Cronologia ────────────────────────────────────────────── --}}
            @php
                $eventi = collect([
                    [$domanda->created_at, 'Domanda presentata dal modulo ' . ($domanda->cartacea ? 'cartaceo' : 'online'), false],
                    [$domanda->voti->min('link_inviato_il'), 'Link di voto inviati a ' . $domanda->voti->whereNotNull('link_inviato_il')->count() . ' consiglieri', false],
                ]);
                foreach ($domanda->voti->whereNotNull('votato_il') as $v) {
                    $eventi->push([$v->votato_il, 'Voto di ' . $v->consigliere_nome, false]);
                }
                $eventi->push([$domanda->sollecito_inviato_il, 'Sollecito a chi non aveva votato', false]);
                $comeChiusa = ['scadenza' => ' alla scadenza', 'tutti' => ': hanno votato tutti'][$domanda->votazione_chiusura ?? ''] ?? '';
                $eventi->push([$domanda->votazione_chiusa_il, 'Votazione chiusa' . $comeChiusa, false]);
                $eventi->push([$domanda->decisione_presidente_il, 'Decisione del Presidente', false]);
                $eventi->push([$domanda->esito_comunicato_il, 'Esito comunicato al richiedente', false]);
                $eventi->push([$domanda->quota_versata_il, 'Quota versata e iscrizione nel libro soci', false]);
                if ($domanda->isInAttesa()) {
                    if (! $domanda->sollecito_inviato_il) {
                        $eventi->push([$giornoSollecito, 'Sollecito a chi non ha votato', true]);
                    }
                    $eventi->push([$domanda->scadenza_cd, 'Chiusura della votazione ed esito', true]);
                }
                $eventi = $eventi->filter(fn ($e) => $e[0] !== null)->sortBy(fn ($e) => $e[0]->timestamp)->values();
            @endphp
            <x-card titolo="Cronologia">
                <ol class="cronologia">
                    @foreach ($eventi as [$quando, $testo, $futuro])
                        <li @class(['futuro' => $futuro])>
                            <span class="cronologia-punto" aria-hidden="true"></span>
                            <span class="cronologia-quando">{{ $quando->format('d/m/Y' . ($quando->format('H:i') !== '00:00' ? ', H:i' : '')) }}</span>
                            <span>
                                {{ $testo }}
                                @if ($futuro)
                                    <span class="sr-only">(previsto)</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        </div>

        <div class="scheda-colonna">
            <x-card :titolo="$minore ? 'Minore che diventa socio' : 'Richiedente'">
                <dl class="dati-griglia">
                    <x-dato label="Codice fiscale" :valore="$domanda->codice_fiscale" codice />
                    <x-dato :label="$domanda->sesso === 'F' ? 'Nata' : 'Nato'" :valore="$domanda->data_nascita?->format('d/m/Y') . ' a ' . $domanda->luogo_nascita" />
                    <x-dato label="Residenza" :valore="$domanda->indirizzo . ', ' . $domanda->cap . ' ' . $domanda->citta . ' (' . $domanda->provincia . ')'" />
                    <x-dato label="Email" :valore="$minore && $domanda->email === $domanda->genitore_email ? null : $domanda->email" />
                    @unless ($minore)
                        <x-dato label="Telefono" :valore="$domanda->telefono" />
                        <x-dato label="Documento" :valore="$documento" />
                    @endunless
                </dl>
            </x-card>

            @if ($minore)
                <x-card titolo="Genitore che presenta la domanda">
                    <dl class="dati-griglia">
                        <x-dato label="Nome" :valore="$domanda->genitore_nome" />
                        <x-dato label="Codice fiscale" :valore="$domanda->genitore_codice_fiscale" codice />
                        <x-dato label="Email" :valore="$domanda->genitore_email" />
                        <x-dato label="Telefono" :valore="$domanda->genitore_telefono" />
                        <x-dato label="Documento" :valore="$documento" />
                    </dl>
                </x-card>
            @endif

            <x-card titolo="Domanda">
                <dl class="dati-griglia">
                    <x-dato label="Categoria" :valore="($minore ? 'Socio giovane' : 'Socio ordinario') . ($domanda->quota_importo ? ' · ' . number_format((float) $domanda->quota_importo, 0, ',', '.') . ' €' : '')" />
                    <x-dato label="Presentata il" :valore="$domanda->created_at->format('d/m/Y, H:i')" />
                    <x-dato label="Statuto accettato" :valore="$domanda->statuto_versione ? 'v' . $domanda->statuto_versione : null" />
                    <x-dato label="Informativa" :valore="$domanda->informativa_versione ? 'v' . $domanda->informativa_versione . ($domanda->informativa_aperta_il ? ' · aperta prima dell\'invio' : '') : null" />
                    <x-dato label="Foto e video" :valore="$domanda->consenso_foto_video ? 'Consenso dato' : 'Consenso non dato'" />
                    @if ($minore)
                        {{-- Assenso del minore dai 14 anni (art. 16, c. 2, D.Lgs. 36/2021; v0.31.0) --}}
                        <x-dato label="Assenso del minore">
                            @if ($domanda->assenso_minore_il)
                                Prestato il {{ $domanda->assenso_minore_il->format('d/m/Y, H:i') }} · {{ $domanda->etaAllaDomanda() }} anni{{ $domanda->consenso_foto_video ? ' · compresi foto e video' : '' }}
                            @elseif ($domanda->richiedeAssensoMinore())
                                <span class="testo-urgente">Mancante</span>
                            @else
                                Non richiesto (meno di {{ \App\Models\Domanda::ETA_ASSENSO_MINORE }} anni)
                            @endif
                            <span class="norma dato-norma">Art. 16, c. 2, D.Lgs. 36/2021</span>
                        </x-dato>
                    @endif
                    <x-dato label="Canale" :valore="$domanda->cartacea ? 'Cartaceo' : 'Online'" />
                </dl>
            </x-card>
        </div>
    </div>

    {{-- ── Modali ───────────────────────────────────────────────────── --}}
    @if ($domanda->is_test && $puoModificare && ! $domanda->socio_id)
        <x-modale nome="elimina-prova" titolo="Elimina la domanda di prova">
            <p>Si eliminano la domanda, i voti, la ricevuta e l'eventuale bozza di verbale. Non si può annullare.</p>
            <form method="POST" action="{{ route('domande.destroy', $domanda) }}" id="form-elimina">@csrf @method('DELETE')</form>
            <x-slot:piede>
                <button type="button" class="btn btn-ghost" @click="aperta = false">Annulla</button>
                <button type="submit" form="form-elimina" class="btn btn-danger">Elimina</button>
            </x-slot:piede>
        </x-modale>
    @endif
@endsection
