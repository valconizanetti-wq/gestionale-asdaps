<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_email_log';

    protected $fillable = [
        'domanda_id',
        'socio_id',
        'tipo_email',
        'destinatario_email',
        'destinatario_nome',
        'oggetto',
        'esito',
        'errore',
    ];

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function domanda(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Domanda::class, 'domanda_id');
    }
}
