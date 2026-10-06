<?php

namespace App\Services;

use App\Models\Domanda;
use App\Models\Impostazione;
use App\Models\Socio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Logica della domanda di iscrizione online (modulo pubblico), separata dal
 * controller: preparazione dei dati, creazione, annullamento.
 * Regole: statuto v1.0 art. 6, scheda domande-iscrizione.md.
 *
 * Dalla v0.30.0 (decisione di Paolo del 29/09/2026) la domanda vale con
 * l'invio del modulo: niente più conferma dal link dell'email. La domanda
 * nasce "in_attesa" e va subito ai consiglieri; l'email al richiedente
 * porta la ricevuta e il link per annullarla. Le domande del vecchio
 * flusso ancora "in_verifica" si confermano ancora dal loro link
 * (conferma()), finché scadono.
 */
class IscrizioneOnline
{
    /** Ore di validità del link di conferma (solo domande del vecchio flusso). */
    public const ORE_CONFERMA = 48;

    /** Stati in cui una domanda impedisce di presentarne un'altra per la stessa persona. */
    private const STATI_ATTIVI = ['in_verifica', 'in_attesa', 'da_rifiutare', 'in_parita', 'approvata'];

    /**
     * Dati validati del passo 1 → dati pronti per il riepilogo e per il
     * salvataggio, con quanto ricavato dai codici fiscali. Tutto ciò che
     * finisce nel database è calcolato qui, sul server.
     */
    public function prepara(array $v): array
    {
        $minore = $v['chi'] === 'minore';
        $cf = CodiceFiscale::analizza($v['codice_fiscale']);

        $dati = [
            'minore' => $minore,
            'nome' => $v['nome'],
            'cognome' => $v['cognome'],
            'codice_fiscale' => $v['codice_fiscale'],
            'data_nascita' => $cf['data_nascita']->toDateString(),
            'sesso' => $cf['sesso'],
            'luogo_nascita' => CodiceFiscale::luogoLeggibile($cf),
            // Per un minore senza email propria le comunicazioni vanno al
            // genitore: gl_domande.email è obbligatoria, quindi si copia la sua.
            'email' => $minore ? (($v['email'] ?? null) ?: $v['genitore_email']) : $v['email'],
            'email_propria_minore' => $minore && ! empty($v['email'] ?? null),
            'telefono' => $minore ? null : ($v['telefono'] ?? null),
            'indirizzo' => $v['indirizzo'],
            'cap' => $v['cap'],
            'citta' => $v['citta'],
            'provincia' => $v['provincia'],
            'tipo_socio' => $minore ? 'minorenne' : 'ordinario',
            'documento_tipo' => $v['documento_tipo'],
            'documento_numero' => $v['documento_numero'],
            'nome_non_corrisponde' => ! CodiceFiscale::corrispondeANominativo($v['codice_fiscale'], $v['cognome'], $v['nome']),
        ];

        if ($minore) {
            $gcf = CodiceFiscale::analizza($v['genitore_codice_fiscale']);
            $dati += [
                'genitore_nome' => trim($v['genitore_nome'] . ' ' . $v['genitore_cognome']),
                'genitore_nome_solo' => $v['genitore_nome'],
                'genitore_cognome' => $v['genitore_cognome'],
                'genitore_codice_fiscale' => $v['genitore_codice_fiscale'],
                'genitore_email' => $v['genitore_email'],
                'genitore_telefono' => $v['genitore_telefono'],
                'genitore_data_nascita' => $gcf['data_nascita']->toDateString(),
                'genitore_luogo_nascita' => CodiceFiscale::luogoLeggibile($gcf),
                'genitore_nome_non_corrisponde' => ! CodiceFiscale::corrispondeANominativo($v['genitore_codice_fiscale'], $v['genitore_cognome'], $v['genitore_nome']),
            ];
        }

        return $dati;
    }

    /**
     * Motivo per cui questa persona non può presentare una domanda, oppure
     * null. Si controlla il codice fiscale, non l'email: due fratelli
     * hanno lo stesso genitore e quindi la stessa email.
     */
    public function motivoBlocco(string $codiceFiscale): ?string
    {
        if (Socio::where('codice_fiscale', $codiceFiscale)->where('stato', '!=', 'cancellato')->exists()) {
            return "Risulta già un socio con questo codice fiscale. Per informazioni scrivi all'Associazione.";
        }

        $attiva = Domanda::where('codice_fiscale', $codiceFiscale)
            ->whereIn('stato', self::STATI_ATTIVI)
            ->where('is_test', false)
            // Una domanda mai confermata non blocca oltre la scadenza del link.
            ->where(fn ($q) => $q->where('stato', '!=', 'in_verifica')->orWhere('token_conferma_scadenza', '>', now()))
            ->latest('id')
            ->first();

        return match ($attiva?->stato) {
            null => null,
            'in_verifica' => "C'è già una domanda da confermare per questo codice fiscale: controlla l'email e apri il link di conferma.",
            'in_attesa', 'in_parita', 'da_rifiutare' => "C'è già una domanda all'esame del Consiglio Direttivo per questo codice fiscale.",
            'approvata' => "La domanda per questo codice fiscale è già stata accolta: manca solo il versamento della quota.",
            default => 'C\'è già una domanda in corso di valutazione per questo codice fiscale.',
        };
    }

    /**
     * Crea la domanda, valida con l'invio: parte subito il termine di 5
     * giorni del Consiglio (statuto art. 6.4 come modificato).
     *
     * @return array{0: Domanda, 1: string} la domanda e il token del link
     *         per annullarla, in chiaro solo nell'email
     */
    public function crea(array $dati, array $consensi, Request $request): array
    {
        $tokenAnnulla = Domanda::generaToken();
        // Versioni in vigore oggi secondo il profilo dell'ente (v0.32.0).
        $versioneInformativa = ProfiloEnte::versione('informativa');

        // Un solo istante per l'invio e per le dichiarazioni che vi si
        // fanno, compreso l'assenso del minore (stessa ora in ricevuta).
        $ora = now();

        $domanda = new Domanda();
        // Assegnazione campo per campo, mai fill($request->all()): stato,
        // is_test e socio_id non devono poter arrivare dal modulo pubblico.
        $domanda->forceFill([
            'token' => Domanda::hashToken(Domanda::generaToken()),
            'cognome' => $dati['cognome'],
            'nome' => $dati['nome'],
            'data_nascita' => $dati['data_nascita'],
            'luogo_nascita' => $dati['luogo_nascita'],
            'codice_fiscale' => $dati['codice_fiscale'],
            'sesso' => $dati['sesso'],
            'email' => $dati['email'],
            'telefono' => $dati['telefono'],
            'indirizzo' => $dati['indirizzo'],
            'cap' => $dati['cap'],
            'citta' => $dati['citta'],
            'provincia' => $dati['provincia'],
            'tipo_socio' => $dati['tipo_socio'],
            'genitore_nome' => $dati['genitore_nome'] ?? null,
            'genitore_codice_fiscale' => $dati['genitore_codice_fiscale'] ?? null,
            'genitore_email' => $dati['genitore_email'] ?? null,
            'genitore_data_nascita' => $dati['genitore_data_nascita'] ?? null,
            'genitore_luogo_nascita' => $dati['genitore_luogo_nascita'] ?? null,
            'genitore_telefono' => $dati['genitore_telefono'] ?? null,
            'documento_tipo' => $dati['documento_tipo'],
            'documento_numero' => $dati['documento_numero'],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            // Quando sono stati aperti informativa e statuto (obbligatorio
            // dalla v0.30.3): ora della pagina aperta, o dell'invio.
            'informativa_aperta_il' => $consensi['informativa_aperta_il'] ?? null,
            'statuto_aperto_il' => $consensi['statuto_aperto_il'] ?? null,
            'informativa_versione' => $versioneInformativa,
            'informativa_hash' => Documenti::hash('informativa', $versioneInformativa),
            'statuto_versione' => ProfiloEnte::versione('statuto'),
            'consenso_privacy' => true,
            'consenso_arbitrato' => true,
            'consenso_extra_ue' => false,
            'consenso_foto_video' => $consensi['foto_video'],
            // Assenso del minore dai 14 anni (art. 16 D.Lgs. 36/2021): la
            // request lo rende obbligatorio quando serve; qui si registra
            // solo se richiesto e dato.
            'assenso_minore_il' => ($consensi['assenso_minore'] ?? false) ? $ora : null,
            'created_at' => $ora,
            'stato' => 'in_attesa',
            // token_conferma contiene l'impronta del link per annullare
            // (stessa colonna del vecchio link di conferma).
            'token_conferma' => Domanda::hashToken($tokenAnnulla),
            'token_conferma_scadenza' => null,
            'scadenza_cd' => $ora->copy()->addDays(Domanda::GIORNI_DECISIONE_CD),
            'cartacea' => false,
            // Fotografia dell'impostazione al momento della creazione: una
            // domanda di prova resta di prova (audit-log.md §8).
            // Con le iscrizioni chiuse al pubblico (prove dall'area riservata,
            // v0.32.0) la domanda è sempre di prova.
            'is_test' => (bool) Impostazione::get('domande_modalita_test', false)
                || ! (bool) Impostazione::get('iscrizioni_aperte', false),
        ]);
        $domanda->save();

        return [$domanda, $tokenAnnulla];
    }

    /**
     * Domanda del link dell'email, in qualunque stato. L'impronta del token
     * resta anche dopo la conferma: chi riapre il link (o preme due volte il
     * pulsante) vede che la domanda è già confermata, non "link non valido".
     */
    public function perToken(string $token): ?Domanda
    {
        if (strlen($token) !== 64) {
            return null;
        }

        return Domanda::where('token_conferma', Domanda::hashToken($token))->first();
    }

    public function linkScaduto(Domanda $domanda): bool
    {
        return $domanda->token_conferma_scadenza === null || $domanda->token_conferma_scadenza->isPast();
    }

    /**
     * Conferma di una domanda del vecchio flusso (prima della v0.30.0):
     * diventa valida e partono i 5 giorni del CD. Il controller apre poi la
     * votazione come per una domanda nuova.
     *
     * Restituisce false se un'altra richiesta l'ha già confermata: la riga
     * è bloccata durante il controllo, così due clic ravvicinati non
     * producono due conferme, due ricevute e due avvisi.
     */
    public function conferma(Domanda $domanda): bool
    {
        return DB::transaction(function () use ($domanda) {
            $bloccata = Domanda::whereKey($domanda->id)->lockForUpdate()->first();
            if (! $bloccata || $bloccata->stato !== 'in_verifica') {
                return false;
            }

            $domanda->forceFill([
                'stato' => 'in_attesa',
                'token_conferma_scadenza' => null,
                'scadenza_cd' => now()->addDays(Domanda::GIORNI_DECISIONE_CD),
            ])->save();

            return true;
        });
    }

    /** Link di conferma scaduto: la domanda non vale più. */
    public function segnaScaduta(Domanda $domanda): void
    {
        $domanda->forceFill(['stato' => 'scaduta'])->save();
    }

    /**
     * Ritiro dal link dell'email, finché la domanda non è iscritta nel
     * libro soci: i dati si cancellano subito. Se la votazione era in
     * corso, i consiglieri ricevono un avviso (VotazioneCd::avvisaRitiro).
     * Un eventuale verbale già redatto resta, senza collegamento.
     */
    public function annulla(Domanda $domanda): void
    {
        app(VotazioneCd::class)->avvisaRitiro($domanda);
        $domanda->cancellaPerFineConservazione();
    }
}
