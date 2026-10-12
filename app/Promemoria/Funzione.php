<?php

namespace App\Promemoria;

use App\Models\Promemoria;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Una funzione del gestionale che può avere promemoria (promemoria.md §2).
 * Per aggiungerne una: una classe in App\Promemoria\Funzioni e una riga in
 * Registro::FUNZIONI.
 */
interface Funzione
{
    public function chiave(): string;

    public function nome(): string;

    /** Chi riceve, in parole (pagina Promemoria). */
    public function descrizione(): string;

    /** Sezione dei permessi: chi la modifica, modifica i promemoria. */
    public function sezione(): string;

    /** Modi ammessi: 'data_fissa' e/o 'relativa'. */
    public function quandoAmmessi(): array;

    /** @return array<string, array{label: string, opzioni: array<string, string>, aiuto?: string}> */
    public function parametri(): array;

    /** @return array<string, string> segnaposto => descrizione */
    public function segnaposti(): array;

    /** Periodo dell'invio: un destinatario riceve lo stesso promemoria una volta per periodo. */
    public function periodo(Promemoria $p, Carbon $quando): string;

    /** @return Collection<int, Destinatario> */
    public function destinatari(Promemoria $p, Carbon $quando): Collection;

    /**
     * Valori d'esempio dei segnaposti, per l'anteprima e «Invia una prova a
     * me» quando oggi non c'è nessun destinatario (collaudo M11, v0.38.1):
     * mai i segnaposti tra parentesi quadre. Dati inventati, con il saluto di
     * chi riceve la prova.
     *
     * @return array<string, string>
     */
    public function esempio(Promemoria $p, Carbon $quando, \App\Models\User $u): array;

    /** Promemoria pronti, creati la prima volta (seeder). @return list<array<string, mixed>> */
    public function predefiniti(): array;
}
