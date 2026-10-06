<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Domanda accolta: quota, IBAN, causale e termine di versamento (Domanda::GIORNI_VERSAMENTO_QUOTA; testo rivisto da Paolo il 30/09/2026). */
class DomandaAccolta extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public \App\Models\Domanda $domanda)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'La tua domanda di adesione è stata accolta', replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.iscrizione.accolta', with: [
            'saluto' => $this->domanda->genitore_nome ? explode(' ', $this->domanda->genitore_nome)[0] : $this->domanda->nome,
            'perMinore' => $this->domanda->tipo_socio === 'minorenne',
            'associazione' => \App\Models\Associazione::istanza(),
            'iban' => \App\Models\Associazione::istanza()->iban ?: \App\Services\Quota::IBAN_SEGNAPOSTO,
            'causale' => \App\Services\Quota::causale($this->domanda),
        ]);
    }
}
