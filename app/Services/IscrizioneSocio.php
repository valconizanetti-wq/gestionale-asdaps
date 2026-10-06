<?php

namespace App\Services;

use App\Mail\SocioIscritto;
use App\Models\Domanda;
use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\Rinnovo;
use App\Models\Socio;
use App\Models\User;
use App\Support\InvioEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Quota versata: iscrivi nel libro soci" (statuto v1.0 art. 6.6,
 * domande-iscrizione.md §3). In una sola transazione:
 * - crea il socio copiando anagrafica, residenza, contatti, tutore (per un
 *   minore) e privacy (metodo online, data e IP della domanda, versione
 *   dell'informativa);
 * - registra la quota: rinnovo dell'anno sociale coperto e movimento di
 *   Prima Nota (entrata, categoria "Quote associative"), collegati;
 * - collega la domanda al socio.
 * Poi, fuori dalla transazione, l'email di conferma al nuovo socio.
 */
class IscrizioneSocio
{
    public const METODI = ['bonifico' => 'Bonifico', 'contante' => 'Contanti', 'altro' => 'Altro'];

    /**
     * @throws \RuntimeException con un messaggio da mostrare all'utente
     */
    public function iscrivi(Domanda $domanda, Carbon $versataIl, string $metodo, bool $promo, User $utente): Socio
    {
        if ($domanda->is_test) {
            throw new \RuntimeException('Una domanda di prova non si iscrive nel libro soci.');
        }
        if (app(AnniContabili::class)->chiuso($versataIl->year)) {
            throw new \RuntimeException("L'anno {$versataIl->year} della Prima Nota è chiuso: la quota non si può registrare con questa data.");
        }

        $socio = DB::transaction(function () use ($domanda, $versataIl, $metodo, $promo, $utente) {
            $bloccata = Domanda::whereKey($domanda->id)->lockForUpdate()->first();
            if (! $bloccata || $bloccata->stato !== 'approvata' || $bloccata->socio_id !== null) {
                throw new \RuntimeException('La domanda non è più in attesa della quota: ricarica la pagina.');
            }
            if (Socio::withTrashed()->where('codice_fiscale', $bloccata->codice_fiscale)->exists()) {
                throw new \RuntimeException('Esiste già una scheda socio con questo codice fiscale: controlla nel libro soci.');
            }

            $anno = Quota::anno($versataIl, $promo);
            $importo = (float) ($bloccata->quota_importo ?? Quota::importo($bloccata->tipo_socio));
            $minore = $bloccata->tipo_socio === 'minorenne';

            $socio = new Socio();
            $socio->forceFill([
                'tipo' => $bloccata->tipo_socio === 'minorenne' ? 'minorenne' : 'ordinario',
                'stato' => 'attivo',
                'cognome' => $bloccata->cognome,
                'nome' => $bloccata->nome,
                'data_nascita' => $bloccata->data_nascita,
                'luogo_nascita' => $bloccata->luogo_nascita,
                'codice_fiscale' => $bloccata->codice_fiscale,
                'sesso' => $bloccata->sesso,
                'indirizzo' => $bloccata->indirizzo,
                'cap' => $bloccata->cap,
                'citta' => $bloccata->citta,
                'provincia' => $bloccata->provincia,
                // Per un minore senza email propria, gl_domande.email è quella
                // del genitore: nella scheda socio va solo come email del tutore.
                'email' => $minore && $bloccata->email === $bloccata->genitore_email ? null : $bloccata->email,
                'telefono' => $bloccata->telefono,
                'tutore_nome' => $minore ? $bloccata->genitore_nome : null,
                'tutore_codice_fiscale' => $minore ? $bloccata->genitore_codice_fiscale : null,
                'tutore_relazione' => $minore ? 'Genitore' : null,
                'tutore_telefono' => $minore ? $bloccata->genitore_telefono : null,
                'tutore_email' => $minore ? $bloccata->genitore_email : null,
                'data_prima_iscrizione' => $versataIl->toDateString(),
                'anno_iscrizione' => $versataIl->year,
                'quota_scadenza' => Carbon::create($anno, 12, 31)->toDateString(),
                'inserito_da' => $utente->id,
                'privacy_accettata_il' => $bloccata->created_at,
                'privacy_ip' => $bloccata->ip_address,
                'privacy_versione' => $bloccata->informativa_versione,
                'privacy_metodo' => 'online',
                // Consenso a foto e video della domanda (v0.33.0, privacy-link.md §3)
                'foto_video_consenso' => (bool) $bloccata->consenso_foto_video,
                'foto_video_il' => $bloccata->consenso_foto_video ? $bloccata->created_at : null,
                'foto_video_origine' => $bloccata->consenso_foto_video ? 'domanda' : null,
                'note' => "Iscritto da domanda online n. {$bloccata->id}.",
            ])->save();

            $rinnovo = new Rinnovo();
            $rinnovo->forceFill([
                'socio_id' => $socio->id,
                'anno' => $anno,
                'importo' => $importo,
                'data_pagamento' => $versataIl->toDateString(),
                'metodo_pagamento' => $metodo,
                'note' => $promo && $anno > $versataIl->year
                    ? "Prima quota, promozione ottobre–dicembre: vale dal {$versataIl->format('d/m/Y')} al 31/12/{$anno}."
                    : 'Prima quota, da domanda di adesione.',
                'registrato_da' => $utente->id,
            ])->save();

            // L'anno contabile ha una riga dal primo movimento (prima-nota.md §3).
            app(AnniContabili::class)->assicura($versataIl->year);

            $movimento = new PrimaNota();
            $movimento->forceFill([
                'data' => $versataIl->toDateString(),
                'tipo' => 'entrata',
                'conto' => $metodo === 'contante' ? 'cassa' : 'banca',
                'importo' => $importo,
                'descrizione' => "Quota associativa {$anno} – {$socio->cognome} {$socio->nome}",
                'categoria_id' => PrimaNotaCategoria::where('codice', 'QUOTE')->value('id'),
                'socio_id' => $socio->id,
                'rinnovo_id' => $rinnovo->id,
                'soggetto' => $minore ? $bloccata->genitore_nome : trim($socio->nome . ' ' . $socio->cognome),
                'registrato_da' => $utente->id,
            ])->save();

            $bloccata->forceFill([
                'socio_id' => $socio->id,
                'quota_versata_il' => $versataIl->toDateString(),
                'quota_anno' => $anno,
                'quota_promo' => $promo && $anno > $versataIl->year,
            ])->save();

            return $socio;
        });

        $domanda->refresh();
        InvioEmail::invia(new SocioIscritto($domanda, $socio), $domanda->emailDestinatario(), $domanda->nomeDestinatario(), 'socio_iscritto', $domanda);

        return $socio;
    }
}
