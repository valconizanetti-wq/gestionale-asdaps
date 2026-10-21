<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Avviso del cambio dell'email per entrare (v0.44.1, analisi-soci-utenti.md
 * §2.3): parte al vecchio e al nuovo indirizzo quando cambia l'email nella
 * scheda di un socio che ha l'accesso al gestionale.
 */
class EmailAccessoCambiata extends Mailable
{
    use MittenteCompagnia;

    /**
     * @param bool $vecchio l'email va al vecchio indirizzo (non vale più per entrare)
     */
    public function __construct(
        public string $nome,
        public string $nuova,
        public bool $vecchio,
        public ?string $da,
        public string $url,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->oggettoPer($this->nome, 'Gestionale: è cambiata l\'email per entrare'), replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.utenti.email-cambiata', with: [
            'nuovaMascherata' => self::maschera($this->nuova),
        ]);
    }

    /** «pa…@gmail.com»: al vecchio indirizzo non si scrive per intero quello nuovo. */
    public static function maschera(string $email): string
    {
        [$locale, $dominio] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($locale, 0, 2) . '…@' . $dominio;
    }
}
