{{-- Dopo la registrazione della quota: il socio non ha una tessera CSEN valida, si propone di emetterla (v0.37.0). --}}
@if ($sug = session('tessera_suggerita'))
    <x-avviso tipo="info" titolo="Tessera CSEN.">
        {{ $sug['nome'] }} non ha una tessera valida (o scade a breve): senza tessera non ha l'assicurazione base.
        <a href="{{ route('tesseramento.index', ['emetti' => $sug['id']] + (request()->routeIs('soci.show') ? ['da' => 'socio'] : [])) }}">Emetti la tessera</a>
    </x-avviso>
@endif
