<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprLogAccesso extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_gdpr_log_accessi';

    protected $fillable = [
        'user_id',
        'ip',
        'user_agent',
        'azione',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
