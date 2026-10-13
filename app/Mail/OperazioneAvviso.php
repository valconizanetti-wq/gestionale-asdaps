<?php

namespace App\Mail;

use App\Mail\Concerns\MittenteCompagnia;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/** Operazione delicata fatta da un altro utente (AvvisiOperazioni, v0.40.0). */
class OperazioneAvviso extends Mailable
{
    use MittenteCompagnia;

    public function __construct(
        public string $destinatario,
        public string $autore,
        /** @var list<array{logId: int, evento: string, dettaglio: string}> */
        public array $righe,
        public Carbon $quando,
        public string $url,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Gestionale: {$this->titolo()} ({$this->autore})", replyTo: $this->risposteA());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.utenti.avviso-operazione', with: ['titolo' => $this->titolo()]);
    }

    /** Titolo della prima riga, il resto è nel corpo. */
    public function titolo(): string
    {
        return self::titoloEvento($this->righe[0]['evento'] ?? '');
    }

    /** Titolo leggibile dell'evento: «scheda di un socio eliminata»… */
    public static function titoloEvento(string $evento): string
    {
        return match ($evento) {
            'IMPORT_SOCI' => 'soci importati da Excel',
            'UTENTE_RUOLI' => 'ruoli di un utente cambiati',
            'UTENTE_CREATO' => 'nuovo utente',
            'USER_UPDATED' => 'utente modificato',
            'USER_DELETED' => 'utente eliminato',
            'SOCIO_DELETED' => 'scheda di un socio eliminata',
            'SOCIO_RESTORED' => 'scheda di un socio ripristinata',
            'RINNOVO_UPDATED' => 'ricevuta di una quota annullata',
            'PRIMANOTA_DELETED' => 'movimento di Prima Nota eliminato',
            'PRIMA_NOTA_CORREZIONE_ANNO_CHIUSO' => 'correzione su un anno chiuso',
            'ANNO_CONTABILE_RIAPERTO' => 'anno contabile riaperto',
            'ANNOCONTABILE_UPDATED' => 'anno contabile chiuso o riaperto',
            'SALDI_APERTURA_MODIFICATI' => 'saldi di apertura cambiati',
            'PRIMANOTASCONTRINO_DELETED' => 'scontrino eliminato',
            default => 'operazione delicata',
        };
    }
}
