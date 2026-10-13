<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Link monouso per scegliere la password del gestionale (v0.40.0, tavola UT-3). */
class LinkPassword extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public string $nome, public string $url)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Gestionale: scegli la tua password', replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.utenti.link-password', with: [
            'ore' => intdiv((int) config('auth.passwords.users.expire', 1440), 60),
        ]);
    }
}
