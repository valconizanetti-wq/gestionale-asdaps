{{-- Domanda accolta (tavola "Email — domanda accolta"; testo rivisto da Paolo il 30/09/2026). --}}
<x-mail::message>
<p class="occhiello">Domanda di adesione</p>

# Domanda accolta

Ciao {{ $saluto }},

il Consiglio Direttivo ha accolto il {{ ($domanda->decisione_presidente_il ?? $domanda->esito_il)?->format('d/m/Y') }} la domanda di adesione {{ $perMinore ? 'per ' . $domanda->nomeCognome() : 'che hai presentato sul nostro sito' }}.
L'iscrizione sarà effettiva, con la copertura assicurativa e il conseguente accesso alle strutture della Compagnia, solo una volta versata la quota sociale.

<x-mail::panel>
**Importo**<br>{{ number_format((float) $domanda->quota_importo, 2, ',', '.') }} €

**Termine**<br>bonifico da disporre entro il {{ $domanda->quota_scadenza?->format('d/m/Y') }} ({{ \App\Models\Domanda::GIORNI_VERSAMENTO_QUOTA }} giorni, statuto art. 6.6)

**Bonifico a**<br>{{ \App\Services\ProfiloEnte::denominazione() }}

**IBAN**<br>{{ $iban }}

**Causale**<br>{{ $causale }}
</x-mail::panel>

@if ($domanda->quota_promo)
La quota vale per il {{ $domanda->quota_anno }}: versandola adesso l'iscrizione vale da subito e fino al 31 dicembre {{ $domanda->quota_anno }}.

@endif
Quando la segreteria riceverà il versamento, {{ $perMinore ? $domanda->nome . ($domanda->sesso === 'F' ? ' sarà iscritta' : ' sarà iscritto') : ($domanda->sesso === 'F' ? 'sarai iscritta' : 'sarai iscritto') }} nel libro dei soci e riceverai la conferma.

Per qualsiasi dubbio rispondi a questa email.

Ti aspettiamo al campo!

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}
</x-mail::message>
