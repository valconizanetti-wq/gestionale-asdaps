<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use App\Models\Seduta;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/**
 * Convocazione della riunione del Consiglio Direttivo (v0.44.0, tavola
 * CD-5), la sua prova a chi la manda e l'avviso di rinvio. I documenti non
 * si allegano: si leggono nella scheda della riunione, nel gestionale.
 */
class ConvocazioneSeduta extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public Seduta $seduta, private string $nome, private Collection $documenti, private bool $prova = false, private ?string $motivoRinvio = null)
    {
    }

    public function envelope(): Envelope
    {
        $data = $this->seduta->data->locale('it')->isoFormat('D MMMM YYYY');
        $oggetto = $this->motivoRinvio !== null
            ? "Consiglio Direttivo: la riunione del {$data} è rinviata"
            : ($this->seduta->urgente ? 'Convocazione urgente' : 'Convocazione') . " del Consiglio Direttivo, {$data}";

        return new Envelope(subject: ($this->prova ? '[PROVA] ' : '') . $this->oggettoPer($this->nome, $oggetto), replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.organi.convocazione', with: [
            's' => $this->seduta,
            'saluto' => strtok($this->nome, ' ') ?: $this->nome,
            'documenti' => $this->documenti,
            'prova' => $this->prova,
            'motivoRinvio' => $this->motivoRinvio,
            'url' => route('verbali.riunioni.show', $this->seduta),
        ]);
    }
}
