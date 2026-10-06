<?php

namespace App\Mail\Concerns;

use App\Models\Associazione;
use Illuminate\Mail\Mailables\Address;

/**
 * Risposte all'email dell'associazione (Reply-To), per tutte le email ai
 * richiedenti e ai consiglieri. Il mittente resta quello del .env, sul
 * dominio della casella che invia (DMARC).
 */
trait MittenteCompagnia
{
    /** @return list<Address> */
    protected function risposteA(): array
    {
        $associazione = Associazione::istanza();

        return $associazione?->email
            ? [new Address($associazione->email, \App\Services\ProfiloEnte::denominazione())]
            : [];
    }

    /**
     * Oggetto con il nome del consigliere in testa ("Andrea Papini ·
     * Consiglio Direttivo: …") per le email che partono a ogni consigliere:
     * si vede a chi è arrivata ciascuna anche con una casella sola
     * (collaudo B, 29/09/2026).
     */
    protected function oggettoPer(?string $destinatario, string $oggetto): string
    {
        $destinatario = trim((string) $destinatario);

        return $destinatario !== '' ? "{$destinatario} · {$oggetto}" : $oggetto;
    }
}
