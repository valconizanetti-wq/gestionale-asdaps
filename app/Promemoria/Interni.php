<?php

namespace App\Promemoria;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Destinatari interni dei promemoria (v0.38.0, tavola PR-3): gli utenti del
 * gestionale con un ruolo, non i soci. Parametro «destinatari» comune alle
 * funzioni «Scadenze dell'associazione» e «Tessere in scadenza».
 */
final class Interni
{
    public const SCELTE = [
        'presidente_tesoriere' => 'Presidente e Tesoriere',
        'tesoriere' => 'Solo il Tesoriere',
        'presidente' => 'Solo il Presidente',
        'tutti' => 'Presidente, Tesoriere e Segretario',
    ];

    private const RUOLI = [
        'presidente_tesoriere' => ['presidente', 'tesoriere'],
        'tesoriere' => ['tesoriere'],
        'presidente' => ['presidente'],
        'tutti' => ['presidente', 'tesoriere', 'segretario'],
    ];

    /** @return array{label: string, opzioni: array<string, string>, aiuto: string} */
    public static function parametro(string $aiuto): array
    {
        return ['label' => 'Destinatari', 'opzioni' => self::SCELTE, 'aiuto' => $aiuto];
    }

    /** Utenti attivi con i ruoli della scelta, Presidente per primo. @return Collection<int, User> */
    public static function utenti(?string $scelta): Collection
    {
        $ruoli = self::RUOLI[$scelta] ?? self::RUOLI['presidente_tesoriere'];

        // Una riga per persona anche con più ruoli fra quelli scelti
        // (analisi-ruoli-utenti.md §3.6); prima chi ha il ruolo che viene
        // prima nella scelta, a parità l'ordine di creazione.
        return User::conRuolo(...$ruoli)->where('attivo', true)->orderBy('id')->get()
            ->sortBy(fn (User $u) => min(array_map(
                fn ($c) => ($i = array_search($c, $ruoli, true)) === false ? PHP_INT_MAX : $i,
                $u->codiciRuoli() ?: ['?']
            )))->values();
    }

    /**
     * Data d'esempio per un promemoria «N giorni prima o dopo»: quella che lo
     * farebbe partire oggi (15 giorni prima → tra 15 giorni).
     */
    public static function dataEsempio(\App\Models\Promemoria $p, \Illuminate\Support\Carbon $quando): \Illuminate\Support\Carbon
    {
        return $quando->copy()->startOfDay()->subDays((int) ($p->giorni ?? 0));
    }

    /** Nome breve per il saluto: il primo nome. */
    public static function saluto(User $u): string
    {
        return explode(' ', trim($u->name))[0] ?: $u->name;
    }
}
