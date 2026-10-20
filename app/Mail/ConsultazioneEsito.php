<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use App\Models\Consultazione;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Esito della consultazione telematica, o il suo ritiro, a ogni componente (v0.44.0, CT-3). */
class ConsultazioneEsito extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public Consultazione $c, private string $saluto)
    {
    }

    public function envelope(): Envelope
    {
        $cosa = $this->c->stato === 'ritirata' ? 'ritirata' : 'esito';

        return new Envelope(subject: "Consiglio Direttivo: consultazione {$cosa} · {$this->c->oggetto}", replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.organi.consultazione-esito', with: ['c' => $this->c, 'saluto' => $this->saluto]);
    }
}
