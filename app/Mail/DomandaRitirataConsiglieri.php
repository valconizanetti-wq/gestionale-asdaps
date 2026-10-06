<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Ai consiglieri: il richiedente ha ritirato la domanda durante la votazione; il link di voto non vale più. */
class DomandaRitirataConsiglieri extends Mailable
{
    use MittenteCompagnia;

    public function __construct(private string $richiedente, private string $saluto, private ?string $destinatario = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->oggettoPer($this->destinatario, 'Consiglio Direttivo: domanda di adesione ritirata'), replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.voto-cd.ritirata', with: [
            'richiedente' => $this->richiedente,
            'saluto' => $this->saluto,
        ]);
    }
}
