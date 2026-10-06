<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Diniego motivato con il diritto di appello all'Assemblea entro 30 giorni (statuto art. 6.5, tavola "Email — diniego motivato"). */
class DomandaDiniego extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public \App\Models\Domanda $domanda)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Esito della domanda di adesione', replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.iscrizione.diniego', with: [
            'destinatario' => $this->domanda->nomeDestinatario(),
            'perMinore' => $this->domanda->tipo_socio === 'minorenne',
            'appelloEntro' => now()->addDays(30),
            'firma' => \App\Services\VotazioneCd::emailPresidente()['nome'] ?? null,
        ]);
    }
}
