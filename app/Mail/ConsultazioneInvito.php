<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use App\Models\ConsultazioneVoto;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Link personale per votare una consultazione telematica, e sollecito (v0.44.0, CT-2). */
class ConsultazioneInvito extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public ConsultazioneVoto $voto, private string $token, private bool $sollecito = false)
    {
    }

    public function envelope(): Envelope
    {
        $c = $this->voto->consultazione;

        return new Envelope(subject: $this->oggettoPer($this->voto->nome, ($this->sollecito
            ? 'Consiglio Direttivo: la consultazione è ancora aperta · '
            : 'Consiglio Direttivo: consultazione telematica · ') . $c->oggetto), replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.organi.consultazione-invito', with: [
            'c' => $this->voto->consultazione,
            'saluto' => $this->voto->nomeProprio(),
            'sollecito' => $this->sollecito,
            'url' => route('consultazione.mostra', $this->token),
        ]);
    }
}
