<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('sito.url', config('app.url'))">
            {{ \App\Services\ProfiloEnte::denominazione() }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            {{ \App\Services\ProfiloEnte::denominazione() }} · C.F. {{ config('sito.piede.codice_fiscale') }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
