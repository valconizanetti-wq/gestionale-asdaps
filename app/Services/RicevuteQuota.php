<?php

namespace App\Services;

use App\Mail\RicevutaQuotaEmail;
use App\Models\Associazione;
use App\Models\Rinnovo;
use App\Support\InvioEmail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Ricevute delle quote: PDF (tavole RQ-4/RQ-6) e invio per email al socio,
 * al genitore per i minorenni (rinnovi-quota.md §3). Il PDF si rigenera dai
 * dati del rinnovo: un annullamento aggiunge il timbro «annullata».
 */
class RicevuteQuota
{
    public function pdf(Rinnovo $r): string
    {
        $r->loadMissing('socio');
        $periodo = ProfiloEnte::al($r->data_pagamento);

        return Pdf::loadView('pdf.ricevuta-quota', [
            'r' => $r,
            'periodo' => $periodo,
            'detraibile' => RinnoviQuota::detraibile($r),
            'tesoriere' => RinnoviQuota::tesoriere(),
            'cf' => Associazione::istanza()?->codice_fiscale ?: config('sito.piede.codice_fiscale'),
            'dataDocumento' => $r->data_pagamento,
        ])->setPaper('a4')->output();
    }

    /** Genera e salva il PDF (nome casuale, come gli altri documenti); restituisce il contenuto. */
    public function genera(Rinnovo $r): string
    {
        $pdf = $this->pdf($r);
        $percorso = "ricevute/{$r->anno_ricevuta}/" . bin2hex(random_bytes(16)) . '.pdf';
        Storage::disk('local')->put($percorso, $pdf);

        $precedente = $r->pdf_path;
        // Campi tecnici (file e stato dell'invio): senza audit, sarebbero solo rumore.
        Rinnovo::withoutEvents(fn () => $r->forceFill([
            'pdf_path' => $percorso,
            'ricevuta_stato' => $r->ricevuta_stato === 'non_generata' ? 'generata' : $r->ricevuta_stato,
        ])->save());
        if ($precedente && $precedente !== $percorso) {
            Storage::disk('local')->delete($precedente);
        }

        return $pdf;
    }

    /** Contenuto del PDF salvato; se manca lo rigenera. */
    public function contenuto(Rinnovo $r): string
    {
        return $r->pdf_path && Storage::disk('local')->exists($r->pdf_path)
            ? Storage::disk('local')->get($r->pdf_path)
            : $this->genera($r);
    }

    /** Destinatario: il socio, o il genitore se minorenne. null = nessuna email. */
    public static function destinatario(Rinnovo $r): ?array
    {
        return $r->socio?->destinatarioPrivacy();
    }

    /** Invia la ricevuta; aggiorna lo stato (inviata o errore). */
    public function invia(Rinnovo $r): bool
    {
        $dest = self::destinatario($r);
        if (! $dest) {
            return false;
        }
        $saluto = explode(' ', trim($dest['nome']))[0] ?: $dest['nome'];
        $ok = InvioEmail::invia(new RicevutaQuotaEmail($r, $this->contenuto($r), $saluto, $dest['destinatario'] === 'tutore'),
            $dest['email'], $dest['nome'], $r->isAnnullato() ? 'ricevuta_quota_annullata' : 'ricevuta_quota', null, $r->socio);

        if (! $r->isAnnullato()) {
            Rinnovo::withoutEvents(fn () => $r->forceFill($ok
                ? ['ricevuta_stato' => 'inviata', 'ricevuta_inviata_il' => now()]
                : ['ricevuta_stato' => 'errore'])->save());
        }

        return $ok;
    }
}
