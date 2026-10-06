<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lettera extends Model
{
    protected $table = 'gl_lettere';

    protected $fillable = [
        'anno',
        'numero',
        'numero_protocollo',
        'tipo',
        'destinatario_id',
        'destinatario_nome',
        'destinatario_indirizzo',
        'destinatario_email',
        'data_lettera',
        'oggetto',
        'testo_html',
        'firmatario_id',
        'firma_testo_snapshot',
        'stato',
        'protocollata_il',
        'protocollata_da',
        'pdf_path',
        'creata_da',
    ];

    protected function casts(): array
    {
        return [
            'data_lettera' => 'date',
            'protocollata_il' => 'datetime',
        ];
    }

    public function destinatario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Destinatario::class, 'destinatario_id');
    }

    public function firmatario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'firmatario_id');
    }

    public function protocollataDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'protocollata_da');
    }

    public function creataDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'creata_da');
    }

    public function invii(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LetteraInvio::class, 'lettera_id');
    }
}
