<?php

namespace App\Promemoria;

use Illuminate\Support\Carbon;

/**
 * Un destinatario calcolato da una funzione: chiave unica nel periodo (es.
 * "socio:12"), email (null = senza email, da avvisare a mano), nome di chi
 * riceve (il genitore per un minore), etichetta per gli elenchi e valori
 * dei segnaposti.
 *
 * Nelle funzioni «N giorni prima o dopo» (quando = relativa, v0.38.0) ogni
 * destinatario porta la sua data di riferimento (es. la scadenza della
 * polizza) e il suo periodo (di norma la data stessa, così il promemoria
 * riparte per la scadenza dell'anno dopo); $cosa dice di che cosa si tratta
 * nella pagina («Polizza RC», «3 tessere»).
 */
final class Destinatario
{
    /** @param array<string, string> $valori */
    public function __construct(
        public readonly string $chiave,
        public readonly ?int $socioId,
        public readonly ?string $email,
        public readonly string $nome,
        public readonly string $etichetta,
        public readonly array $valori,
        public readonly ?Carbon $data = null,
        public readonly ?string $periodo = null,
        public readonly ?string $cosa = null,
    ) {
    }
}
