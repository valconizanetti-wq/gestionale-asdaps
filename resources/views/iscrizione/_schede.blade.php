{{--
    Schede in testa al modulo: "1 Dati e quota", "2 Consensi e invio".
    Sono l'aspetto dei due passi, che restano pagine distinte controllate
    dal server (domande-iscrizione.md §3). Nel passo 2 la scheda 1 ha la
    spunta ed è un link per tornare ai dati. Parametro: $corrente (1 o 2).
--}}
<ol class="iscr-schede" aria-label="Passi della domanda">
    <li @class(['iscr-scheda', 'attiva' => $corrente === 1, 'fatta' => $corrente > 1]) @if ($corrente === 1) aria-current="step" @endif>
        @if ($corrente > 1)
            <a href="{{ route('iscrizione.dati') }}" class="iscr-scheda-corpo">
                <span class="iscr-scheda-num"><x-icona nome="ok" /><span class="sr-only">Completato:</span></span>
                <span class="iscr-scheda-nome">Dati e quota</span>
            </a>
        @else
            <span class="iscr-scheda-corpo"><span class="iscr-scheda-num">1</span><span class="iscr-scheda-nome">Dati e quota</span></span>
        @endif
    </li>
    <li @class(['iscr-scheda', 'attiva' => $corrente === 2]) @if ($corrente === 2) aria-current="step" @endif>
        <span class="iscr-scheda-corpo"><span class="iscr-scheda-num">2</span><span class="iscr-scheda-nome">Consensi e invio</span></span>
    </li>
</ol>
