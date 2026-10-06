<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Al Presidente: il diniego motivato non è ancora partito e il termine di 10 giorni si avvicina (art. 6.5). */
class PromemoriaDiniego extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public \App\Models\Domanda $domanda)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Promemoria: diniego da comunicare a ' . $this->domanda->nomeCognome(), replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.voto-cd.promemoria-diniego', with: [
            'entro' => $this->domanda->scadenzaDiniego(),
            'url' => route('domande.show', $this->domanda),
        ]);
    }
}
