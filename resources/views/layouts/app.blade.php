{{--
    Layout dell'area riservata (vedi ui.md).
    Regola: niente style="..." e niente <style> qui né nelle viste figlie.
--}}
@php
    $utente = auth()->user();

    // Sidebar: voci con rotta esistente, sezione accessibile al ruolo e,
    // se la voce lo chiede, modulo previsto dal periodo dell'ente in vigore.
    $menu = collect(config('navigazione'))
        ->map(function ($gruppo) use ($utente) {
            $gruppo['voci'] = collect($gruppo['voci'])
                ->filter(fn ($v) => Route::has($v['rotta']) && $utente->puoAccedere($v['sezione'])
                    && (! isset($v['se']) || \App\Services\ProfiloEnte::haModulo($v['se'])))
                ->map(fn ($v) => $v + [
                    'attiva' => request()->routeIs(\Illuminate\Support\Str::before($v['rotta'], '.') . '.*'),
                ]);
            return $gruppo;
        })
        ->filter(fn ($gruppo) => $gruppo['voci']->isNotEmpty());
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Gestionale') — Arcieri Castel Viscardo</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-arcieri.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body x-data="{ menu: false }">

<header class="topbar">
    <div class="topbar-brand">
        <a href="{{ route('dashboard') }}" class="topbar-logo" aria-label="Vai alla dashboard">
            <img src="{{ asset('images/logo-arcieri.png') }}" alt="">
        </a>
        <span class="topbar-nome">Arcieri <em>Castel Viscardo</em></span>
        <span class="topbar-sep"></span>
        <span class="topbar-app">Gestionale ASD</span>
        <span class="topbar-sep"></span>
        <span class="topbar-ragione">{{ \App\Services\ProfiloEnte::denominazione() }}</span>
    </div>

    <div class="topbar-user">
        <div class="topbar-user-testo">
            <div class="topbar-user-nome">{{ $utente->name }}</div>
            <div class="topbar-user-ruolo">{{ $utente->ruoliInBreve() }}</div>
        </div>
        <span class="topbar-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($utente->name, 0, 1)) }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="topbar-logout">Esci</button>
        </form>
        {{-- Su telefono il menu si apre da destra, dopo «Esci» (collaudo, 07/10/2026) --}}
        <button type="button" class="topbar-hamburger" aria-label="Apri il menu" :aria-expanded="menu.toString()" @click="menu = !menu">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
</header>

<div class="layout">
    <div class="sidebar-overlay" :class="{ 'open': menu }" @click="menu = false"></div>

    <nav class="sidebar" :class="{ 'open': menu }" aria-label="Menu principale">
        <div class="sidebar-anno">
            <div class="sidebar-anno-label">Anno sociale</div>
            {{-- Provvisorio: anno solare corrente. Da collegare all'anno
                 sociale/contabile quando si costruisce Prima Nota. --}}
            <div class="sidebar-anno-value">{{ now()->year }}</div>
        </div>

        <a href="{{ route('dashboard') }}" @class(['sidebar-item', 'active' => request()->routeIs('dashboard')])
           @if (request()->routeIs('dashboard')) aria-current="page" @endif>Dashboard</a>

        @foreach ($menu as $gruppo)
            @if ($gruppo['gruppo'])
                <div class="sidebar-gruppo">{{ $gruppo['gruppo'] }}</div>
            @endif
            @foreach ($gruppo['voci'] as $voce)
                <a href="{{ route($voce['rotta']) }}" @class(['sidebar-item', 'active' => $voce['attiva']])
                   @if ($voce['attiva']) aria-current="page" @endif>{{ $voce['label'] }}</a>
            @endforeach
        @endforeach
    </nav>

    <main class="main">
        @if (session('success'))
            <div class="alert alert-ok" role="status">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12l5 5 9-10"/></svg>
                <div>{{ session('success') }}</div>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-ko" role="alert">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8v5M12 16.5v.5"/><circle cx="12" cy="12" r="9"/></svg>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        @yield('content')

        <footer class="app-footer">{{ \App\Services\ProfiloEnte::firma() }}</footer>
    </main>
</div>

@stack('modals')
@stack('scripts')
</body>
</html>
