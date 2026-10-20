<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Presenza di un membro a una seduta: in sede, in videoconferenza o assente (tavola CD-5). */
class SedutaPresenza extends Model
{
    public const PRESENZE = ['sede' => 'In sede', 'video' => 'In videoconferenza', 'assente' => 'Assente'];

    protected $table = 'gl_sedute_presenze';

    protected $fillable = ['seduta_id', 'socio_id', 'governance_cd_id', 'nome', 'carica', 'presidente', 'presenza', 'giustificato'];

    protected function casts(): array
    {
        return ['presidente' => 'boolean', 'giustificato' => 'boolean'];
    }

    public function seduta(): BelongsTo
    {
        return $this->belongsTo(Seduta::class, 'seduta_id');
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id')->withTrashed();
    }

    public function presente(): bool
    {
        return in_array($this->presenza, ['sede', 'video'], true);
    }
}
