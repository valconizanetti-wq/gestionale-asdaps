{{--
    Layout delle pagine pubbliche (domanda di iscrizione, informativa,
    statuto). Intestazione e piè di pagina sono la copia di header.php e
    footer.php del tema del sito (astra-child), con le stesse classi e le
    stesse immagini: passando dal sito al modulo non cambia nulla (ui.md
    §3, "Tema del sito"). Menu, immagini e righe del piè di pagina:
    config/sito.php. La classe "tema-sito" sul body ridefinisce i token solo
    qui: l'area riservata resta com'è.
    Regola: niente style="..." e niente <style> (ui.md).
--}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Domanda di adesione') — Compagnia Arcieri Castel Viscardo</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-arcieri.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="tema-sito">

@php
    $sito = config('sito.url');
    $intestazione = config('sito.intestazione');
    $piede = config('sito.piede');
@endphp

<div class="page-wrap">

    <header class="mock-header-single" role="banner" x-data="{ menu: false }"
        x-on:click.outside="menu = false" x-on:keydown.escape.window="menu = false">
        <a href="{{ $sito }}/" class="header-logo-link">
            <picture>
                <source media="(min-width: 900px)" srcset="{{ asset($intestazione['desktop']) }}">
                <img src="{{ asset($intestazione['telefono']) }}" alt="{{ $intestazione['alt'] }}" class="header-logo-img">
            </picture>
        </a>
        <nav class="mock-nav" x-bind:class="{ 'nav-open': menu }" aria-label="Menu del sito">
            <ul class="mock-nav-list">
                @foreach (config('sito.menu') as $voce)
                    <li @class(['current-menu-item' => $voce['attiva'] ?? false])>
                        <a href="{{ $sito . $voce['percorso'] }}" @if ($voce['attiva'] ?? false) aria-current="page" @endif>{{ $voce['etichetta'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
        <button type="button" class="nav-toggle-btn" x-bind:class="{ 'is-active': menu }"
            x-on:click="menu = ! menu" x-bind:aria-expanded="menu" aria-expanded="false"
            x-bind:aria-label="menu ? 'Chiudi il menu' : 'Apri il menu'" aria-label="Apri il menu">
            <span class="nav-toggle-icon"></span>
        </button>
    </header>

    <main @class(['pubblico-main', 'pubblico-stretto' => View::hasSection('stretto')])>
        @yield('content')
    </main>

    <footer class="mock-footer">
        <p>
            &copy; {{ now()->year }} {{ \App\Services\ProfiloEnte::denominazione() }}
            <br>{{ $piede['email'] }} - C.F. {{ $piede['codice_fiscale'] }}
            <br><a href="{{ route('informativa') }}">Informativa privacy</a> · <a href="{{ route('statuto') }}">Statuto</a>
            <br><a href="{{ $sito . $piede['gestione_contabile'] }}" target="_blank" rel="noopener">Gestione contabile</a>
            <br><span class="footer-credit">{{ $piede['credito'] }}</span>
        </p>
    </footer>

</div>

@stack('scripts')
</body>
</html>
