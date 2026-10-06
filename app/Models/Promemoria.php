<?php

namespace App\Models;

use App\Observers\AuditObserver;
use App\Promemoria\Registro;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Promemoria: email automatica pianificata (promemoria.md, v0.36.0).
 * La funzione (App\Promemoria\Registro) decide destinatari, segnaposti,
 * periodo dell'invio e sezione dei permessi.
 */
#[ObservedBy([AuditObserver::class])]
class Promemoria extends Model
{
    public const MESI = [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto',
        'settembre', 'ottobre', 'novembre', 'dicembre'];

    protected $table = 'gl_promemoria';

    protected $fillable = ['funzione', 'nome', 'attivo', 'quando', 'giorno', 'mese', 'giorni', 'ora', 'parametri',
        'oggetto', 'testo', 'creato_da'];

    protected function casts(): array
    {
        return ['attivo' => 'boolean', 'parametri' => 'array', 'giorno' => 'integer', 'mese' => 'integer', 'giorni' => 'integer'];
    }

    public function invii(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PromemoriaInvio::class, 'promemoria_id');
    }

    public function funzioneOggetto(): ?\App\Promemoria\Funzione
    {
        return Registro::trova($this->funzione);
    }

    public function parametro(string $chiave, mixed $default = null): mixed
    {
        return $this->parametri[$chiave] ?? $default;
    }

    public function oraBreve(): string
    {
        return substr((string) $this->ora, 0, 5);
    }

    /** "Ogni anno il 1° febbraio, alle 9:00" */
    public function quandoTesto(): string
    {
        if ($this->quando === 'relativa') {
            $g = abs((int) $this->giorni);

            return $g . ' ' . ($g === 1 ? 'giorno' : 'giorni') . ($this->giorni < 0 ? ' prima' : ' dopo') . ', alle ' . ltrim($this->oraBreve(), '0');
        }
        $giorno = $this->giorno === 1 ? '1°' : (string) $this->giorno;

        return "Ogni anno il {$giorno} " . (self::MESI[$this->mese] ?? '?') . ', alle ' . ltrim($this->oraBreve(), '0');
    }

    /** "Inviato il 01/02/2027 a 6" oppure "Mai inviato" */
    public function ultimoInvioTesto(): string
    {
        $ultimo = $this->invii()->where('esito', '!=', 'prova')->max('inviato_il');
        if (! $ultimo) {
            return 'Mai inviato';
        }
        $giorno = \Illuminate\Support\Carbon::parse($ultimo)->toDateString();
        $n = $this->invii()->where('esito', 'inviato')->whereDate('inviato_il', $giorno)->count();

        return 'Ultimo invio il ' . \Illuminate\Support\Carbon::parse($ultimo)->format('d/m/Y') . ' · ' . $n . ' ' . ($n === 1 ? 'email' : 'email');
    }
}
