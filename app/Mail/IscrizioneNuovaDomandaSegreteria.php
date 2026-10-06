<?php

namespace App\Mail;

use App\Models\Domanda;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Avviso alla casella dell'Associazione: nuova domanda presentata.
 * Nessun dato personale oltre al nominativo: il resto si legge nel gestionale.
 */
class IscrizioneNuovaDomandaSegreteria extends Mailable
{
    public function __construct(public Domanda $domanda)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nuova domanda di adesione');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.iscrizione.segreteria');
    }
}
