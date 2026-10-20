{{--
    Scheda della riunione del CD (v0.44.0, tavola CD-5). Passi: bozza →
    convocata → presenze → verbale. In bozza: modifica, prova, «Invia la
    convocazione» con il controllo dei termini (art. 16.1, V4). Convocata:
    rinvio (con l'avviso ai membri) o annullamento, con il motivo; dal
    giorno della riunione le presenze, con il quorum (art. 16.3), chi
    presiede e chi verbalizza. Poi il verbale per punti (VB-2).
    Variabili: $s, $documenti, $membri, $termine, $righe, $assenze.
--}}
@extends('layouts.app')

@use('App\Models\SedutaPresenza')

@section('title', 'Riunione del CD del ' . $s->data->format('d/m/Y'))

@section('content')
    @php
        $puoModificare = auth()->user()->puoModificare('verbali');
        $passo = match ($s->stato) { 'bozza', 'rinviata' => 1, 'convocata' => 2, 'svolta' => $s->verbale && ! $s->verbale->isBozza() ? 5 : 4, default => 1 };
        $nomi = [
            'Bozza',
            'Convocata' . ($s->convocazione_inviata_il ? ' · ' . $s->convocazione_inviata_il->format('d/m') : ''),
            'Presenze',
            'Verbale',
        ];
        $quorum = \App\Services\Votazioni::quorumCd(max(1, $righe->count() ?: $membri->count()));
    @endphp

    <x-page-header titolo="Riunione del" :evidenza="'CD · ' . $s->data->format('d/m/Y')"
                   :sottotitolo="ucfirst($s->data->locale('it')->isoFormat('dddd D MMMM YYYY')) . ', ore ' . substr((string) $s->ora_inizio, 0, 5) . ($s->luogo ? ' · ' . $s->luogo : '') . ($s->videoconferenza ? ' · videoconferenza' : '')"
                   :indietro="route('verbali.index')" indietro-label="Verbali">
        <div class="badge-riga">
            <x-badge :variante="$s->statoVariante()">{{ $s->statoTesto() }}</x-badge>
            @if ($s->urgente)<x-badge variante="warn">Urgente</x-badge>@endif
        </div>
        @if ($puoModificare)
            <x-slot:azioni>
                @if ($s->stato === 'bozza')
                    <a href="{{ route('verbali.riunioni.edit', $s) }}" class="btn btn-secondary"><x-icona nome="matita" width="14" height="14" /> Modifica</a>
                @elseif ($s->stato === 'convocata' && ! $s->verbale)
                    <button type="button" class="btn btn-secondary" x-data x-on:click="$dispatch('apri-modale', 'rinvia')">Rinvia</button>
                @elseif ($s->stato === 'rinviata')
                    <form method="POST" action="{{ route('verbali.riunioni.riconvoca', $s) }}">@csrf<button type="submit" class="btn btn-primary">Riconvoca</button></form>
                @endif
                @if (in_array($s->stato, ['bozza'], true))
                    <form method="POST" action="{{ route('verbali.riunioni.destroy', $s) }}" x-data x-on:submit="if (! confirm('Eliminare la bozza della riunione?')) $event.preventDefault()">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger-outline">Elimina la bozza</button>
                    </form>
                @elseif (in_array($s->stato, ['convocata', 'rinviata'], true) && ! $s->verbale)
                    <button type="button" class="btn btn-danger-outline" x-data x-on:click="$dispatch('apri-modale', 'annulla')">Annulla la riunione</button>
                @endif
            </x-slot:azioni>
        @endif
    </x-page-header>

    <x-sola-lettura sezione="verbali" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Operazione non eseguita.">{{ $errors->first() }}</x-avviso>
    @endif

    @if ($s->stato === 'annullata')
        <x-avviso tipo="info" titolo="Annullata il {{ $s->annullata_il?->format('d/m/Y \a\l\l\e H:i') }}.">Motivo: {{ $s->motivo_annullamento }}</x-avviso>
    @elseif ($s->stato === 'rinviata')
        <x-avviso tipo="info" titolo="Rinviata il {{ $s->rinviata_il?->format('d/m/Y') }}.">Motivo: {{ $s->rinvio_note }}. Con «Riconvoca» torna in bozza per la nuova data, e si convoca di nuovo.</x-avviso>
    @else
        <x-passi :corrente="$passo" :nomi="$nomi" etichetta="Passi della riunione" class="an-passi" />
    @endif

    <div class="pn-colonne">
        <div class="pn-colonna">
            @if ($s->stato === 'bozza')
                <x-card titolo="Convocazione">
                    @if ($termine)
                        <x-avviso tipo="warn" titolo="Non si può ancora convocare.">{{ $termine }}</x-avviso>
                    @else
                        <p class="form-intro">
                            La convocazione parte per email ai <strong>{{ $membri->count() }} membri in carica</strong>, con data, luogo e ordine del giorno.
                            @if ($s->urgente) È <strong>urgente</strong>: {{ $s->motivo_urgenza }}. @endif
                            Dopo, data, luogo e ordine del giorno non si cambiano più.
                        </p>
                    @endif
                    <ul class="vb-destinatari">
                        @foreach ($membri as $m)
                            <li>
                                <strong>{{ $m->nomeSocio() }}</strong> · {{ \App\Services\ConsiglioDirettivo::etichetta($m->carica_codice) }}
                                @if (trim((string) $m->socio->email) === '')<span class="testo-ko">senza email</span>@else<span class="testo-secondario">{{ $m->socio->email }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($puoModificare)
                        <div class="form-actions">
                            <form method="POST" action="{{ route('verbali.riunioni.prova', $s) }}" x-data="{ inviato: false }" x-on:submit="inviato = true">
                                @csrf<button type="submit" class="btn btn-secondary" x-bind:disabled="inviato">Invia una prova a me</button>
                            </form>
                            @unless ($termine)
                                <form method="POST" action="{{ route('verbali.riunioni.convoca', $s) }}" x-data="{ inviato: false }" x-on:submit="inviato = true">
                                    @csrf<button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Invia la convocazione</button>
                                </form>
                            @endunless
                        </div>
                    @endif
                </x-card>
            @endif

            @if ($s->presenzeAperte() && $puoModificare)
                <x-card titolo="Presenze">
                    <form method="POST" action="{{ route('verbali.riunioni.presenze', $s) }}" class="form-modale"
                          x-data="{ presenze: @js($righe->mapWithKeys(fn ($r) => [(string) $r->socio_id => old('presenze.' . $r->socio_id . '.presenza', $r->presenza)])),
                                    nomi: @js($righe->mapWithKeys(fn ($r) => [(string) $r->socio_id => $r->nome])),
                                    get presenti() { return Object.keys(this.presenze).filter(k => this.presenze[k] !== 'assente') },
                                    inviato: false }" x-on:submit="inviato = true">
                        @csrf
                        <div class="table-wrap">
                            <table class="data-table responsive">
                                <thead>
                                    <tr>
                                        <th scope="col">Componente</th>
                                        @foreach (SedutaPresenza::PRESENZE as $nome)<th scope="col" class="td-centro">{{ $nome }}</th>@endforeach
                                        <th scope="col" class="td-centro">Giustificato</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($righe as $r)
                                        <tr>
                                            <td class="td-primario" data-label="Componente">{{ $r->nome }}<span class="td-sub">{{ $r->carica }}</span></td>
                                            @foreach (SedutaPresenza::PRESENZE as $codice => $nome)
                                                <td data-label="{{ $nome }}" class="td-centro">
                                                    <input type="radio" name="presenze[{{ $r->socio_id }}][presenza]" value="{{ $codice }}" x-model="presenze['{{ $r->socio_id }}']" aria-label="{{ $r->nome }}: {{ $nome }}">
                                                </td>
                                            @endforeach
                                            <td data-label="Giustificato" class="td-centro">
                                                <input type="checkbox" name="presenze[{{ $r->socio_id }}][giustificato]" value="1" @checked(old('presenze.' . $r->socio_id . '.giustificato', $r->giustificato))
                                                       x-bind:disabled="presenze['{{ $r->socio_id }}'] !== 'assente'" aria-label="{{ $r->nome }}: assenza giustificata">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="vb-quorum" aria-live="polite">
                            <span x-text="presenti.length + ' presenti su {{ $righe->count() }}'"></span> ·
                            <span x-show="presenti.length >= {{ $quorum }}" class="testo-ok">validamente costituito (servono {{ $quorum }}, art. 16.3)</span>
                            <span x-show="presenti.length < {{ $quorum }}" x-cloak class="testo-ko">non costituito: servono almeno {{ $quorum }} presenti, non si delibera</span>
                        </p>
                        <div class="form-grid">
                            @foreach (['presidente_socio_id' => 'Presiede', 'segretario_socio_id' => 'Segretario verbalizzante'] as $campo => $etichetta)
                                @php
                                    $predefinito = $campo === 'presidente_socio_id'
                                        ? ($s->presidente_socio_id ?? $righe->firstWhere('presidente', true)?->socio_id)
                                        : ($s->segretario_socio_id ?? $righe->first(fn ($r) => str_starts_with($r->carica, 'Segretario'))?->socio_id);
                                @endphp
                                <div @class(['form-field', 'has-error' => $errors->has($campo)])>
                                    <label class="form-label" for="{{ $campo }}">{{ $etichetta }}<span class="obbligatorio" aria-hidden="true">*</span></label>
                                    <select id="{{ $campo }}" name="{{ $campo }}" class="form-select" required>
                                        <option value="">Scegli tra i presenti</option>
                                        @foreach ($righe as $r)
                                            <option value="{{ $r->socio_id }}" @selected((int) old($campo, $predefinito) === $r->socio_id) x-bind:disabled="presenze['{{ $r->socio_id }}'] === 'assente'">{{ $r->nome }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                        <p class="form-help">Di norma presiede il Presidente (o il Vicepresidente) e verbalizza il Segretario; se mancano, il Consiglio sceglie tra i presenti.</p>
                        <div class="form-actions form-actions-fine">
                            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">{{ $s->verbale ? 'Aggiorna le presenze' : 'Registra le presenze e scrivi il verbale' }}</button>
                        </div>
                    </form>
                </x-card>
            @elseif ($s->stato === 'convocata' && $s->data->isFuture())
                <x-card titolo="Presenze">
                    <p class="form-help">Le presenze si registrano dal giorno della riunione, {{ $s->data->format('d/m/Y') }}.</p>
                </x-card>
            @endif

            @if ($s->stato === 'svolta' && $s->presenze->isNotEmpty() && ! $s->presenzeAperte())
                <x-card titolo="Presenze">
                    <ul class="vb-destinatari">
                        @foreach ($s->presenze as $pr)
                            <li><strong>{{ $pr->nome }}</strong> · {{ $pr->carica }} · {{ SedutaPresenza::PRESENZE[$pr->presenza] }}@if ($pr->presenza === 'assente') {{ $pr->giustificato ? '(giustificato)' : '(non giustificato)' }}@endif</li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @foreach ($assenze as $nome => $date)
                <x-avviso tipo="info" titolo="{{ $nome }}: terza assenza di fila senza giustificazione.">Era assente anche il {{ implode(' e il ', $date) }} (art. 15.5): lo valuta il Consiglio.</x-avviso>
            @endforeach

            @if ($s->verbale)
                <x-card titolo="Verbale">
                    <p>
                        <x-badge :variante="$s->verbale->statoVariante()">{{ $s->verbale->statoTesto() }}</x-badge>
                        @if ($s->verbale->codice()) <strong>{{ $s->verbale->codice() }}</strong> @endif
                    </p>
                    <div class="form-actions">
                        <a href="{{ route('verbali.show', $s->verbale) }}" class="btn btn-secondary">Scheda del verbale</a>
                        @if ($puoModificare && $s->verbale->isBozza())
                            <a href="{{ route('verbali.scrivi', $s->verbale) }}" class="btn btn-primary">Scrivi il verbale</a>
                        @endif
                    </div>
                </x-card>
            @endif
        </div>

        <div class="pn-colonna">
            <x-card titolo="Ordine del giorno">
                <ol class="vb-odg">
                    @foreach ($s->punti() as $p)<li>{{ $p }}</li>@endforeach
                </ol>
            </x-card>
            <x-card titolo="Dati">
                <dl class="dati-griglia dati-una-colonna">
                    <x-dato label="Quando" :valore="$s->data->format('d/m/Y') . ', ore ' . substr((string) $s->ora_inizio, 0, 5)" />
                    <x-dato label="Luogo" :valore="$s->luogo" />
                    @if ($s->videoconferenza)<x-dato label="Videoconferenza" :valore="$s->collegamento ?: 'sì'" />@endif
                    @if ($s->urgente)<x-dato label="Urgente" :valore="$s->motivo_urgenza" />@endif
                    @if ($s->convocazione_inviata_il)<x-dato label="Convocazione" :valore="'inviata il ' . $s->convocazione_inviata_il->format('d/m/Y H:i') . ' a ' . $s->convocati . ' membri'" />@endif
                    <x-dato label="Preparata da" :valore="$s->creataDa?->name" />
                </dl>
            </x-card>
            <x-card titolo="Documenti">
                @if ($documenti->isEmpty())
                    <p class="form-help">Nessun documento.</p>
                @else
                    <ul class="ct-documenti">
                        @foreach ($documenti as $d)
                            <li>
                                <a href="{{ route('verbali.riunioni.documenti.show', [$s, $d]) }}" target="_blank" rel="noopener"><x-icona nome="documento" width="16" height="16" /> {{ $d->nome }}</a>
                                <span class="testo-secondario">{{ $d->pesoTesto() }}</span>
                                @if ($puoModificare && $s->modificabile())
                                    <form method="POST" action="{{ route('verbali.riunioni.documenti.destroy', [$s, $d]) }}" class="vb-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="action-btn" aria-label="Togli {{ $d->nome }}"><x-icona nome="cestino" width="14" height="14" /></button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>

    @if ($puoModificare)
        @push('modals')
            @if ($s->stato === 'convocata' && ! $s->verbale)
                <x-modale nome="rinvia" titolo="Rinviare la riunione?" :aperta="$errors->rinvio->any()">
                    <form method="POST" action="{{ route('verbali.riunioni.rinvia', $s) }}" class="form-modale" id="form-rinvia">
                        @csrf
                        <p>La riunione resta nella storia come «rinviata»; con «Riconvoca» si sceglie la nuova data e si manda una nuova convocazione.</p>
                        <div @class(['form-field', 'has-error' => $errors->rinvio->has('motivo_rinvio')])>
                            <label class="form-label" for="motivo_rinvio">Motivo<span class="obbligatorio" aria-hidden="true">*</span></label>
                            <textarea id="motivo_rinvio" name="motivo_rinvio" rows="3" required minlength="10" maxlength="500" class="form-textarea">{{ $errors->rinvio->any() ? old('motivo_rinvio') : '' }}</textarea>
                            @foreach ($errors->rinvio->get('motivo_rinvio') as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                        </div>
                        <label class="vb-spunta"><input type="checkbox" name="avvisa" value="1" checked> <span>Avvisa i membri per email, con il motivo</span></label>
                    </form>
                    <x-slot:piede>
                        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Indietro</button>
                        <button type="submit" form="form-rinvia" class="btn btn-primary">Rinvia</button>
                    </x-slot:piede>
                </x-modale>
            @endif
            @if (in_array($s->stato, ['convocata', 'rinviata'], true) && ! $s->verbale)
                <x-modale nome="annulla" titolo="Annullare la riunione?" :aperta="$errors->annulla->any()">
                    <form method="POST" action="{{ route('verbali.riunioni.annulla', $s) }}" class="form-modale" id="form-annulla">
                        @csrf
                        <p>Una riunione annullata non si tiene e non ha verbale; resta nella storia con il motivo. L'operazione arriva per email agli altri del CD.</p>
                        <div @class(['form-field', 'has-error' => $errors->annulla->has('motivo_annullamento')])>
                            <label class="form-label" for="motivo_annullamento">Motivo<span class="obbligatorio" aria-hidden="true">*</span></label>
                            <textarea id="motivo_annullamento" name="motivo_annullamento" rows="3" required minlength="10" maxlength="500" class="form-textarea">{{ $errors->annulla->any() ? old('motivo_annullamento') : '' }}</textarea>
                            @foreach ($errors->annulla->get('motivo_annullamento') as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                        </div>
                    </form>
                    <x-slot:piede>
                        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Indietro</button>
                        <button type="submit" form="form-annulla" class="btn btn-danger">Annulla la riunione</button>
                    </x-slot:piede>
                </x-modale>
            @endif
        @endpush
    @endif
@endsection
