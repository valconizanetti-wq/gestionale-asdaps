{{-- Mostra l'avviso di sola lettura se il ruolo corrente la ha per la sezione. --}}
@props(['sezione'])
@if (auth()->user()?->soloLettura($sezione))
    <div class="alert alert-info" role="status">
        <x-icona nome="lucchetto" />
        <div><strong>Sola lettura.</strong> Il tuo ruolo può consultare questa sezione ma non modificarla.</div>
    </div>
@endif
