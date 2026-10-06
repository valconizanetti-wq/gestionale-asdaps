{{-- Pagina di accesso (vedi ui.md). Solo classi di app.css. --}}
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accedi — Arcieri Castel Viscardo</title>
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

    <h1 class="login-title">Accedi</h1>

    <form method="POST" action="{{ route('login.submit') }}" class="login-form">
        @csrf

        <div @class(['form-field', 'has-error' => $errors->has('email')])>
            <label class="form-label" for="email">Email</label>
            <input class="form-input" id="email" name="email" type="email"
                   value="{{ old('email') }}" required autofocus autocomplete="username"
                   @error('email') aria-describedby="email-errore" @enderror>
            @error('email')
                <div class="form-error" id="email-errore">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-field" x-data="{ visibile: false }">
            <label class="form-label" for="password">Password</label>
            <div class="form-input-wrap">
                <input class="form-input" id="password" name="password" type="password"
                       :type="visibile ? 'text' : 'password'"
                       required autocomplete="current-password">
                <button type="button" class="form-input-azione"
                        @click="visibile = !visibile"
                        :aria-pressed="visibile.toString()"
                        :aria-label="visibile ? 'Nascondi password' : 'Mostra password'"
                        aria-label="Mostra password" aria-controls="password">
                    <svg x-show="!visibile" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg x-show="visibile" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M3 3l18 18M10.6 5.1A10.7 10.7 0 0112 5c6.5 0 10 7 10 7a17.6 17.6 0 01-3.2 4.2M6.6 6.6A17.4 17.4 0 002 12s3.5 7 10 7a10 10 0 005.4-1.6M9.9 9.9a3 3 0 004.2 4.2"/></svg>
                </button>
            </div>
        </div>

        <label class="form-check">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            Ricordami su questo dispositivo
        </label>

        <button type="submit" class="btn btn-primary login-submit">Accedi</button>
    </form>

    <p class="login-nota">
        <a href="mailto:admin@arciericastelviscardo.it?subject=Password%20dimenticata%20-%20Gestionale%20ASD">Password dimenticata?</a>
    </p>
</main>

</body>
</html>
