{{-- Testa comune delle pagine di accesso e password (v0.40.0). $titolo = <title>. --}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $titolo }} — Arcieri Castel Viscardo</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-arcieri.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">

<main class="login-card">
    <div class="login-brand">
        <img src="{{ asset('images/logo-arcieri.png') }}" alt="Logo Arcieri Castel Viscardo" class="login-logo">
        <div>
            <div class="login-nome">Arcieri <em>Castel Viscardo</em></div>
            <div class="login-app">Gestionale ASD</div>
        </div>
    </div>
