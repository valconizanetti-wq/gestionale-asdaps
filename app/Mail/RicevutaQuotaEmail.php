<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use App\Models\Rinnovo;
use App\Services\RinnoviQuota;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Ricevuta della quota (o del suo annullamento) in allegato. */
class RicevutaQuotaEmail extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public Rinnovo $r, private string $pdf, private string $saluto, private bool $perGenitore)
    {
    }

    public function envelope(): Envelope
    {
        $oggetto = $this->r->isAnnullato()
            ? "Ricevuta n. {$this->r->numero_ricevuta} annullata"
            : "Quota associativa {$this->r->anno}: ricevuta n. {$this->r->numero_ricevuta}";

        return new Envelope(subject: $oggetto, replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        $t = RinnoviQuota::tesoriere();

        return new Content(markdown: 'emails.quote.ricevuta', with: [
            'saluto' => $this->saluto,
            'perGenitore' => $this->perGenitore,
            'annullata' => $this->r->isAnnullato(),
            'firma' => $t ? "{$t}, Tesoriere" : 'Il Tesoriere',
        ]);
    }

    public function attachments(): array
    {
        $nome = 'Ricevuta-' . str_replace('/', '-', $this->r->numero_ricevuta) . '.pdf';

        return [Attachment::fromData(fn () => $this->pdf, $nome)->withMime('application/pdf')];
    }
}
