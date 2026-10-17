@extends('layouts.app')

@section('title', "{$socio->cognome} {$socio->nome}")

@section('content')
    @php
        $utente = auth()->user();
        $puoModificare = $utente->puoModificare('soci');
        $minorenne = $socio->isMinorenne();
        $cancellato = $socio->stato === 'cancellato';
        // Consenso privacy e link personale (privacy-link.md §3)
        $versioneInformativa = \App\Services\ProfiloEnte::versione('informativa');
        $statoPrivacy = $socio->statoPrivacy($versioneInformativa);
        $linkAperto = $socio->privacyLinkAperto;
        $destPrivacy = $socio->destinatarioPrivacy();
        $puoLinkPrivacy = $puoModificare && ! $socio->pseudonimizzato && $socio->stato === 'attivo';
    @endphp

    <x-page-header :titolo="$socio->cognome" :evidenza="$socio->nome" :indietro="route('soci.index')" indietro-label="Soci">
        <div class="badge-riga">
            <x-badge-stato dominio="socio" :valore="$socio->stato" />
            <x-badge variante="neutral">{{ $tipi[$socio->tipo] ?? $socio->tipo }}</x-badge>
            @if ($minorenne)<x-badge variante="info">Minorenne</x-badge>@endif
            @if ($socio->tipoIncoerenteConEta())<x-badge variante="warn">Tipo da aggiornare</x-badge>@endif
            @if ($socio->pseudonimizzato)<x-badge variante="neutral">Pseudonimizzato</x-badge>@endif
            @if ($socio->stato === 'attivo')<x-badge :variante="$tessera['variante']">Tessera CSEN: {{ ['mancante' => 'nessuna', 'scaduta' => 'scaduta'][$tessera['stato']] ?? mb_strtolower($tessera['testo']) }}</x-badge>@endif
        </div>
        @if ($puoModificare && ! $socio->pseudonimizzato)
            <x-slot:azioni>
                <a href="{{ route('soci.edit', $socio) }}" class="btn btn-primary"><x-icona nome="matita" width="14" height="14" /> Modifica</a>
                @if ($cancellato)
                    <form method="POST" action="{{ route('soci.riattiva', $socio) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary"><x-icona nome="ripristina" width="14" height="14" /> Riattiva</button>
                    </form>
                @else
                    <button type="button" class="btn btn-secondary" @click="$dispatch('apri-modale', 'cessazione')">Registra cessazione</button>
                @endif
            </x-slot:azioni>
        @endif
    </x-page-header>

    <x-sola-lettura sezione="soci" />

    @include('tessere._suggerita')

    @if ($cancellato)
        <x-avviso tipo="info" titolo="Socio cessato.">
            Dal {{ $socio->data_cancellazione?->format('d/m/Y') }} — {{ $socio->motivo_cancellazione }}.
        </x-avviso>
    @endif

    @if ($socio->tipoIncoerenteConEta())
        <x-avviso tipo="warn" titolo="Tipo da aggiornare.">
            Il socio è registrato come «{{ $tipi[$socio->tipo] ?? $socio->tipo }}» ma
            {{ $minorenne ? 'è minorenne' : 'è maggiorenne' }} in base alla data di nascita.
            Il cambio di categoria può incidere sulla quota.
        </x-avviso>
    @endif

    @if ($minorenne && (! $socio->tutore_nome || ! $socio->tutore_telefono))
        <x-avviso tipo="warn" titolo="Dati del tutore incompleti.">Per un socio minorenne servono almeno nome e telefono del tutore.</x-avviso>
    @endif

    <div class="scheda-griglia">
        <x-card titolo="Dati associativi">
            <dl class="dati-griglia">
                <x-dato label="Iscritto dal" :valore="$socio->data_prima_iscrizione?->format('d/m/Y')" />
                <x-dato label="Anno di iscrizione" :valore="$socio->anno_iscrizione" />
                <x-dato label="Quota valida fino al">
                    @if ($socio->quota_scadenza?->gte(today()))
                        <x-badge variante="ok">{{ $socio->quota_scadenza->format('d/m/Y') }}</x-badge>
                    @elseif ($socio->quota_scadenza)
                        <x-badge variante="warn">Scaduta il {{ $socio->quota_scadenza->format('d/m/Y') }}</x-badge>
                    @else
                        <x-badge variante="warn">Da versare</x-badge>
                    @endif
                </x-dato>
                <x-dato label="N. tessera interna" :valore="$socio->numero_tessera" codice />
                <x-dato label="Scheda cartacea" :valore="$socio->scheda_registrazione_presente ? 'Presente' : 'Non presente'" />
                @foreach ($socio->caricheCd as $carica)
                    <x-dato label="Carica nel CD" :valore="$carica->carica" />
                @endforeach
            </dl>
        </x-card>

        <x-card titolo="Anagrafica">
            <dl class="dati-griglia">
                <x-dato label="Codice fiscale" :valore="$socio->codice_fiscale" codice />
                <x-dato label="Data di nascita" :valore="$socio->data_nascita?->format('d/m/Y')" />
                <x-dato label="Luogo di nascita" :valore="$socio->luogo_nascita" />
                <x-dato label="Sesso" :valore="$socio->sesso" />
                <x-dato label="Nazionalità" :valore="$socio->nazionalita" />
            </dl>
        </x-card>

        <x-card titolo="Residenza e contatti">
            <dl class="dati-griglia">
                <x-dato label="Indirizzo" :valore="collect([$socio->indirizzo, trim($socio->cap . ' ' . $socio->citta), $socio->provincia ? '(' . $socio->provincia . ')' : null])->filter()->implode(' ')" />
                <x-dato label="Email">
                    @if ($socio->email)<a href="mailto:{{ $socio->email }}">{{ $socio->email }}</a>@endif
                </x-dato>
                <x-dato label="Telefono">
                    @if ($socio->telefono)<a href="tel:{{ preg_replace('/[^\d+]/', '', $socio->telefono) }}">{{ $socio->telefono }}</a>@endif
                </x-dato>
            </dl>
        </x-card>

        @if ($minorenne || $socio->tutore_nome)
            {{-- Due righe al posto di tre (collaudo OB5, 17/10/2026): nome,
                 relazione e codice fiscale; telefono ed email --}}
            <x-card titolo="Tutore">
                <dl class="dati-griglia dati-griglia-3">
                    <x-dato label="Nome" :valore="$socio->tutore_nome" />
                    <x-dato label="Relazione" :valore="$socio->tutore_relazione" />
                    <x-dato label="Codice fiscale" :valore="$socio->tutore_codice_fiscale" codice />
                    <x-dato label="Telefono">
                        @if ($socio->tutore_telefono)<a href="tel:{{ preg_replace('/[^\d+]/', '', $socio->tutore_telefono) }}">{{ $socio->tutore_telefono }}</a>@endif
                    </x-dato>
                    <x-dato label="Email" class="dato-largo">
                        @if ($socio->tutore_email)<a href="mailto:{{ $socio->tutore_email }}">{{ $socio->tutore_email }}</a>@endif
                    </x-dato>
                </dl>
            </x-card>
        @endif

        {{-- Tessera CSEN (tavola TS-4): la tessera vale un anno dall'emissione e comprende l'assicurazione base --}}
        @php $puoTessere = $utente->puoModificare('tesseramento') && $socio->stato === 'attivo' && ! $socio->pseudonimizzato; @endphp
        <x-card titolo="Tessera CSEN" senza-padding id="tessera">
            {{-- Tessera valida per più di 30 giorni: il rinnovo si fa quando si avvicina la scadenza --}}
            @if ($puoTessere && in_array($tessera['stato'], ['in_scadenza', 'scaduta', 'mancante'], true))
                <x-slot:azioni>
                    <a href="{{ route('tesseramento.index', ['emetti' => $socio->id, 'da' => 'socio']) }}"
                       class="btn btn-sm btn-primary">
                        {{ $socio->tesseramenti->isEmpty() ? 'Emetti' : 'Rinnova' }}
                    </a>
                </x-slot:azioni>
            @endif
            @if (in_array($tessera['stato'], ['scaduta', 'mancante'], true) && $socio->stato === 'attivo')
                <div class="card-body">
                    <x-avviso tipo="ko" :titolo="$tessera['stato'] === 'scaduta' ? 'Tessera scaduta il ' . $tessera['tessera']->scadenza?->format('d/m/Y') . '.' : 'Nessuna tessera CSEN.'">
                        Senza tessera valida il socio non ha l'assicurazione base: non dovrebbe tirare al campo finché non è rinnovata.
                    </x-avviso>
                </div>
            @endif
            @if ($socio->tesseramenti->isEmpty())
                <x-vuoto>Nessuna tessera registrata.</x-vuoto>
            @else
                <div class="table-wrap">
                    {{-- Senza scorrimento orizzontale (collaudo OB5): il tipo sta sotto il numero --}}
                    <table class="data-table responsive ts-tabella ts-scheda">
                        <thead>
                            <tr><th scope="col">Numero</th><th scope="col">Emessa</th><th scope="col">Scade</th><th scope="col">Stato</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($socio->tesseramenti as $t)
                                @php
                                    [$sTesto, $sVar] = $tessera['tessera']?->is($t) && in_array($tessera['stato'], ['in_scadenza', 'valida'], true)
                                        ? [$tessera['testo'], $tessera['variante']]
                                        : \App\Models\Tesseramento::STATI[$t->statoOggi()];
                                @endphp
                                <tr>
                                    <td class="td-primario"><span class="ts-numero">{{ $t->numero_tessera_csen }}</span>
                                        <span class="td-sub">{{ collect([$t->tipoNome(), $t->pacchetto ? 'pacchetto ' . $t->pacchetto->intervallo() : ($t->origine === 'caricata' ? 'dall\'elenco CSEN' : null)])->filter()->implode(' · ') }}</span></td>
                                    <td data-label="Emessa">{{ $t->data_rilascio?->format('d/m/Y') ?? '—' }}</td>
                                    <td data-label="Scade">{{ $t->scadenza?->format('d/m/Y') ?? '—' }}</td>
                                    <td data-label="Stato"><x-badge :variante="$sVar">{{ $sTesto }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        @if ($socio->note)
            <x-card titolo="Note">
                <p class="testo-note">{{ $socio->note }}</p>
            </x-card>
        @endif
    </div>

    {{-- Quote associative (rinnovi-quota.md, v0.36.0) --}}
    @if (auth()->user()->puoAccedere('rinnovi_quota'))
        @include('rinnovi._quote-socio')
    @endif

    {{-- Documenti per l'iscrizione: stato di ciascun requisito --}}
    @php $mancanti = collect($requisiti)->where('stato', false)->count(); @endphp
    <x-card titolo="Documenti per l'iscrizione" senza-padding>
        <x-slot:azioni>
            @if ($mancanti)
                <x-badge variante="warn">{{ $mancanti }} {{ $mancanti === 1 ? 'mancante' : 'mancanti' }}</x-badge>
            @else
                <x-badge variante="ok">Completa</x-badge>
            @endif
        </x-slot:azioni>
        <ul class="requisiti">
            @foreach ($requisiti as $r)
                <li class="requisito">
                    <div class="requisito-testo">
                        <span class="requisito-nome">{{ $r['label'] }}</span>
                        @if ($r['chiave'] === 'privacy')
                            @if ($socio->privacy_accettata_il)
                                <span class="requisito-nota">
                                    @switch ($socio->privacy_metodo)
                                        @case ('online')
                                            Domanda online{{ $socio->domandaOrigine ? ' n. ' . $socio->domandaOrigine->id : '' }} del {{ $socio->privacy_accettata_il->format('d/m/Y') }}
                                            @break
                                        @case ('link')
                                            Link personale il {{ $socio->privacy_accettata_il->format('d/m/Y') }} alle {{ $socio->privacy_accettata_il->format('H:i') }}
                                            @break
                                        @case ('cartaceo')
                                            Modulo firmato il {{ $socio->privacy_accettata_il->format('d/m/Y') }}
                                            @break
                                        @default
                                            Raccolto di persona il {{ $socio->privacy_accettata_il->format('d/m/Y') }}
                                    @endswitch
                                    @if ($socio->privacy_versione)
                                        · informativa v{{ $socio->privacy_versione }}
                                    @endif
                                    @if ($socio->privacy_versione !== $versioneInformativa)
                                        · in vigore la {{ $versioneInformativa }}
                                    @endif
                                </span>
                            @endif
                            @if ($linkAperto)
                                <span class="requisito-nota">Link inviato il {{ $linkAperto->inviato_il->format('d/m/Y') }} alle {{ $linkAperto->inviato_il->format('H:i') }} a {{ $linkAperto->email }} · non ancora usato</span>
                            @elseif ($statoPrivacy !== 'presente' && $puoLinkPrivacy)
                                @if ($destPrivacy)
                                    <span class="requisito-nota">Il link andrà a {{ $destPrivacy['email'] }}{{ $destPrivacy['destinatario'] === 'tutore' ? ' (genitore)' : '' }}</span>
                                @else
                                    <span class="requisito-nota testo-urgente">Nessuna email{{ $minorenne ? ' del genitore' : '' }}: inseriscila nella scheda per mandare il link</span>
                                @endif
                            @endif
                        @elseif ($r['chiave'] === 'certificato' && $r['stato'] === null)
                            <span class="requisito-nota">Non richiesto per l'attività non agonistica</span>
                        @elseif ($r['chiave'] === 'tutore')
                            <span class="requisito-nota">Consenso del genitore o tutore</span>
                        @endif
                    </div>
                    <div class="requisito-stato">
                        @if ($r['chiave'] === 'privacy' && $statoPrivacy !== 'presente' && $statoPrivacy !== 'mancante')
                            @if ($statoPrivacy === 'in_attesa')
                                <x-badge variante="info">In attesa</x-badge>
                            @else
                                <x-badge variante="warn">Da aggiornare</x-badge>
                            @endif
                        @elseif ($r['stato'] === null)
                            <x-badge variante="neutral">Non richiesto</x-badge>
                        @elseif ($r['stato'])
                            <x-badge variante="ok">✓ Presente</x-badge>
                        @else
                            <x-badge variante="warn">Mancante</x-badge>
                        @endif
                    </div>
                    @php
                        // Documento più recente della categoria, per aprirlo dal riquadro
                        $docRequisito = $r['categoria'] ? $socio->documenti->firstWhere('categoria', $r['categoria']) : null;
                    @endphp
                    <div class="requisito-azioni">
                        @if ($r['chiave'] === 'privacy' && $socio->privacy_metodo === 'online' && $socio->domandaOrigine && auth()->user()->puoAccedere('domande'))
                            {{-- Domanda online: ricevuta PDF (dichiarazioni e consensi) e scheda della domanda --}}
                            @if ($socio->domandaOrigine->ricevuta_path)
                                <a href="{{ route('domande.ricevuta', $socio->domandaOrigine) }}" class="btn btn-ghost btn-sm"><x-icona nome="download" width="14" height="14" /> Ricevuta</a>
                            @endif
                            <a href="{{ route('domande.show', $socio->domandaOrigine) }}" class="btn btn-ghost btn-sm"><x-icona nome="occhio" width="14" height="14" /> Domanda</a>
                        @elseif ($r['chiave'] === 'privacy' && $socio->privacy_doc_path)
                            <a href="{{ route('soci.privacy.anteprima', $socio) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><x-icona nome="occhio" width="14" height="14" /> Apri modulo</a>
                        @elseif ($docRequisito)
                            <a href="{{ route('soci.documenti.anteprima', [$socio, $docRequisito]) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><x-icona nome="occhio" width="14" height="14" /> Apri</a>
                        @endif
                    @if ($puoModificare && ! $socio->pseudonimizzato)
                            @if ($r['chiave'] === 'privacy')
                                @if ($puoLinkPrivacy && $statoPrivacy !== 'presente')
                                    @if ($destPrivacy)
                                        <form method="POST" action="{{ route('soci.privacy.link', $socio) }}" x-data="{ inviato: false }" x-on:submit="inviato = true">
                                            @csrf
                                            <button type="submit" @class(['btn btn-sm', 'btn-secondary' => $linkAperto, 'btn-primary' => ! $linkAperto]) x-bind:disabled="inviato">{{ $linkAperto ? 'Invia di nuovo' : 'Invia il link' }}</button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-primary btn-sm" disabled>Invia il link</button>
                                    @endif
                                @endif
                                @if ($statoPrivacy !== 'presente')
                                    <button type="button" class="btn btn-secondary btn-sm" @click="$dispatch('apri-modale', 'privacy-presenza')">In presenza</button>
                                @endif
                                <button type="button" class="btn btn-secondary btn-sm" @click="$dispatch('apri-modale', 'privacy-cartacea')">
                                    {{ $socio->privacy_accettata_il ? 'Nuovo modulo' : 'Carica modulo' }}
                                </button>
                            @elseif ($r['categoria'] && ! $r['stato'])
                                <button type="button" class="btn btn-secondary btn-sm"
                                        @click="$dispatch('categoria-documento', '{{ $r['categoria'] }}'); $dispatch('apri-modale', 'documento')">Carica</button>
                            @endif
                    @endif
                    </div>
                </li>
                @if ($r['chiave'] === 'privacy')
                    {{-- Foto e video: facoltativo, non conta tra i mancanti (privacy-link.md §3) --}}
                    <li class="requisito">
                        <div class="requisito-testo">
                            <span class="requisito-nome">Foto e video</span>
                            <span class="requisito-nota">
                                @if ($socio->foto_video_consenso)
                                    Consenso dato il {{ $socio->foto_video_il?->format('d/m/Y') }} · {{ \App\Models\Socio::ORIGINI_FOTO_VIDEO[$socio->foto_video_origine] ?? 'origine non registrata' }}
                                @elseif ($socio->foto_video_il)
                                    Revocato il {{ $socio->foto_video_il->format('d/m/Y') }} · {{ \App\Models\Socio::ORIGINI_FOTO_VIDEO[$socio->foto_video_origine] ?? '' }}
                                @else
                                    Non dato · per raccoglierlo si manda il link
                                @endif
                            </span>
                            <span class="norma">{{ \App\Models\Domanda::NORMA_IMMAGINE }}</span>
                        </div>
                        <div class="requisito-stato">
                            <x-badge :variante="$socio->foto_video_consenso ? 'ok' : 'neutral'">{{ $socio->foto_video_consenso ? 'Sì' : 'No' }}</x-badge>
                        </div>
                        <div class="requisito-azioni">
                            @if ($puoModificare && ! $socio->pseudonimizzato && $socio->foto_video_consenso)
                                <button type="button" class="btn btn-danger-outline btn-sm" @click="$dispatch('apri-modale', 'foto-revoca')">Registra la revoca</button>
                            @endif
                        </div>
                    </li>
                @endif
            @endforeach
        </ul>
    </x-card>

    <x-card titolo="Documenti caricati" senza-padding>
        @if ($puoModificare && ! $socio->pseudonimizzato)
            <x-slot:azioni>
                <button type="button" class="btn btn-secondary btn-sm" @click="$dispatch('apri-modale', 'documento')"><x-icona nome="piu" width="14" height="14" /> Aggiungi</button>
            </x-slot:azioni>
        @endif
        @if ($socio->documenti->isEmpty())
            <x-vuoto>Nessun documento caricato.</x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive">
                    <thead>
                        <tr><th scope="col">Documento</th><th scope="col">File</th><th scope="col">Caricato il</th><th scope="col"><span class="sr-only">Azioni</span></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($socio->documenti as $doc)
                            <tr>
                                <td class="td-primario">
                                    {{ $categorie[$doc->categoria] ?? $doc->categoria }}
                                    @if ($doc->descrizione)<span class="td-sub">{{ $doc->descrizione }}</span>@endif
                                </td>
                                <td data-label="File" class="td-solo-desktop">{{ $doc->file_originale }}</td>
                                <td data-label="Caricato il">{{ $doc->created_at?->format('d/m/Y') }}</td>
                                <td class="td-azioni">
                                    <a href="{{ route('soci.documenti.anteprima', [$socio, $doc]) }}" target="_blank" rel="noopener" class="action-btn" aria-label="Apri {{ $categorie[$doc->categoria] ?? $doc->categoria }} in una nuova scheda"><x-icona nome="occhio" /></a>
                                    <a href="{{ route('soci.documenti.download', [$socio, $doc]) }}" class="action-btn" aria-label="Scarica {{ $categorie[$doc->categoria] ?? $doc->categoria }}"><x-icona nome="download" /></a>
                                    @if ($puoModificare && ! $socio->pseudonimizzato)
                                        <form method="POST" action="{{ route('soci.documenti.destroy', [$socio, $doc]) }}"
                                              x-data @submit="if (! confirm('Eliminare questo documento? Il file verrà cancellato.')) $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-btn" aria-label="Elimina {{ $categorie[$doc->categoria] ?? $doc->categoria }}"><x-icona nome="cestino" /></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if ($utente->haRuolo('presidente') && ! $socio->pseudonimizzato)
        <x-card titolo="Zona riservata al Presidente">
            <div class="zona-pericolo">
                <p>Eliminare la scheda serve <strong>solo a correggere un inserimento sbagliato</strong>. Per l'uscita di un socio usa «Registra cessazione»: il socio resta nello storico.</p>
                <button type="button" class="btn btn-danger" @click="$dispatch('apri-modale', 'elimina')"><x-icona nome="cestino" width="14" height="14" /> Elimina scheda</button>
            </div>
        </x-card>
    @endif
@endsection

@push('modals')
    @if (auth()->user()->puoModificare('soci') && $socio->stato !== 'cancellato')
        <x-modale nome="cessazione" titolo="Registra cessazione">
            <form method="POST" action="{{ route('soci.cessazione', $socio) }}" id="form-cessazione" class="form-modale">
                @csrf
                <p class="form-help">Il socio passa allo stato «cancellato» e resta nello storico con i suoi dati. Si può annullare con «Riattiva».</p>
                <x-campo name="motivo_cancellazione" label="Motivo" tipo="select" required vuota="Scegli…"
                         :opzioni="array_combine($motivi, $motivi)" />
                <x-campo name="data_cancellazione" label="Data di cessazione" tipo="date" required :value="now()" />
            </form>
            <x-slot:piede>
                <button type="button" class="btn btn-ghost" @click="aperta = false">Annulla</button>
                <button type="submit" form="form-cessazione" class="btn btn-primary">Registra cessazione</button>
            </x-slot:piede>
        </x-modale>
    @endif

    @if (auth()->user()->puoModificare('soci') && ! $socio->pseudonimizzato)
        <x-modale nome="documento" titolo="Carica documento">
            <form method="POST" action="{{ route('soci.documenti.store', $socio) }}" enctype="multipart/form-data" id="form-documento" class="form-modale">
                @csrf
                <div x-data x-on:categoria-documento.window="$el.querySelector('select').value = $event.detail">
                    <x-campo name="categoria" label="Tipo di documento" tipo="select" required vuota="Scegli…" :opzioni="$categorie" />
                </div>
                <x-campo name="descrizione" label="Descrizione" aiuto="Obbligatoria per «Altro»." />
                <x-campo name="file" label="File" tipo="file" required accept=".pdf,.jpg,.jpeg,.png,image/*"
                         aiuto="PDF, JPG o PNG, massimo 5 MB. Da telefono puoi scattare una foto." />
            </form>
            <x-slot:piede>
                <button type="button" class="btn btn-ghost" @click="aperta = false">Annulla</button>
                <button type="submit" form="form-documento" class="btn btn-primary">Carica</button>
            </x-slot:piede>
        </x-modale>

        <x-modale nome="privacy-presenza" titolo="Consenso raccolto di persona">
            <p>Registra che {{ $socio->nome }} {{ $socio->cognome }}@if ($socio->isMinorenne()) (tramite il tutore)@endif ha preso visione dell'informativa privacy
               (versione {{ \App\Services\ProfiloEnte::versione('informativa') }}) e ha dato il consenso.
               Verranno salvate data e ora di adesso.</p>
            <form method="POST" action="{{ route('soci.privacy.presenza', $socio) }}" id="form-privacy-presenza">@csrf</form>
            <x-slot:piede>
                <button type="button" class="btn btn-ghost" @click="aperta = false">Annulla</button>
                <button type="submit" form="form-privacy-presenza" class="btn btn-primary">Registra consenso</button>
            </x-slot:piede>
        </x-modale>

        @if ($socio->foto_video_consenso)
            <x-modale nome="foto-revoca" titolo="Revoca del consenso a foto e video">
                <form method="POST" action="{{ route('soci.foto-video.revoca', $socio) }}" id="form-foto-revoca" class="form-modale">
                    @csrf
                    <x-campo name="revoca_il" label="Data della richiesta" tipo="date" required :value="now()" />
                    <x-campo name="revoca_modo" label="Come è arrivata" tipo="select" required vuota="Scegli…" :opzioni="\App\Models\Socio::MODI_REVOCA" />
                    <p class="form-help">Dopo la revoca non si pubblicano nuove immagini e si tolgono quelle già pubblicate (informativa, punto 7).</p>
                </form>
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" @click="aperta = false">Annulla</button>
                    <button type="submit" form="form-foto-revoca" class="btn btn-danger">Registra la revoca</button>
                </x-slot:piede>
            </x-modale>
        @endif

        <x-modale nome="privacy-cartacea" titolo="Modulo privacy firmato">
            <form method="POST" action="{{ route('soci.privacy.cartacea', $socio) }}" enctype="multipart/form-data" id="form-privacy-cartacea" class="form-modale">
                @csrf
                <x-campo name="data_firma" label="Data della firma" tipo="date" required :value="now()" />
                <x-campo name="modulo" label="Modulo firmato" tipo="file" required accept=".pdf,.jpg,.jpeg,.png,image/*"
                         aiuto="Scansione o foto del modulo, PDF/JPG/PNG, massimo 5 MB." />
            </form>
            <x-slot:piede>
                <button type="button" class="btn btn-ghost" @click="aperta = false">Annulla</button>
                <button type="submit" form="form-privacy-cartacea" class="btn btn-primary">Carica modulo</button>
            </x-slot:piede>
        </x-modale>
    @endif

    @if (auth()->user()->haRuolo('presidente'))
        @php $bagElimina = $errors->getBag('elimina'); @endphp
        <x-modale nome="elimina" titolo="Eliminare la scheda?" :aperta="$bagElimina->any()">
            <form method="POST" action="{{ route('soci.destroy', $socio) }}" id="form-elimina" class="form-modale">
                @csrf
                @method('DELETE')
                <p class="form-help">Solo per un inserimento sbagliato: la scheda di <strong>{{ $socio->cognome }} {{ $socio->nome }}</strong> sparisce dagli elenchi.
                    Si ripristina da Soci › Filtri › Stato «Schede eliminate». Non si elimina un socio con quote valide, movimenti o tessere: per chi esce si registra la cessazione.</p>
                <div @class(['form-field', 'has-error' => $bagElimina->has('motivo')])>
                    <label class="form-label" for="motivo-elimina">Motivazione<span class="obbligatorio" aria-hidden="true">*</span></label>
                    <textarea id="motivo-elimina" name="motivo" rows="3" required minlength="15" maxlength="1000" class="form-textarea"
                              aria-describedby="motivo-elimina-aiuto">{{ $bagElimina->any() ? old('motivo') : '' }}</textarea>
                    <div class="form-help" id="motivo-elimina-aiuto">Almeno 15 caratteri: resta nell'Audit Log.</div>
                    @foreach ($bagElimina->get('motivo') as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                </div>
            </form>
            <x-slot:piede>
                <button type="button" class="btn btn-ghost" @click="aperta = false">Annulla</button>
                <button type="submit" form="form-elimina" class="btn btn-danger">Elimina scheda</button>
            </x-slot:piede>
        </x-modale>
    @endif
@endpush

{{-- Dopo un errore di validazione si riapre la modale da cui proveniva --}}
@php
    $modaleConErrori = match (true) {
        $errors->hasAny(['motivo_cancellazione', 'data_cancellazione']) => 'cessazione',
        $errors->hasAny(['categoria', 'descrizione', 'file']) => 'documento',
        $errors->hasAny(['data_firma', 'modulo']) => 'privacy-cartacea',
        $errors->hasAny(['revoca_il', 'revoca_modo']) => 'foto-revoca',
        default => null,
    };
@endphp
@if ($modaleConErrori)
    @push('scripts')
        <script>document.addEventListener('alpine:initialized', () => window.dispatchEvent(new CustomEvent('apri-modale', { detail: @js($modaleConErrori) })));</script>
    @endpush
@endif
