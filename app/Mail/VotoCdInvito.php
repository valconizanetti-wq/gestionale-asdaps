<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Link personale di voto al consigliere: invito all'apertura e sollecito al terzo giorno (tavola "Email — invito al voto e sollecito"). */
class VotoCdInvito extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public \App\Models\VotoCd $voto, private string $token, private bool $sollecito = false)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->oggettoPer($this->voto->consigliere_nome, $this->sollecito
            ? 'Consiglio Direttivo: la votazione su ' . $this->voto->domanda->nomeCognome() . ' è ancora aperta'
            : 'Consiglio Direttivo: domanda di adesione di ' . $this->voto->domanda->nomeCognome()), replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.voto-cd.invito', with: [
            'domanda' => $this->voto->domanda,
            'saluto' => $this->voto->nomeProprio(),
            'sollecito' => $this->sollecito,
            'url' => route('voto-cd.mostra', $this->token),
        ]);
    }
}
