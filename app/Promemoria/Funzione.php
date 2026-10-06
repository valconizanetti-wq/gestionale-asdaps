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

    /** Promemoria pronti, creati la prima volta (seeder). @return list<array<string, mixed>> */
    public function predefiniti(): array;
}
