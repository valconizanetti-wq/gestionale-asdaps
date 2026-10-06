<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un invio di un promemoria a un destinatario (o una prova). Registro, senza audit: è già nel registro email. */
class PromemoriaInvio extends Model
{
    public const UPDATED_AT = null;
    public const CREATED_AT = null;

    protected $table = 'gl_promemoria_invii';

    protected $fillable = ['promemoria_id', 'periodo', 'socio_id', 'chiave_destinatario', 'email', 'esito', 'inviato_da', 'inviato_il'];

    protected function casts(): array
    {
        return ['inviato_il' => 'datetime'];
    }

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id')->withTrashed();
    }
}
