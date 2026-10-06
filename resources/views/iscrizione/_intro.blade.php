{{--
    "Come iscriversi" e "Perché associarsi" in cima alla pagina (tavola
    "Pagina di iscrizione"). Testi in resources/content/iscrizione/testi.php.
    Su desktop i due riquadri sono affiancati e sempre aperti; su telefono
    sono blocchi a fisarmonica (<details>) chiusi, per non allungare la
    pagina prima del modulo. "Come iscriversi" viene prima nel codice (ordine
    del telefono); su desktop il CSS mette "Perché associarsi" a sinistra.
--}}
@php
    // Su desktop i blocchi si aprono all'avvio e non si richiudono.
    $desktop = "window.matchMedia('(min-width: 769px)').matches";
@endphp
<div class="iscr-intro">
    <details class="iscr-blocco iscr-come" x-data x-init="if ({{ $desktop }}) $el.open = true">
        <summary class="iscr-blocco-testa" x-on:click="if ({{ $desktop }}) $event.preventDefault()">
            <span class="iscr-blocco-titoli">
                <span class="iscr-occhiello">{{ $testi['come']['occhiello'] }}</span>
                <span class="iscr-blocco-titolo">{{ $testi['come']['titolo'] }}</span>
            </span>
            <x-icona nome="giu" class="iscr-freccia" />
        </summary>
        <ol class="iscr-passi">
            @foreach ($testi['come']['passi'] as $i => [$titolo, $testo])
                <li><span class="iscr-passo-num">{{ $i + 1 }}</span><span><strong>{{ $titolo }}</strong>: {{ $testo }}</span></li>
            @endforeach
        </ol>
        <p class="iscr-contatti">Per informazioni: <a href="mailto:{{ config('sito.piede.email') }}">{{ config('sito.piede.email') }}</a></p>
    </details>

    <details class="iscr-blocco iscr-perche" x-data x-init="if ({{ $desktop }}) $el.open = true">
        <summary class="iscr-blocco-testa" x-on:click="if ({{ $desktop }}) $event.preventDefault()">
            <span class="iscr-blocco-titoli">
                <span class="iscr-occhiello">{{ $testi['perche']['occhiello'] }}</span>
                <span class="iscr-blocco-titolo">{{ $testi['perche']['titolo'] }}</span>
            </span>
            <x-icona nome="giu" class="iscr-freccia" />
        </summary>
        <ul class="iscr-vantaggi">
            @foreach ($testi['perche']['voci'] as [$titolo, $testo])
                <li><span class="iscr-stella" aria-hidden="true">✦</span><span><strong>{{ $titolo }}</strong>: {{ $testo }}</span></li>
            @endforeach
        </ul>
    </details>
</div>
