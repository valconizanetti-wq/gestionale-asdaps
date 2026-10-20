{{--
    Passi numerati. <x-passi :corrente="1" /> (domanda di iscrizione)
    Anche per la nota di rimborso (v0.43.0), con i suoi nomi e l'etichetta:
    <x-passi :corrente="2" :nomi="['Nota · 14/02/2027', 'Firma', 'Pagamento']" etichetta="Passi della nota" />
--}}
@props(['corrente' => 1, 'nomi' => ['Dati', 'Controlla e invia'], 'etichetta' => 'Passi della domanda'])
<ol {{ $attributes->class('passi') }} aria-label="{{ $etichetta }}">
    @foreach ($nomi as $i => $nome)
        @php $n = $i + 1; @endphp
        <li @class(['passo', 'fatto' => $n < $corrente, 'corrente' => $n === $corrente]) @if ($n === $corrente) aria-current="step" @endif>
            <span class="passo-num">@if ($n < $corrente)<x-icona nome="ok" width="14" height="14" /><span class="sr-only">Completato:</span>@else{{ $n }}@endif</span>
            <span>{{ $nome }}</span>
        </li>
    @endforeach
</ol>
