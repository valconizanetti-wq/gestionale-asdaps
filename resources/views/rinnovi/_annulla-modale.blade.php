{{-- Finestra di annullamento di una ricevuta (tavola RQ-5), dalla scheda del socio e dall'elenco dei rinnovi. Variabile: $r. --}}
@use('App\Support\Importo')
@php $bag = $errors->getBag('annulla' . $r->id); @endphp
            <x-modale nome="annulla-{{ $r->id }}" :titolo="'Annullare la ricevuta ' . $r->numero_ricevuta . '?'" :aperta="$bag->any()">
                <form method="POST" action="{{ route('rinnovi.annulla', $r) }}" class="form-modale" id="form-annulla-{{ $r->id }}">
                    @csrf
                    <p class="form-help">Il numero resta nel registro come «annullata». Il movimento di {{ Importo::it($r->importo, true) }} esce dalla Prima Nota
                        (se l'anno è chiuso, la correzione resta nell'Audit Log). Il socio torna «da versare» per il {{ $r->anno }}.</p>
                    <div @class(['form-field', 'has-error' => $bag->has('motivo')])>
                        <label class="form-label" for="motivo-{{ $r->id }}">Motivazione<span class="obbligatorio" aria-hidden="true">*</span></label>
                        <textarea id="motivo-{{ $r->id }}" name="motivo" rows="3" required minlength="15" maxlength="1000" class="form-textarea"
                                  aria-describedby="motivo-{{ $r->id }}-aiuto">{{ $bag->any() ? old('motivo') : '' }}</textarea>
                        <div class="form-help" id="motivo-{{ $r->id }}-aiuto">Almeno 15 caratteri: resta nell'Audit Log.</div>
                        @foreach ($bag->get('motivo') as $msg)<div class="form-error">{{ $msg }}</div>@endforeach
                    </div>
                    <label class="spunta">
                        <input type="checkbox" name="avvisa" value="1">
                        <span><strong>Avvisa il socio per email</strong><span class="scelta-sub">Con la ricevuta annullata in allegato.</span></span>
                    </label>
                </form>
                <x-slot:piede>
                    <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Indietro</button>
                    <button type="submit" form="form-annulla-{{ $r->id }}" class="btn btn-danger">Annulla la ricevuta</button>
                </x-slot:piede>
            </x-modale>
