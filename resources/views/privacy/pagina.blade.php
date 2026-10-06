{{--
    Pagina del link personale per l'informativa privacy (tavola "Link
    privacy 1"; privacy-link.md §3). Al genitore se il socio è minorenne.
    Dichiarazione di lettura obbligatoria; foto e video facoltativo, già
    spuntato se il consenso c'è: togliendo la spunta si revoca.
--}}
@extends('layouts.pubblico')
@section('title', 'Informativa privacy')
@section('stretto', '1')
@section('content')
@php
    $nomeSocio = trim($socio->nome . ' ' . $socio->cognome);
    $nomeDestinatario = $perTutore ? ($socio->tutore_nome ?: 'genitore') : $nomeSocio;
    $categoria = $socio->isMinorenne() ? 'socio giovane' . ($eta !== null ? " ({$eta} anni)" : '') : 'socio ordinario';
    $fotoAttuale = (bool) $socio->foto_video_consenso;
    $fotoSpuntata = old('_token') ? (bool) old('foto_video') : $fotoAttuale;
    $lui = $socio->sesso === 'F' ? 'lei' : 'lui';
@endphp
<div class="pubblico-colonna voto-pagina">
    <div class="iscr-titolo">
        <span class="iscr-occhiello">{{ $precedente ? 'Nuova versione' : 'Link personale' }}</span>
        <h1>Informativa privacy</h1>
    </div>

    <p class="privacy-saluto">
        Gentile {{ $nomeDestinatario }},
        @if ($precedente)
            abbiamo aggiornato l'informativa sul trattamento dei dati personali. Ti chiediamo di leggerla e di confermarlo.
        @elseif ($perTutore)
            come genitore di {{ $socio->nome }} ti chiediamo di confermare di aver letto la nostra informativa sul trattamento dei dati personali. Bastano un minuto e una spunta.
        @else
            ti chiediamo di confermare di aver letto la nostra informativa sul trattamento dei dati personali. Bastano un minuto e una spunta.
        @endif
    </p>

    <section class="voto-scheda">
        <dl class="voto-dati">
            <x-dato label="Socio" :valore="$nomeSocio . ', ' . $categoria" />
            <x-dato label="Informativa" :valore="'Versione ' . $versione . ($precedente ? ' · hai letto la ' . $precedente->privacy_versione . ' il ' . $precedente->privacy_accettata_il->format('d/m/Y') : '')" />
        </dl>
    </section>

    <details class="privacy-testo" open>
        <summary class="privacy-testo-testa">Leggi l'informativa <x-icona nome="giu" class="iscr-freccia" /></summary>
        <div class="privacy-testo-corpo">
            <article class="documento-testo">{!! $html !!}</article>
        </div>
        <div class="privacy-testo-piede"><a href="{{ route('informativa') }}" target="_blank" rel="noopener">Apri in una pagina a parte</a></div>
    </details>

    <form method="POST" action="{{ route('privacy-link.conferma', $token) }}" class="voto-modulo" x-data="{ inviato: false }" x-on:submit="inviato = true">
        @csrf
        <div class="privacy-scelte">
            <div @class(['has-error' => $errors->has('dichiarazione')])>
                <div class="dichiarazione principale">
                    <input type="checkbox" id="dichiarazione" name="dichiarazione" value="1" @checked(old('dichiarazione'))
                        @error('dichiarazione') aria-invalid="true" aria-describedby="dichiarazione-errore" @enderror>
                    <div class="dichiarazione-corpo">
                        <label for="dichiarazione"><strong>Dichiaro di aver letto l'informativa (versione {{ $versione }}){{ $perTutore ? ', come genitore di ' . $nomeSocio : '' }}.</strong> <span class="obbligatorio" aria-hidden="true">*</span></label>
                        <p class="norma">Art. 13 Reg. (UE) 2016/679</p>
                    </div>
                </div>
                @error('dichiarazione')<div class="form-error" id="dichiarazione-errore">{{ $message }}</div>@enderror
            </div>

            <div class="dichiarazione">
                <input type="checkbox" id="foto_video" name="foto_video" value="1" @checked($fotoSpuntata)>
                <div class="dichiarazione-corpo">
                    <label for="foto_video">
                        @if ($perTutore)
                            Acconsento alla pubblicazione di foto e video che ritraggono {{ $socio->nome }} durante le attività, sul sito e sui canali social dell'Associazione{{ $assensoMinore ? ', dopo averne parlato con ' . $lui : '' }}. Posso revocare il consenso in ogni momento.
                        @else
                            Acconsento alla pubblicazione di foto e video che mi ritraggono durante le attività, sul sito e sui canali social dell'Associazione. Posso revocare il consenso in ogni momento.
                        @endif
                    </label>
                    <div><x-badge>Facoltativo</x-badge></div>
                    @if ($fotoAttuale && $socio->foto_video_il)
                        <p class="dichiarazione-nota">Oggi: consenso dato il {{ $socio->foto_video_il->format('d/m/Y') }}. Togliendo la spunta lo revochi.</p>
                    @endif
                    <p class="norma">{{ $normaImmagine }}</p>
                </div>
            </div>
        </div>
        <div class="voto-modulo-piede privacy-piede">
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Conferma</button>
            <p class="dichiarazione-nota">Registriamo data, ora e indirizzo IP della conferma. Il link vale una volta sola.</p>
        </div>
    </form>
</div>
@endsection
