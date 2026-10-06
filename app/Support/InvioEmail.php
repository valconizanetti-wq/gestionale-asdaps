<?php

namespace App\Support;

use App\Models\Domanda;
use App\Models\EmailLog;
use App\Models\Socio;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Invio di un'email con registrazione in gl_email_log, esito compreso.
 * Un invio fallito non blocca l'operazione che lo ha chiesto (la domanda
 * resta salvata): l'errore resta nel registro degli invii e nel log.
 *
 * Nel registro non si scrive l'oggetto dell'email, che può contenere il
 * nominativo; il tipo basta a capire di quale email si tratta.
 */
class InvioEmail
{
    public static function invia(Mailable $email, string $indirizzo, ?string $nome, string $tipo, ?Domanda $domanda = null, ?Socio $socio = null): bool
    {
        $esito = 'inviata';
        $errore = null;

        try {
            Mail::to($indirizzo, $nome)->send($email);
        } catch (\Throwable $e) {
            $esito = 'fallita';
            // Il messaggio d'errore può contenere l'indirizzo: nel registro
            // solo la classe e un estratto, per intero nel log applicativo.
            $errore = mb_substr(class_basename($e) . ': ' . $e->getMessage(), 0, 500);
            Log::error("Invio email {$tipo} fallito" . ($domanda ? " (domanda {$domanda->id})" : '') . ($socio ? " (socio {$socio->id})" : '') . ': ' . $e->getMessage());
        }

        try {
            EmailLog::create([
                'domanda_id' => $domanda?->id,
                'socio_id' => $socio?->id,
                'tipo_email' => $tipo,
                'destinatario_email' => $indirizzo,
                'destinatario_nome' => $nome,
                'oggetto' => null,
                'esito' => $esito,
                'errore' => $errore,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Registro invii non aggiornato ({$tipo}): " . $e->getMessage());
        }

        return $esito === 'inviata';
    }
}
