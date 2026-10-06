<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Anomalia trovata dalla verifica mensile dell'Audit Log (tavola "Audit Log 5"): solo numeri di riga e conteggi. */
class AuditAnomalia extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public array $dati, public int $anno)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Audit Log: anomalia nella catena di integrità (anno {$this->anno})", replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.audit.anomalia', with: [
            'url' => route('audit-log.verifica', ['anno' => $this->anno]),
        ]);
    }
}
