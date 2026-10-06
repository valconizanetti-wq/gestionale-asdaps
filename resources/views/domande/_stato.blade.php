{{-- Badge dello stato di una domanda, con "Iscritta", "D'ufficio" e "Prova" quando servono. --}}
@if ($domanda->socio_id)
    <x-badge-stato dominio="domanda" valore="iscritta" />
@else
    <x-badge-stato dominio="domanda" :valore="$domanda->stato" />
    @if ($domanda->isApprovata())
        <x-badge variante="warn">Quota da versare</x-badge>
    @endif
@endif
@if ($domanda->silenzio_assenso)
    <x-badge variante="info">D'ufficio</x-badge>
@endif
@if ($domanda->is_test)
    <x-badge variante="neutral">Prova</x-badge>
@endif
