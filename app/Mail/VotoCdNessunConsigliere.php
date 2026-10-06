<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Al Presidente (o alla segreteria): è arrivata una domanda ma nel
 * gestionale non c'è nessun consigliere in carica, quindi la votazione non
 * si è aperta (collaudo A6, 29/09/2026). Si apre da sola, con il comando
 * orario, appena il CD è inserito.
 */
class VotoCdNessunConsigliere extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public \App\Models\Domanda $domanda)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Domanda di adesione: votazione del CD non aperta', replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.voto-cd.nessun-consigliere', with: [
            'url' => route('domande.show', $this->domanda),
        ]);
    }
}
