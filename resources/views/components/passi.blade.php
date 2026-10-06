{{-- Passi della domanda di iscrizione. <x-passi :corrente="1" /> --}}
@props(['corrente' => 1, 'nomi' => ['Dati', 'Controlla e invia']])
<ol class="passi" aria-label="Passi della domanda">
    @foreach ($nomi as $i => $nome)
        @php $n = $i + 1; @endphp
        <li @class(['passo', 'fatto' => $n < $corrente, 'corrente' => $n === $corrente]) @if ($n === $corrente) aria-current="step" @endif>
            <span class="passo-num">@if ($n < $corrente)<x-icona nome="ok" width="14" height="14" /><span class="sr-only">Completato:</span>@else{{ $n }}@endif</span>
            <span>{{ $nome }}</span>
        </li>
    @endforeach
</ol>
