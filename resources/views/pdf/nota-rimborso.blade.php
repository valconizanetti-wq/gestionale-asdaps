{{--
    Nota di rimborso spese (tavola AN-2) e dichiarazione di rinuncia al
    rimborso (AN-4b), sulla carta intestata del periodo dell'ente alla data
    della nota. Tutto dalla fotografia $n->dati: il PDF si rifà sempre
    uguale (difetto 6 del Dante). Firme: il socio e il visto (il Tesoriere,
    o il Presidente se ha anticipato il Tesoriere: E2). Con i contanti la
    riga «Per quietanza», firmata quando il socio riceve i soldi (E1).
    Annullata: timbro in cima e motivo in fondo.
    Variabili: $n (NotaRimborso), $dataDocumento.
--}}
@extends('pdf.carta-intestata')

@use('App\Support\ImportoInLettere')
@use('App\Support\Importo')
@use('Illuminate\Support\Carbon')

@php
    $rinuncia = $n->isRinuncia();
    $s = $n->dati['socio'] ?? [];
    $visto = $n->dati['visto'] ?? ['carica' => 'il Tesoriere', 'nome' => null];
    $righe = $n->dati['righe'] ?? [];
    $allegati = array_sum(array_column($righe, 'giustificativi'));
    $nomeSocio = trim(($s['nome'] ?? '') . ' ' . ($s['cognome'] ?? ''));
    $generata = isset($n->dati['generata_il']) ? Carbon::parse($n->dati['generata_il']) : $n->created_at;
    $contanti = ! $rinuncia && $n->modalita === 'cassa';
@endphp

@section('titolo', ($rinuncia ? 'Rinuncia al rimborso ' : 'Nota di rimborso ') . $n->numero)

@section('stili')
.numero-nota { text-align: right; }
.numero-nota strong { font-size: 17pt; color: #0f4124; }
.etichetta-num { font-size: 7pt; letter-spacing: .14em; text-transform: uppercase; color: #4f5451; }
table.righe td { padding: 1.6mm 0; border-bottom: 0.5pt solid #e3e1db; }
table.righe td.etichetta { width: 34mm; color: #4f5451; }
table.spese { width: 100%; border-collapse: collapse; margin-top: 5mm; }
table.spese th { font-size: 7pt; letter-spacing: .1em; text-transform: uppercase; color: #4f5451; text-align: left; padding: 1.5mm 1.5mm 1.5mm 0; border-bottom: 0.8pt solid #9aa0a8; }
table.spese td { padding: 1.6mm 1.5mm 1.6mm 0; border-bottom: 0.5pt solid #e3e1db; vertical-align: top; }
table.spese .num { text-align: right; white-space: nowrap; padding-right: 0; }
table.spese .data { width: 18mm; white-space: nowrap; }
table.spese tr.totale td { font-weight: bold; border-bottom: 0; padding-top: 2.5mm; }
.codice-riga { font-size: 7pt; color: #4f5451; }
.firme { margin-top: 16mm; }
.firme td { width: 50%; text-align: center; font-size: 8.5pt; color: #3b4459; vertical-align: bottom; padding: 0 5mm; }
.linea-firma { border-bottom: 0.5pt solid #9aa0a8; height: 14mm; margin-bottom: 1.5mm; }
.quietanza { margin-top: 9mm; border: 0.6pt solid #9aa0a8; padding: 3mm 4mm; }
.quietanza td { vertical-align: bottom; }
.firma-quietanza { text-align: center; padding-left: 6mm; }
.piede-nota { margin-top: 10mm; font-size: 7.5pt; color: #4f5451; }
.annullata { border: 1.5pt solid #9e2328; color: #9e2328; font-weight: bold; font-size: 14pt; text-align: center; padding: 2mm; margin-bottom: 4mm; letter-spacing: .1em; }
@endsection

@section('content')
@if ($n->isAnnullata())
    <div class="annullata">ANNULLATA il {{ $n->annullata_il->format('d/m/Y') }}</div>
@endif
<table class="colonne"><tr>
    <td class="colonna">
        <h1>{{ $rinuncia ? 'Dichiarazione di rinuncia al rimborso' : 'Nota di rimborso spese' }}</h1>
        <p class="sotto">{{ $rinuncia ? 'Spese sostenute per conto della Compagnia e donate alla Compagnia' : 'Spese sostenute per conto della Compagnia e anticipate di tasca propria' }}</p>
    </td>
    <td class="colonna numero-nota"><span class="etichetta-num">{{ $rinuncia ? 'Rinuncia' : 'Nota' }}</span><br><strong>{{ $n->numero }}</strong><br>
        <span class="sotto">Castel Viscardo, {{ $n->data_nota->format('d/m/Y') }}</span></td>
</tr></table>

<table class="dati righe">
    <tr><td class="etichetta">Socio</td><td>{{ ($s['cognome'] ?? '') . ' ' . ($s['nome'] ?? '') }}@if (! empty($s['codice_fiscale'])) · C.F. <span class="mono">{{ $s['codice_fiscale'] }}</span>@endif</td></tr>
    <tr><td class="etichetta">Carica</td><td>{{ $s['carica'] ?? 'Socio' }}</td></tr>
    @if ($rinuncia)
        <tr><td class="etichetta">Rimborso</td><td>Nessuno: l'importo è donato alla Compagnia</td></tr>
    @else
        <tr><td class="etichetta">Rimborso</td><td>{{ $n->modalita === 'banca' ? 'Con bonifico bancario' : 'In contanti, dalla cassa della Compagnia' }}</td></tr>
        @if ($n->modalita === 'banca')
            <tr><td class="etichetta">IBAN</td><td><span class="mono">{{ trim(chunk_split((string) $n->iban, 4, ' ')) }}</span> · intestato a {{ $nomeSocio }}</td></tr>
            <tr><td class="etichetta">Causale</td><td>Rimborso spese {{ $n->numero }}</td></tr>
        @endif
    @endif
</table>

<table class="spese">
    <thead>
        <tr><th class="data">Data</th><th>Descrizione</th><th>Fornitore</th><th>Documento</th><th class="num">Importo €</th></tr>
    </thead>
    <tbody>
        @foreach ($righe as $r)
            <tr>
                <td class="data">{{ Carbon::parse($r['data'])->format('d/m/Y') }}</td>
                <td>{{ $r['descrizione'] }}@if ($r['categoria']) ({{ $r['categoria'] }})@endif
                    @if (! empty($r['codice_riga']))<br><span class="codice-riga">Riga {{ $r['codice_riga'] }} del rendiconto</span>@endif</td>
                <td>{{ $r['fornitore'] ?: '—' }}</td>
                <td>{{ $r['documento'] }}</td>
                <td class="num">{{ Importo::it($r['importo']) }}</td>
            </tr>
        @endforeach
        <tr class="totale">
            <td colspan="4">{{ $rinuncia ? 'Totale donato' : 'Totale da rimborsare' }}</td>
            <td class="num">{{ Importo::it($n->totale) }}</td>
        </tr>
    </tbody>
</table>

<p>Importo: <strong>€ {{ Importo::it($n->totale) }}</strong> ({{ ImportoInLettere::euro($n->totale) }}).</p>

@if ($rinuncia)
    <p>Il sottoscritto dichiara di aver sostenuto personalmente le spese elencate, per l'attività della Compagnia, e che i giustificativi allegati
        sono gli originali; dichiara di <strong>rinunciare al loro rimborso</strong> e di donare la somma indicata alla Compagnia, senza nulla pretendere.</p>
@else
    <p>Il sottoscritto dichiara di aver sostenuto personalmente le spese elencate, per l'attività della Compagnia, e che i giustificativi allegati
        sono gli originali; chiede il rimborso della somma indicata e dichiara di non averlo già ricevuto per le stesse spese.</p>
@endif

@if ($n->note)
    <p class="nota">Note: {{ $n->note }}</p>
@endif

<table class="colonne firme"><tr>
    <td><div class="linea-firma"></div>Il socio<br><strong>{{ $nomeSocio }}</strong></td>
    <td><div class="linea-firma"></div>Visto, {{ $visto['carica'] }}
        @if (! empty($visto['nome']))<br><strong>{{ $visto['nome'] }}</strong>@endif</td>
</tr></table>

@if ($contanti)
    <div class="quietanza"><table class="colonne"><tr>
        <td class="colonna"><strong>Per quietanza.</strong> Ricevuti in contanti € {{ Importo::it($n->totale) }} il ____/____/________</td>
        <td class="colonna firma-quietanza"><div class="linea-firma"></div>Il socio, {{ $nomeSocio }}</td>
    </tr></table></div>
@endif

<p class="piede-nota">
    @if ($rinuncia)
        Donazione alla Compagnia dell'importo delle spese anticipate: al momento della registrazione si scrivono in cassa, nella stessa data, l'uscita delle spese e l'entrata della donazione.
    @else
        Rimborso di spese documentate: non costituisce compenso né reddito per chi lo riceve.
    @endif
    Allegati: {{ $allegati }} {{ $allegati === 1 ? 'giustificativo' : 'giustificativi' }}.
    Codice del documento: {{ $n->numero }} · emessa dal Gestionale il {{ $generata->format('d/m/Y \a\l\l\e H:i') }}.
    @if (! $rinuncia && ! $contanti)
        Dopo il pagamento: «Pagata il ____/____/________ con bonifico», a cura del Tesoriere.
    @endif
</p>

@if ($n->isAnnullata())
    <p class="nota"><strong>{{ $rinuncia ? 'Dichiarazione annullata' : 'Nota annullata' }}</strong> il {{ $n->annullata_il->format('d/m/Y') }}: {{ $n->motivo_annullamento }}</p>
@endif
@endsection
