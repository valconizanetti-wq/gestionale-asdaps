{{-- Quota ricevuta: iscrizione nel libro dei soci completata. --}}
<x-mail::message>
<p class="occhiello">Iscrizione</p>

# Iscrizione completata

Ciao {{ $saluto }},

abbiamo ricevuto la quota associativa: {{ $perMinore ? $socio->nome . ($socio->sesso === 'F' ? ' è iscritta' : ' è iscritto') : ($socio->sesso === 'F' ? 'sei iscritta' : 'sei iscritto') }} nel libro dei soci della Compagnia dal {{ $socio->data_prima_iscrizione?->format('d/m/Y') }}.

<x-mail::panel>
**Categoria**<br>{{ $socio->tipo === 'minorenne' ? 'Socio giovane' : 'Socio ordinario' }}

**Quota valida fino al**<br>{{ $socio->quota_scadenza?->format('d/m/Y') }}
</x-mail::panel>

Il rinnovo si versa ogni anno entro il 31 gennaio (statuto, art. 8). Per qualsiasi dubbio rispondi a questa email.

Benvenuti nella Compagnia, ti aspettiamo al campo!<br>
La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}
</x-mail::message>
