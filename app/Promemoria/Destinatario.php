<?php

namespace App\Promemoria;

/**
 * Un destinatario calcolato da una funzione: chiave unica nel periodo (es.
 * "socio:12"), email (null = senza email, da avvisare a mano), nome di chi
 * riceve (il genitore per un minore), etichetta per gli elenchi e valori
 * dei segnaposti.
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
    ) {
    }
}
