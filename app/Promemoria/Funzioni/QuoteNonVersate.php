<?php

namespace App\Promemoria\Funzioni;

use App\Models\Associazione;
use App\Models\Promemoria;
use App\Models\Rinnovo;
use App\Models\Socio;
use App\Promemoria\Destinatario;
use App\Promemoria\Funzione;
use App\Services\ProfiloEnte;
use App\Services\Quota;
use App\Services\RinnoviQuota;
use App\Support\Importo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Quote non versate (rinnovi-quota.md §4): soci attivi senza la quota
 * dell'anno sociale scelto (quello in corso, o quello dopo per l'invito di
 * dicembre). Per i minorenni l'email va al genitore.
 */
class QuoteNonVersate implements Funzione
{
    public function chiave(): string
    {
        return 'quote_non_versate';
    }

    public function nome(): string
    {
        return 'Quote non versate';
    }

    public function descrizione(): string
    {
        return 'Soci attivi senza la quota dell\'anno scelto. Per i soci minorenni l\'email va al genitore.';
    }

    public function sezione(): string
    {
        return 'rinnovi_quota';
    }

    public function quandoAmmessi(): array
    {
        return ['data_fissa'];
    }

    public function parametri(): array
    {
        return [
            'anno_sociale' => [
                'label' => 'Anno sociale',
                'opzioni' => ['corrente' => 'Quello in corso', 'successivo' => 'Quello dopo'],
                'aiuto' => 'Per l\'invito di dicembre: «quello dopo».',
            ],
        ];
    }

    public function segnaposti(): array
    {
        return [
            'saluto' => 'Nome di chi riceve (il genitore per un minore)',
            'nome' => 'Nome del socio',
            'cognome' => 'Cognome del socio',
            'anno' => 'Anno sociale',
            'importo' => 'Quota dovuta',
            'scadenza' => 'Termine del 31 gennaio',
            'scadenza_decadenza' => 'Ultimo giorno prima della decadenza (30 aprile)',
            'iban' => 'IBAN della Compagnia',
            'causale' => 'Causale del bonifico',
            'firma' => 'Nome del Tesoriere',
        ];
    }

    public function anno(Promemoria $p, Carbon $quando): int
    {
        return $quando->year + ($p->parametro('anno_sociale') === 'successivo' ? 1 : 0);
    }

    public function periodo(Promemoria $p, Carbon $quando): string
    {
        return (string) $this->anno($p, $quando);
    }

    public function destinatari(Promemoria $p, Carbon $quando): Collection
    {
        $anno = $this->anno($p, $quando);
        $iban = Associazione::istanza()?->iban ?: Quota::IBAN_SEGNAPOSTO;
        $tesoriere = RinnoviQuota::tesoriere();
        $firma = $tesoriere ? "{$tesoriere}, Tesoriere" : 'Il Tesoriere';

        return Socio::query()
            ->where('stato', 'attivo')->where('pseudonimizzato', false)
            ->where(fn ($q) => $q->whereNull('data_prima_iscrizione')->orWhere('data_prima_iscrizione', '<=', "{$anno}-12-31"))
            ->whereDoesntHave('rinnovi', fn ($q) => $q->valido()->where('anno', $anno))
            ->orderBy('cognome')->orderBy('nome')
            ->get()
            ->map(function (Socio $s) use ($anno, $iban, $firma) {
                $dest = $s->destinatarioPrivacy();
                $nomeSocio = trim("{$s->cognome} {$s->nome}");
                $perGenitore = $dest && $dest['destinatario'] === 'tutore';
                $chi = $dest['nome'] ?? ($s->isMinorenne() ? ($s->tutore_nome ?: 'genitore') : trim("{$s->nome} {$s->cognome}"));

                return new Destinatario(
                    chiave: 'socio:' . $s->id,
                    socioId: $s->id,
                    email: $dest['email'] ?? null,
                    nome: $chi,
                    etichetta: $nomeSocio . ($perGenitore ? " (al genitore {$chi})" : ''),
                    valori: [
                        'saluto' => explode(' ', trim($chi))[0] ?: $chi,
                        'nome' => (string) $s->nome,
                        'cognome' => (string) $s->cognome,
                        'anno' => (string) $anno,
                        'importo' => Importo::it(RinnoviQuota::importo(RinnoviQuota::categoria($s, $anno)), true),
                        'scadenza' => RinnoviQuota::termine($anno)->format('d/m/Y'),
                        'scadenza_decadenza' => RinnoviQuota::ultimoGiorno($anno)->format('d/m/Y'),
                        'iban' => $iban,
                        'causale' => "Quota associativa {$anno} – {$nomeSocio}",
                        'firma' => $firma,
                    ],
                );
            });
    }

    public function esempio(Promemoria $p, Carbon $quando, \App\Models\User $u): array
    {
        $anno = $this->anno($p, $quando);
        $tesoriere = RinnoviQuota::tesoriere();

        return [
            'saluto' => explode(' ', trim($u->name))[0] ?: $u->name,
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'anno' => (string) $anno,
            'importo' => Importo::it(RinnoviQuota::importo('ordinario'), true),
            'scadenza' => RinnoviQuota::termine($anno)->format('d/m/Y'),
            'scadenza_decadenza' => RinnoviQuota::ultimoGiorno($anno)->format('d/m/Y'),
            'iban' => Associazione::istanza()?->iban ?: Quota::IBAN_SEGNAPOSTO,
            'causale' => "Quota associativa {$anno} – Rossi Mario",
            'firma' => $tesoriere ? "{$tesoriere}, Tesoriere" : 'Il Tesoriere',
        ];
    }

    public function predefiniti(): array
    {
        $chiusura = "\n\nPuoi versarla con bonifico all'IBAN {iban}, causale «{causale}», oppure in sede.\n\nUn saluto,\n{firma}";

        return [
            [
                'nome' => 'Invito a rinnovare la quota',
                'giorno' => 15, 'mese' => 12, 'parametri' => ['anno_sociale' => 'successivo'],
                'oggetto' => 'Quota associativa {anno}: si rinnova entro il 31 gennaio',
                'testo' => "Ciao {saluto},\n\nè il momento di rinnovare la quota associativa {anno} di {nome} ({importo}): lo statuto chiede di versarla entro il {scadenza}." . $chiusura,
            ],
            [
                'nome' => 'Sollecito: voto sospeso',
                'giorno' => 1, 'mese' => 2, 'parametri' => ['anno_sociale' => 'corrente'],
                'oggetto' => 'Quota associativa {anno} non ancora versata',
                'testo' => "Ciao {saluto},\n\nla quota associativa {anno} di {nome} ({importo}) non risulta ancora versata: il termine era il {scadenza}. Finché la quota non è versata non si vota in Assemblea." . $chiusura,
            ],
            [
                'nome' => 'Ultimo avviso prima della decadenza',
                'giorno' => 1, 'mese' => 4, 'parametri' => ['anno_sociale' => 'corrente'],
                'oggetto' => 'Quota {anno}: ultimo avviso prima della decadenza',
                'testo' => "Ciao {saluto},\n\nla quota associativa {anno} di {nome} ({importo}) risulta ancora da versare. Lo statuto prevede che chi non versa entro tre mesi dal 31 gennaio decada da socio: l'ultimo giorno utile è il {scadenza_decadenza}." . $chiusura,
            ],
        ];
    }
}
