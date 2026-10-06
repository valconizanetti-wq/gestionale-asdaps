<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email di un promemoria (promemoria.md): oggetto e testo già composti, nello stile delle altre email. */
class PromemoriaEmail extends Mailable
{
    use MittenteCompagnia;

    public function __construct(private string $oggetto, private string $testo)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->oggetto, replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.promemoria.messaggio', with: ['oggetto' => $this->oggetto, 'testo' => $this->testo]);
    }
}
