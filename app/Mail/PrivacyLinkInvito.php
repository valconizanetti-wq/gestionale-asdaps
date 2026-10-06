<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use App\Models\PrivacyLink;
use App\Models\Socio;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Link personale per l'informativa privacy e foto e video (tavola "Email — link personale per l'informativa"; privacy-link.md §3). */
class PrivacyLinkInvito extends Mailable
{
    use MittenteCompagnia;

    public function __construct(public Socio $socio, public PrivacyLink $link, private string $token, private bool $aggiornamento = false)
    {
    }

    public function envelope(): Envelope
    {
        $oggetto = match (true) {
            $this->aggiornamento => 'Informativa privacy aggiornata: conferma la lettura',
            $this->link->perTutore() => "Informativa privacy per {$this->socio->nome} {$this->socio->cognome}: conferma la lettura",
            default => 'Informativa privacy: conferma la lettura',
        };

        return new Envelope(subject: $oggetto, replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        $dest = $this->socio->destinatarioPrivacy();

        return new Content(markdown: 'emails.privacy.link', with: [
            'nome' => $this->link->perTutore() ? ($dest['nome'] ?? 'genitore') : trim("{$this->socio->nome} {$this->socio->cognome}"),
            'perTutore' => $this->link->perTutore(),
            'nomeSocio' => $this->socio->nome,
            'aggiornamento' => $this->aggiornamento,
            'versione' => \App\Services\ProfiloEnte::versione('informativa'),
            'url' => route('privacy-link.mostra', $this->token),
        ]);
    }
}
