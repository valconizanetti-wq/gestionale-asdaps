{{--
    Intestazione delle email: l'immagine dell'intestazione del sito
    (public/images/sito/header-mobile.jpg), con indirizzo assoluto perché
    l'email si legge fuori dal sito. Tema "sito" (html/themes/sito.css).
--}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}">
<img src="{{ asset('images/sito/header-mobile.jpg') }}" alt="{{ \App\Services\ProfiloEnte::denominazione() }}" width="570">
</a>
</td>
</tr>
