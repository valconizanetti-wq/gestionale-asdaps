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

    public function __construct(public \App\Models\Domanda $domanda, public \App\Models\Socio $socio,
        public ?\App\Models\Rinnovo $rinnovo = null, private ?string $pdf = null)
    {
    }

    /** Ricevuta della prima quota (v0.36.0). */
    public function attachments(): array
    {
        if (! $this->rinnovo || $this->pdf === null) {
            return [];
        }

        return [\Illuminate\Mail\Mailables\Attachment::fromData(fn () => $this->pdf,
            'Ricevuta-' . str_replace('/', '-', (string) $this->rinnovo->numero_ricevuta) . '.pdf')->withMime('application/pdf')];
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
            'ricevuta' => $this->pdf !== null ? $this->rinnovo?->numero_ricevuta : null,
        ]);
    }
}
