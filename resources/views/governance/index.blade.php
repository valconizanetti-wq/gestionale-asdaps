@extends('layouts.app')

@section('title', 'Consiglio Direttivo')

@use('App\Services\ConsiglioDirettivo')

{{--
    Consiglio Direttivo (v0.42.0, tavole CD-1…CD-4, analisi-collaudo-O.md
    §15). Dai membri in carica nascono gli utenti del gestionale; le regole
    sono in App\Services\ConsiglioDirettivo, qui le stesse servono solo a
    proporre le cariche libere e a spiegare il passaggio.
--}}
@section('content')
    @php
        $modale = session('modale');
        $cariche = collect(ConsiglioDirettivo::CARICHE)->map(fn ($c) => ['label' => $c[0], 'ruoli' => $c[1]])->all();
        $libera = fn (string $c) => ! array_intersect(ConsiglioDirettivo::ruoliCarica($c), array_keys($occupati));
        $gestisce = fn (?string $c) => $c === 'presidente' ? $puoPresidente : $puoModificare;
        $liberePerNuovo = collect(ConsiglioDirettivo::CARICHE)->filter(fn ($c, $k) => $libera($k) && $gestisce($k))->map(fn ($c) => $c[0])->all();
    @endphp

    <x-page-header titolo="Consiglio" evidenza="Direttivo" sottotitolo="Chi è nel CD e con quale carica · dai membri nascono gli utenti del gestionale">
        @if ($puoModificare && $membri->count() < ConsiglioDirettivo::MASSIMO)
            <x-slot:azioni>
                <button type="button" class="btn btn-primary" x-data x-on:click="$dispatch('apri-modale', 'aggiungi')"><x-icona nome="piu" width="14" height="14" /> Aggiungi un membro</button>
            </x-slot:azioni>
        @endif
    </x-page-header>

    <x-sola-lettura sezione="governance_cd" />

    @if ($membri->count() < ConsiglioDirettivo::MINIMO)
        <x-avviso tipo="warn" titolo="Il CD ha {{ $membri->count() === 1 ? 'un membro' : $membri->count() . ' membri' }}.">Lo statuto ne chiede da tre a cinque, compreso il Presidente <span class="norma">Statuto, art. 15.1</span>.</x-avviso>
    @endif
    @if ($membri->isNotEmpty() && ! isset($occupati['presidente']))
        <x-avviso tipo="warn" titolo="Manca il Presidente.">Dai la carica di Presidente a un membro con la matita sulla sua riga.</x-avviso>
    @endif

    <x-card senza-padding titolo="In carica">
        @if ($membri->isEmpty())
            <x-vuoto>Nessun membro. Aggiungi i membri eletti dall'Assemblea: per ognuno nasce l'utente del gestionale.</x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive">
                    <thead>
                        <tr>
                            <th scope="col">Socio</th>
                            <th scope="col">Carica</th>
                            <th scope="col">Dal</th>
                            <th scope="col">Utente del gestionale</th>
                            @if ($puoModificare || $puoPresidente)<th scope="col"><span class="sr-only">Azioni</span></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($membri as $m)
                            @php
                                $u = $utenti[$m->socio_id] ?? null;
                                $presidente = $m->carica_codice === 'presidente';
                                $cambia = ! $presidente && ! $m->socio?->trashed() && ($puoModificare || $puoPresidente) && ($m->socio_id !== $io || $puoPresidente);
                                $togli = ! $presidente && $gestisce($m->carica_codice) && $m->socio_id !== $io;
                            @endphp
                            <tr>
                                <td class="td-primario">
                                    @if (auth()->user()->puoAccedere('soci') && $m->socio && ! $m->socio->trashed())<a href="{{ route('soci.show', $m->socio_id) }}">{{ $m->nomeSocio() }}</a>@else{{ $m->nomeSocio() }}@endif
                                    @if ($m->socio?->trashed())<x-badge variante="ko">Scheda eliminata</x-badge>@endif
                                    <span class="td-sub">{{ $m->socio?->email ?: 'senza email' }}</span>
                                </td>
                                <td data-label="Carica"><x-badge :variante="$m->carica_codice === 'consigliere' ? 'neutral' : 'info'">{{ ConsiglioDirettivo::etichetta($m->carica_codice) }}</x-badge></td>
                                <td data-label="Dal">{{ $m->data_inizio_mandato?->format('d/m/Y') }}@if ($m->delibera_assemblea)<span class="td-sub">{{ $m->delibera_assemblea }}</span>@endif</td>
                                <td data-label="Utente">
                                    @if (! $u)
                                        <x-badge variante="warn">Nessun utente</x-badge>
                                        {{-- OB4: CD di prova o email del socio diversa da quella dell'utente --}}
                                        @if ($gestisce($m->carica_codice) && $m->socio && ! $m->socio->trashed())
                                            <form method="POST" action="{{ route('governance.utente', $m) }}" class="cd-utente">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-ghost">Crea o collega l'utente</button>
                                            </form>
                                        @endif
                                    @elseif (! $u->attivo)
                                        <x-badge variante="neutral">Disattivato</x-badge>
                                    @else
                                        Attivo · {{ $u->ultimo_accesso ? 'ultimo accesso ' . $u->ultimo_accesso->format('d/m/Y') : 'mai entrato' }}@if ($u->haRuolo('amministratore'))<span class="td-sub">anche Amministratore</span>@endif
                                    @endif
                                </td>
                                @if ($puoModificare || $puoPresidente)
                                    <td class="td-azioni">
                                        @if ($cambia)
                                            <button type="button" class="action-btn" aria-label="Cambia la carica di {{ $m->nomeSocio() }}"
                                                    x-data x-on:click="$dispatch('apri-modale', 'carica-{{ $m->id }}')"><x-icona nome="matita" /></button>
                                        @endif
                                        @if ($togli)
                                            <button type="button" class="action-btn" aria-label="Togli {{ $m->nomeSocio() }} dal CD"
                                                    x-data x-on:click="$dispatch('apri-modale', 'togli-{{ $m->id }}')"><x-icona nome="esci" /></button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if ($usciti->isNotEmpty())
        <x-card titolo="Usciti dal CD" class="cd-usciti">
            <ul class="cd-elenco">
                @foreach ($usciti as $m)
                    <li>{{ $m->nomeSocio() }} · {{ ConsiglioDirettivo::etichetta($m->carica_codice) }}
                        dal {{ $m->data_inizio_mandato?->format('d/m/Y') }} al {{ $m->data_fine_mandato?->format('d/m/Y') }}
                        · {{ mb_strtolower(ConsiglioDirettivo::MOTIVI_FINE[$m->motivo_fine] ?? 'uscito') }}</li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card titolo="Come funziona" class="pn-aiuto">
        <p>Il socio che entra nel CD diventa utente del gestionale con il ruolo della sua carica: se ha già un utente (stessa email) si usa quello, altrimenti gli arriva il link per scegliere la password.</p>
        <p>Cariche uniche: Presidente, Vicepresidente, Segretario, Tesoriere. Segretario e Tesoriere possono essere la stessa persona <span class="norma">Statuto, art. 15.2</span>.</p>
        <p>Chi esce dal CD perde i ruoli del CD; se non è anche Amministratore, il suo utente si disattiva. Il Presidente non si toglie: si passa a un altro con la matita sulla riga di chi lo riceve. La carica di Presidente la gestiscono il Presidente e l'Amministratore.</p>
    </x-card>

    @push('modals')
        @if ($puoModificare)
            <x-modale nome="aggiungi" titolo="Aggiungi un membro del CD" :aperta="$modale === 'aggiungi'">
                @if (! $liberePerNuovo)
                    <p>Non ci sono cariche libere che puoi assegnare.</p>
                @else
                    <form method="POST" action="{{ route('governance.store') }}" id="form-aggiungi" class="form-grid"
                          x-data="{ socio: @js((string) old('socio_id', '')), email: @js($soci->mapWithKeys(fn ($s) => [(string) $s->id => $s->email])), carica: @js(old('carica', array_key_last($liberePerNuovo))), etichette: @js($liberePerNuovo) }">
                        @csrf
                        <x-campo name="socio_id" label="Socio" tipo="select" required class="ut-intero" vuota="Scegli dal libro soci…" x-model="socio"
                                 :opzioni="$soci->mapWithKeys(fn ($s) => [$s->id => $s->cognome . ' ' . $s->nome . ($s->data_nascita ? ' · ' . ($s->sesso === 'F' ? 'nata' : 'nato') . ' il ' . $s->data_nascita->format('d/m/Y') : '')])->all()"
                                 aiuto="Soci attivi e maggiorenni non ancora nel CD, in ordine di cognome." />
                        <x-campo name="carica" label="Carica" tipo="select" required :opzioni="$liberePerNuovo" x-model="carica"
                                 :value="old('carica', array_key_last($liberePerNuovo))"
                                 :aiuto="count($liberePerNuovo) < count(ConsiglioDirettivo::CARICHE) ? 'Solo le cariche libere: per le altre usa la matita sul membro che la lascia o la riceve.' : null" />
                        <x-campo name="dal" label="In carica dal" tipo="date" required :value="old('dal', now()->format('Y-m-d'))" />
                        @if ($verbaliNomina->isNotEmpty())
                            <x-campo name="verbale_nomina_id" label="Verbale della nomina" tipo="select" class="ut-intero" vuota="Nessuno"
                                     :opzioni="$verbaliNomina->mapWithKeys(fn ($v) => [$v->id => $v->codice() . ' del ' . $v->data_seduta->format('d/m/Y') . ' — ' . \Illuminate\Support\Str::limit($v->titolo, 60)])->all()"
                                     aiuto="Facoltativo: il verbale definitivo dell'Assemblea o del CD da cui viene la nomina." />
                        @endif
                        <x-campo name="nominato_da" label="Nominato da" class="ut-intero" :value="old('nominato_da')" placeholder="Assemblea del …" aiuto="Facoltativo: assemblea o delibera del CD. Con il verbale scelto, si scrive da solo." />
                        <div class="ut-intero"><x-spunta name="link" label="Mandagli il link per entrare nel gestionale" :checked="! old() || old('link')" /></div>
                        <p class="form-help ut-intero" x-show="socio" x-cloak>
                            <span x-show="email[socio]">Email del socio: <strong x-text="email[socio]"></strong>. Diventerà utente con il ruolo <span x-text="etichette[carica]"></span>.</span>
                            <span class="testo-ko" x-show="! email[socio]">Il socio non ha l'email nella scheda: aggiungila prima.</span>
                        </p>
                    </form>
                @endif
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                    @if ($liberePerNuovo)<button type="submit" form="form-aggiungi" class="btn btn-primary">Aggiungi al CD</button>@endif
                </x-slot:piede>
            </x-modale>
        @endif

        @foreach ($membri as $m)
            @continue($m->carica_codice === 'presidente')
            @php
                $nome = $m->nomeSocio();
                $possibili = collect($cariche)->except($m->carica_codice)->filter(fn ($c, $k) => $gestisce($k))->map(fn ($c) => $c['label'])->all();
            @endphp
            @if ($puoModificare || $puoPresidente)
                <x-modale :nome="'carica-' . $m->id" :titolo="'Cambiare la carica di ' . $nome" :aperta="$modale === 'carica-' . $m->id">
                    <form method="POST" action="{{ route('governance.carica', $m) }}" id="form-carica-{{ $m->id }}" class="form-grid"
                          x-data="{ carica: @js(old('nuova_carica', array_key_first($possibili))), cariche: @js($cariche), occupati: @js($occupati), io: {{ $m->id }},
                                    get cedono() { const v = []; (this.cariche[this.carica]?.ruoli || []).forEach(r => { const o = this.occupati[r]; if (o && o.id !== this.io && ! v.some(x => x.id === o.id)) v.push({ ...o, ruolo: this.cariche[r]?.label || r }); }); return v; } }">
                        @csrf @method('PUT')
                        <x-campo name="nuova_carica" label="Nuova carica" tipo="select" required class="ut-intero" :opzioni="$possibili" x-model="carica" />
                        <div class="ut-intero" x-show="cedono.length" x-cloak>
                            <div class="alert alert-warn" role="status"><x-icona nome="avviso" />
                                <div><template x-for="c in cedono" :key="c.id"><span><strong x-text="'La carica di ' + c.ruolo + ' è di ' + c.nome + '. '"></strong></span></template>Si passa in un'unica operazione.</div>
                            </div>
                        </div>
                        <div class="ut-intero" x-show="cedono.length" x-cloak>
                            <x-campo name="altri" label="Chi la lascia resta nel CD come" tipo="select" :opzioni="['consigliere' => 'Consigliere', 'esce' => 'Esce dal CD']" :value="old('altri', 'consigliere')"
                                     aiuto="Se aveva anche un'altra carica (es. Segretario e Tesoriere), tiene quella." />
                        </div>
                        <x-campo name="dal_carica" label="Dal" tipo="date" required :value="old('dal_carica', now()->format('Y-m-d'))" />
                        <x-campo name="motivazione" label="Motivazione" tipo="textarea" required rows="2" class="ut-intero" :value="old('motivazione')" placeholder="Es. elezione del nuovo Presidente, Assemblea del …" />
                        <p class="form-help ut-intero">I ruoli degli utenti si aggiornano di conseguenza. Va nell'Audit Log e gli altri del CD ricevono l'avviso.</p>
                    </form>
                    <x-slot:piede>
                        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                        <button type="submit" form="form-carica-{{ $m->id }}" class="btn btn-primary">Cambia carica</button>
                    </x-slot:piede>
                </x-modale>
            @endif
            @if ($gestisce($m->carica_codice) && $m->socio_id !== $io)
                @php $u = $utenti[$m->socio_id] ?? null; @endphp
                <x-modale :nome="'togli-' . $m->id" :titolo="'Togliere ' . $nome . ' dal CD?'" :aperta="$modale === 'togli-' . $m->id">
                    <form method="POST" action="{{ route('governance.togli', $m) }}" id="form-togli-{{ $m->id }}" class="form-grid" x-data="{ motivo: @js(old('motivo', '')) }">
                        @csrf @method('PUT')
                        <x-campo name="al" label="Fuori dal CD dal" tipo="date" required :value="old('al', now()->format('Y-m-d'))" />
                        <x-campo name="motivo" label="Motivo" tipo="select" required vuota="Scegli…" :opzioni="ConsiglioDirettivo::MOTIVI_FINE" :value="old('motivo')" x-model="motivo" />
                        <x-campo name="nota" label="Note" tipo="textarea" rows="2" class="ut-intero" :value="old('nota')" placeholder="Es. lettera di dimissioni del …" obbligatorio-se="motivo === 'altro'"
                                 aiuto="Obbligatorie con il motivo «Altro»." />
                    </form>
                    <p>@if ($u && $u->haRuolo('amministratore'))Il suo utente perde i ruoli del CD e resta Amministratore.@else Il suo utente perde il ruolo di {{ ConsiglioDirettivo::etichetta($m->carica_codice) }} e <strong>si disattiva</strong>.@endif Resta nello storico del CD.</p>
                    <x-slot:piede>
                        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                        <button type="submit" form="form-togli-{{ $m->id }}" class="btn btn-danger">Togli dal CD</button>
                    </x-slot:piede>
                </x-modale>
            @endif
        @endforeach
    @endpush
@endsection
