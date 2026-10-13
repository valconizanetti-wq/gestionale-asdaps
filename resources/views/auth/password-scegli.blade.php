{{-- Scegli la password dal link per email (v0.40.0, tavola UT-3b). --}}
@include('auth._testa', ['titolo' => 'Scegli la password'])

    <h1 class="login-title">Scegli la password</h1>

    <form method="POST" action="{{ route('password.salva') }}" class="login-form" x-data="{ visibile: false }">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div @class(['form-field', 'has-error' => $errors->has('email')])>
            <label class="form-label" for="email">Email</label>
            <input class="form-input" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username"
                   @error('email') aria-describedby="email-errore" @enderror>
            @error('email')<div class="form-error" id="email-errore">{{ $message }}</div>@enderror
        </div>

        <div @class(['form-field', 'has-error' => $errors->has('password')])>
            <label class="form-label" for="password">Nuova password</label>
            <div class="form-input-wrap">
                <input class="form-input" id="password" name="password" type="password" :type="visibile ? 'text' : 'password'"
                       required minlength="{{ \App\Http\Controllers\PasswordController::MINIMO }}" autocomplete="new-password" aria-describedby="password-aiuto">
                <button type="button" class="form-input-azione" @click="visibile = !visibile" :aria-pressed="visibile.toString()"
                        :aria-label="visibile ? 'Nascondi password' : 'Mostra password'" aria-label="Mostra password" aria-controls="password">
                    <svg x-show="!visibile" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg x-show="visibile" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M3 3l18 18M10.6 5.1A10.7 10.7 0 0112 5c6.5 0 10 7 10 7a17.6 17.6 0 01-3.2 4.2M6.6 6.6A17.4 17.4 0 002 12s3.5 7 10 7a10 10 0 005.4-1.6M9.9 9.9a3 3 0 004.2 4.2"/></svg>
                </button>
            </div>
            <div class="form-help" id="password-aiuto">Almeno {{ \App\Http\Controllers\PasswordController::MINIMO }} caratteri. Una frase di tre o quattro parole va benissimo.</div>
            @error('password')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-field">
            <label class="form-label" for="password_confirmation">Ripeti la password</label>
            <input class="form-input" id="password_confirmation" name="password_confirmation" :type="visibile ? 'text' : 'password'" type="password" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary login-submit">Salva e accedi</button>
    </form>

    <p class="login-nota"><a href="{{ route('password.dimenticata') }}">Il link non funziona? Chiedine un altro</a></p>
</main>

</body>
</html>
