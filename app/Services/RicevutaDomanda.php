<?php

namespace App\Services;

use App\Http\Requests\IscrizioneDatiRequest;
use App\Models\Associazione;
use App\Models\Domanda;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Ricevuta PDF della domanda confermata: sostituisce il modulo cartaceo
 * CSEN (dati, dichiarazioni) e riporta le prove della firma elettronica
 * semplice (data e ora di invio e di conferma, IP, versioni e impronte dei
 * testi accettati). Salvata sul disco privato e inviata al richiedente.
 */
class RicevutaDomanda
{
    /** Genera, salva e restituisce il contenuto del PDF. */
    public function genera(Domanda $domanda, bool $conConferma = false): string
    {
        $confermataIl = now();
        $pdf = Pdf::loadView('pdf.ricevuta-domanda', [
            'domanda' => $domanda,
            'associazione' => Associazione::istanza(),
            'documenti' => IscrizioneDatiRequest::DOCUMENTI,
            'confermataIl' => $confermataIl,
            // Solo le domande del vecchio flusso (prima della v0.30.0) hanno
            // la conferma dal link dell'email.
            'conConferma' => $conConferma,
            // Periodo dell'ente alla data della domanda (carta intestata).
            'dataDocumento' => $domanda->created_at,
            'statutoHash' => $domanda->statuto_versione ? Documenti::hash('statuto', $domanda->statuto_versione) : null,
            // Variabili del layout comune dei PDF (pdf/layout.blade.php)
            'docTitolo' => 'Domanda di adesione',
            'docSottotitolo' => 'N. ' . $domanda->id,
            'docData' => $confermataIl,
            'showPagination' => true,
        ])->setPaper('a4')->output();

        // Nome casuale, come per i documenti dei soci (soci.md): il percorso
        // non rivela a chi appartiene il file.
        $percorso = "domande/{$domanda->id}/" . bin2hex(random_bytes(16)) . '.pdf';
        Storage::disk('local')->put($percorso, $pdf);

        $precedente = $domanda->ricevuta_path;
        $domanda->forceFill(['ricevuta_path' => $percorso, 'ricevuta_at' => now()])->save();
        if ($precedente && $precedente !== $percorso) {
            Storage::disk('local')->delete($precedente);
        }

        return $pdf;
    }
}
