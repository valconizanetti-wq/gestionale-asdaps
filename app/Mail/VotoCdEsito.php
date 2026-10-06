<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Votazione chiusa: esito e voti a ogni consigliere; al Presidente, se serve, la richiesta di intervento (diniego da scrivere o parità da decidere). */
class VotoCdEsito extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public \App\Models\Domanda $domanda, private string $saluto, private bool $alPresidente = false, private ?string $destinatario = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->oggettoPer($this->destinatario, $this->alPresidente
            ? 'Consiglio Direttivo: domanda di ' . $this->domanda->nomeCognome() . ', serve il tuo intervento'
            : 'Consiglio Direttivo: esito della domanda di ' . $this->domanda->nomeCognome()), replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.voto-cd.esito', with: [
            'saluto' => $this->saluto,
            'alPresidente' => $this->alPresidente,
            'url' => route('domande.show', $this->domanda),
        ]);
    }
}
