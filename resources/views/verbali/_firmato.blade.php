{{--
    Carica la copia firmata digitalmente (v0.44.0, tavola VB-3; V2, V14):
    PDF con la firma dentro (PAdES) o .p7m (CAdES). Con due o più firme
    PAdES basta il file; con una firma sola, o con un .p7m, chi carica
    dichiara chi ha firmato. Una firma sola si accetta solo se è del
    segretario verbalizzante (resta l'avviso). Sostituzione: con il motivo.
    Variabili: $v, $sostituzione.
--}}
<form method="POST" action="{{ route('verbali.firmato', $v) }}" enctype="multipart/form-data" class="form-modale"
      x-data="{ inviato: false }" x-on:submit="inviato = true">
    @csrf
    @if ($errors->firmato->any())
        <x-avviso tipo="ko" titolo="Copia non caricata.">{{ $errors->firmato->first() }}</x-avviso>
    @endif
    <div @class(['form-field', 'has-error' => $errors->firmato->has('firmato')])>
        <label class="form-label" for="firmato-{{ $sostituzione ? 's' : 'n' }}">File firmato<span class="obbligatorio" aria-hidden="true">*</span></label>
        <input id="firmato-{{ $sostituzione ? 's' : 'n' }}" name="firmato" type="file" class="form-input" accept=".pdf,.p7m,application/pdf,application/pkcs7-mime" required
               aria-describedby="firmato-{{ $sostituzione ? 's' : 'n' }}-aiuto">
        <div class="form-help" id="firmato-{{ $sostituzione ? 's' : 'n' }}-aiuto">PDF firmato digitalmente (PAdES) o file .p7m (CAdES), fino a 10 MB. Una scansione firmata a mano non basta.</div>
    </div>
    <fieldset @class(['scelta-gruppo', 'has-error' => $errors->firmato->has('dichiarazione')])>
        <legend>Chi ha firmato</legend>
        <p class="form-help">Con due firme digitali nel PDF il gestionale le conta da solo; scegli qui solo per un .p7m o se c'è una firma sola.</p>
        <div class="scelta-opzioni">
            <label class="scelta">
                <input type="radio" name="dichiarazione" value="entrambi" @checked(old('dichiarazione') === 'entrambi')>
                <span class="scelta-testo">Il presidente e il segretario <span class="scelta-sub">tutte e due le firme</span></span>
            </label>
            <label class="scelta">
                <input type="radio" name="dichiarazione" value="segretario" @checked(old('dichiarazione') === 'segretario')>
                <span class="scelta-testo">Solo il segretario verbalizzante <span class="scelta-sub">manca la firma del presidente</span></span>
            </label>
        </div>
    </fieldset>
    @if ($sostituzione)
        <div @class(['form-field', 'has-error' => $errors->firmato->has('motivo')])>
            <label class="form-label" for="motivo-sostituzione">Motivo della sostituzione<span class="obbligatorio" aria-hidden="true">*</span></label>
            <textarea id="motivo-sostituzione" name="motivo" rows="2" class="form-textarea" minlength="15" maxlength="500" required>{{ $errors->firmato->any() ? old('motivo') : '' }}</textarea>
            <div class="form-help">Almeno 15 caratteri: resta nell'Audit Log. La copia di prima resta conservata.</div>
        </div>
    @endif
    <div class="form-actions form-actions-fine">
        <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">{{ $sostituzione ? 'Sostituisci la copia firmata' : 'Carica la copia firmata' }}</button>
    </div>
</form>
