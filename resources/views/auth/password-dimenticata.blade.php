{{-- Password dimenticata (v0.40.0, tavola UT-3b): chiede l'email e manda il link. Risposta sempre uguale. --}}
@include('auth._testa', ['titolo' => 'Password dimenticata'])

    <h1 class="login-title">Password dimenticata</h1>

    @if (session('success'))
        <x-avviso tipo="ok">{{ session('success') }}</x-avviso>
    @endif

    <form method="POST" action="{{ route('password.invia') }}" class="login-form">
        @csrf
        <p class="form-help">Scrivi l'email con cui entri nel gestionale: riceverai un link per scegliere una nuova password, valido 24 ore.</p>
        <div @class(['form-field', 'has-error' => $errors->has('email')])>
            <label class="form-label" for="email">Email</label>
            <input class="form-input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   @error('email') aria-describedby="email-errore" @enderror>
            @error('email')<div class="form-error" id="email-errore">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary login-submit">Mandami il link</button>
    </form>

    <p class="login-nota"><a href="{{ route('login') }}">Torna all'accesso</a></p>
</main>

</body>
</html>
