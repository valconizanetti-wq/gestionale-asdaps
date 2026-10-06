{{-- Icone di linea usate nell'app. Uso: <x-icona nome="mail" /> --}}
@props(['nome'])
<svg {{ $attributes->merge(['width' => 16, 'height' => 16]) }} fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
@switch($nome)
    @case('mail')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>@break
    @case('telefono')<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2"/>@break
    @case('avanti')<path d="M9 6l6 6-6 6"/>@break
    @case('indietro')<path d="M15 6l-6 6 6 6"/>@break
    @case('filtro')<path d="M4 6h16M7 12h10M10 18h4"/>@break
    @case('piu')<path d="M12 5v14M5 12h14"/>@break
    @case('matita')<path d="M4 20h4L18.5 9.5a2.8 2.8 0 00-4-4L4 16v4z"/>@break
    @case('cestino')<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>@break
    @case('download')<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>@break
    @case('documento')<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>@break
    @case('ripristina')<path d="M4 12a8 8 0 108-8 8 8 0 00-5.7 2.3L4 8.6M4 4v4.6h4.6"/>@break
    @case('lucchetto')<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>@break
    @case('info')<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.5"/>@break
    @case('avviso')<path d="M10.3 3.9L2.5 17.5A2 2 0 004.2 20.5h15.6a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/><path d="M12 9v4M12 16.5v.5"/>@break
    @case('ok')<path d="M5 12l5 5 9-10"/>@break
    @case('esci')<path d="M15 4h4v16h-4M10 17l5-5-5-5M15 12H3"/>@break
    @case('occhio')<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>@break
    @case('giu')<path d="M6 9l6 6 6-6"/>@break
    @case('menu')<path d="M4 7h16M4 12h16M4 17h16"/>@break
    @case('orologio')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
    @case('chiudi')<path d="M6 6l12 12M18 6L6 18"/>@break
    @default<circle cx="12" cy="12" r="9"/>
@endswitch
</svg>
