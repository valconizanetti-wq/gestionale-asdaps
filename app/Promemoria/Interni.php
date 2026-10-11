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

        return User::where('attivo', true)->whereIn('ruolo', $ruoli)->orderBy('id')->get()
            ->sortBy(fn (User $u) => array_search($u->ruolo, $ruoli, true))->values();
    }

    /** Nome breve per il saluto: il primo nome. */
    public static function saluto(User $u): string
    {
        return explode(' ', trim($u->name))[0] ?: $u->name;
    }
}
