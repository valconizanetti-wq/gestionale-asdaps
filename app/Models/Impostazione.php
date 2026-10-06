<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Impostazione extends Model
{
    protected $table = 'gl_impostazioni';

    protected $fillable = [
        'chiave',
        'valore',
        'tipo',
        'descrizione',
    ];

    /**
     * Legge un'impostazione per chiave, con cast automatico in base al
     * campo 'tipo' (string/boolean/integer/json). Restituisce $default
     * se la chiave non esiste.
     */
    public static function get(string $chiave, mixed $default = null): mixed
    {
        $riga = static::where('chiave', $chiave)->first();

        if (! $riga) {
            return $default;
        }

        return match ($riga->tipo) {
            'boolean' => filter_var($riga->valore, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $riga->valore,
            'json' => json_decode($riga->valore, true),
            default => $riga->valore,
        };
    }

    public static function set(string $chiave, mixed $valore, string $tipo = 'string', ?string $descrizione = null): self
    {
        $valoreSerializzato = $tipo === 'json' ? json_encode($valore) : (string) $valore;

        return static::updateOrCreate(
            ['chiave' => $chiave],
            ['valore' => $valoreSerializzato, 'tipo' => $tipo, 'descrizione' => $descrizione]
        );
    }
}
