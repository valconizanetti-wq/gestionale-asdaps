<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Domanda presentata: ricevuta PDF allegata, trasmessa al CD, link per annullarla (tavola "Email — domanda ricevuta"). */
class IscrizioneRicevuta extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public \App\Models\Domanda $domanda, private string $token, private ?string $pdf = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Abbiamo ricevuto la tua domanda di adesione', replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.iscrizione.ricevuta', with: [
            'saluto' => $this->domanda->genitore_nome ? explode(' ', $this->domanda->genitore_nome)[0] : $this->domanda->nome,
            'perMinore' => $this->domanda->tipo_socio === 'minorenne',
            'urlAnnulla' => route('iscrizione.annulla', $this->token),
            'conRicevuta' => $this->pdf !== null,
        ]);
    }

    public function attachments(): array
    {
        if ($this->pdf === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->pdf, "domanda-adesione-{$this->domanda->id}.pdf")->withMime('application/pdf'),
        ];
    }
}
