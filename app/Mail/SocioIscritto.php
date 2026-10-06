<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Quota ricevuta: il nuovo socio è iscritto nel libro dei soci. */
class SocioIscritto extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public \App\Models\Domanda $domanda, public \App\Models\Socio $socio)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'La tua iscrizione alla Compagnia Arcieri è completa', replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.iscrizione.iscritto', with: [
            'saluto' => $this->domanda->genitore_nome ? explode(' ', $this->domanda->genitore_nome)[0] : $this->domanda->nome,
            'perMinore' => $this->domanda->tipo_socio === 'minorenne',
        ]);
    }
}
