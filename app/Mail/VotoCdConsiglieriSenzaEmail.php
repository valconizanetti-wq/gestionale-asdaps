<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Al Presidente: consiglieri in carica senza email, che non hanno ricevuto il link (contano nel quorum come non votanti). */
class VotoCdConsiglieriSenzaEmail extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public \App\Models\Domanda $domanda, private array $nomi)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Consiglio Direttivo: consiglieri senza indirizzo email', replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.voto-cd.senza-email', with: [
            'nomi' => $this->nomi,
            'url' => route('domande.show', $this->domanda),
        ]);
    }
}
