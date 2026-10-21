<?php

namespace App\Console\Commands;

use App\Services\Azzeramento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Azzeramento dei dati di prova prima dell'apertura (v0.41.0,
 * analisi-azzeramento.md, tavole AZ-1…AZ-3).
 *
 * Senza --esegui è una prova a vuoto: dice cosa farebbe e non cambia niente.
 *
 * --tieni=<email> (v0.44.1, analisi-soci-utenti.md, D-SU2): l'utente che
 * resta, unico socio e unico utente, Presidente nel Consiglio Direttivo.
 * Tutti gli altri utenti si cancellano con i soci di prova.
 */
class Azzera extends Command
{
    protected $signature = 'gestionale:azzera
        {--esegui : Azzera davvero (dopo la copia di sicurezza e la conferma)}
        {--tieni= : Email dell\'utente che resta: unico socio e unico utente, Presidente nel CD}
        {--force : Salta la domanda di conferma (la copia di sicurezza si fa comunque)}';

    protected $description = 'Azzera i dati di prova prima dell\'apertura; senza --esegui è una prova a vuoto';

    public function handle(Azzeramento $azz): int
    {
        $esegui = (bool) $this->option('esegui');

        $this->newLine();
        $this->line('<options=bold>AZZERAMENTO DEI DATI DI PROVA</>' . ($esegui ? '' : ' — prova a vuoto (non cambia niente)'));
        $this->line('Database: ' . config('database.connections.' . config('database.default') . '.database') . ' · ' . now()->format('d/m/Y H:i'));
        $this->newLine();

        if ($motivo = $azz->motivoRifiuto()) {
            $this->error('Non si può azzerare: ' . $motivo);

            return self::FAILURE;
        }

        $tieni = $azz->daTenere($this->option('tieni'));
        if (is_string($tieni) && ($esegui || filled($this->option('tieni')))) {
            $this->error('Non si può azzerare: ' . $tieni);
            $this->candidati($azz);

            return self::FAILURE;
        }
        $tieni = is_string($tieni) ? null : $tieni;

        $r = $azz->riepilogo($tieni);
        $this->riepilogo($r);

        if (! $esegui) {
            $this->newLine();
            if (! $tieni) {
                $this->warn("Manca --tieni=<email>: l'utente che resta, unico socio e unico utente, Presidente nel CD.");
                $this->candidati($azz);
            }
            $this->line('Per azzerare davvero:  <options=bold>php artisan gestionale:azzera --tieni=' . ($tieni?->email ?? '<email>') . ' --esegui</>');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('ATTENZIONE: i dati elencati sopra si cancellano. Prima si fa una copia del database.');
        if (! $this->option('force') && $this->ask('Scrivi AZZERA per continuare') !== 'AZZERA') {
            $this->info('Niente fatto: conferma non data.');

            return self::FAILURE;
        }

        $copia = $azz->copiaDiSicurezza();
        $this->line('  ✔ Copia di sicurezza: storage/app/private/' . $copia . ' (' . self::peso((int) \Illuminate\Support\Facades\Storage::disk('local')->size($copia)) . ')');

        $giaGiu = app()->isDownForMaintenance();
        if (! $giaGiu) {
            Artisan::call('down', ['--retry' => 60]);
            $this->line('  ✔ Gestionale in manutenzione');
        }

        try {
            $e = $azz->esegui($copia, $tieni->email);
        } catch (\Throwable $ex) {
            $this->error('Azzeramento interrotto: ' . $ex->getMessage());
            $this->line("La copia di sicurezza è in storage/app/private/{$copia}: si ripristina con phpMyAdmin › Importa (dopo averla decompressa).");

            return self::FAILURE;
        } finally {
            if (! $giaGiu) {
                Artisan::call('up');
            }
        }

        $this->line('  ✔ Tabelle svuotate: ' . number_format($e['righe'], 0, ',', '.') . " righe in {$e['tabelle']} tabelle");
        $this->line("  ✔ File cancellati: {$e['file']} (" . self::peso($e['byte']) . ')');
        $this->line('  ✔ Impostazioni rimesse, scadenze pronte alla prossima data');
        $this->line("  ✔ Resta solo {$e['tenuto']}: socio n. 1, utente n. 1, Presidente nel Consiglio Direttivo");
        $this->line("  ✔ Audit Log ripartito: riga n. {$e['audit_id']} «Azzeramento dei dati di prova»");
        if (! $giaGiu) {
            $this->line('  ✔ Gestionale di nuovo in linea');
        }
        $this->newLine();
        $this->info('Fatto. Ora: importazione dei soci (Soci › Importa da Excel), CD vero (Consiglio Direttivo), tessere dal file del CSEN, saldi di apertura (Prima Nota › Anni contabili).');
        $this->line('Si entra con l\'utente tenuto e la sua password di prima (le sessioni aperte sono chiuse).');
        $this->line('La copia di sicurezza contiene i dati di prova: cancellala quando hai controllato che va tutto bene.');

        return self::SUCCESS;
    }

    private function riepilogo(array $r): void
    {
        $this->line('Si svuotano (righe):');
        $vuote = 0;
        foreach ($r['svuota'] as $t => $n) {
            if ($n === 0) {
                $vuote++;
                continue;
            }
            $this->line('  ' . str_pad((Azzeramento::ETICHETTE[$t] ?? $t) . ' ', 38, '.') . ' ' . str_pad((string) $n, 6, ' ', STR_PAD_LEFT));
        }
        if ($r['scadenze_a_mano']) {
            $this->line('  ' . str_pad('Scadenze create a mano ', 38, '.') . ' ' . str_pad((string) $r['scadenze_a_mano'], 6, ' ', STR_PAD_LEFT));
        }
        if ($r['utenti'] || $r['utenti_eliminati']) {
            $this->line('  ' . str_pad('Utenti ', 38, '.') . ' ' . str_pad((string) (count($r['utenti']) + $r['utenti_eliminati']), 6, ' ', STR_PAD_LEFT)
                . ($r['utenti'] ? ': ' . implode('; ', $r['utenti']) : '') . ($r['utenti_eliminati'] ? " (e {$r['utenti_eliminati']} già eliminati)" : ''));
        }
        $this->line("  Altre {$vuote} tabelle già vuote (moduli non ancora in uso)");

        $this->newLine();
        $this->line('Si tengono:');
        if ($r['tieni']) {
            $this->line("  Il socio e l'utente {$r['tieni']}: unico socio, unico utente, Presidente nel CD");
        }
        $this->line('  Profilo dell\'ente e periodi, categorie della Prima Nota, tipi di tessera, impostazioni');
        $this->line("  Promemoria ({$r['promemoria']}): i testi restano come sono");
        if ($r['scadenze_pronte']) {
            $this->line('  Scadenze pronte, data rimessa: ' . implode('; ', array_map(
                fn ($s) => $s['titolo'] . ' ' . ($s['prima'] === $s['dopo'] ? $s['dopo'] : "{$s['prima']} → {$s['dopo']}"), $r['scadenze_pronte'])));
        }

        $this->newLine();
        $this->line("File da cancellare: {$r['file']['numero']} (" . self::peso($r['file']['byte']) . ') in ' . implode(', ', Azzeramento::CARTELLE));
        if ($r['cartelle_sconosciute']) {
            $this->warn('Cartelle che il comando non conosce (restano): ' . implode(', ', $r['cartelle_sconosciute']));
        }
        $this->line('Impostazioni: iscrizioni chiuse, modalità test spenta, verifica dell\'Audit Log da rifare.');
    }

    private function candidati(Azzeramento $azz): void
    {
        $c = $azz->candidati();
        $this->line($c ? 'Utenti che si possono tenere: ' . implode('; ', $c) : 'Nessun utente collegato a un socio attivo: collegane uno dalla pagina Consiglio Direttivo.');
    }

    private static function peso(int $byte): string
    {
        return $byte >= 1048576 ? number_format($byte / 1048576, 1, ',', '.') . ' MB' : number_format($byte / 1024, 0, ',', '.') . ' kB';
    }
}
