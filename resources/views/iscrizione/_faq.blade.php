{{-- Domande frequenti, a destra del modulo (sotto, su telefono). Testi in resources/content/iscrizione/testi.php. --}}
<aside class="iscr-faq" aria-labelledby="faq-titolo">
    <h2 id="faq-titolo" class="iscr-occhiello">Domande frequenti</h2>
    @foreach ($testi['faq'] as $voce)
        <div class="iscr-faq-voce">
            <h3>{{ $voce[0] }}</h3>
            <p>{{ $voce[1] }}</p>
            @isset($voce[2])
                <p class="norma">{{ $voce[2] }}</p>
            @endisset
        </div>
    @endforeach
    <p class="iscr-faq-link"><a href="{{ route('informativa') }}" target="_blank" rel="noopener">Leggi l'informativa privacy</a> · <a href="{{ route('statuto') }}" target="_blank" rel="noopener">Statuto</a></p>
</aside>
