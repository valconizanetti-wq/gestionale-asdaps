<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Associazione extends Model
{
    protected $table = 'gl_associazione';

    protected $fillable = [
        'ragione_sociale',
        'nome_breve',
        'codice_fiscale',
        'forma_giuridica',
        'indirizzo',
        'cap',
        'citta',
        'provincia',
        'email',
        'email_pec',
        'telefono',
        'sito_web',
        'firma_comunicazioni',
        'logo_path',
        'firma_path',
        'firma_testo',
        'timbro_path',
        'banca_nome',
        'iban',
        'bic_swift',
        'runts_numero',
        'runts_data_iscrizione',
        'runts_sezione',
        'rasd_numero',
        'rasd_data_iscrizione',
        'rete_affiliazione_nome',
        'rete_affiliazione_codice',
        'rete_affiliazione_referente',
        'rete_affiliazione_scadenza',
        'anno_sociale_corrente',
    ];

    protected function casts(): array
    {
        return [
            'runts_data_iscrizione' => 'date',
            'rasd_data_iscrizione' => 'date',
            'rete_affiliazione_scadenza' => 'date',
        ];
    }

    public static function istanza(): static
    {
        return static::firstOrCreate(['id' => 1]);
    }

    public function hasLogo(): bool
    {
        return $this->logo_path && Storage::disk('local')->exists($this->logo_path);
    }

    public function hasFirmaImmagine(): bool
    {
        return $this->firma_path && Storage::disk('local')->exists($this->firma_path);
    }

    public function hasTimbro(): bool
    {
        return $this->timbro_path && Storage::disk('local')->exists($this->timbro_path);
    }

    public function logoBase64(): ?string
    {
        if (! $this->hasLogo()) return null;
        $content = Storage::disk('local')->get($this->logo_path);
        $mime    = Storage::disk('local')->mimeType($this->logo_path);
        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    public function firmaBase64(): ?string
    {
        if (! $this->hasFirmaImmagine()) return null;
        $content = Storage::disk('local')->get($this->firma_path);
        $mime    = Storage::disk('local')->mimeType($this->firma_path);
        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    public function timbroBase64(): ?string
    {
        if (! $this->hasTimbro()) return null;
        $content = Storage::disk('local')->get($this->timbro_path);
        $mime    = Storage::disk('local')->mimeType($this->timbro_path);
        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }
}
