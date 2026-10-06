{{--
    Piè di tabella con conteggio e paginazione.
    Desktop: numeri di pagina. Telefono: Prec./Succ. (bersagli più grandi).
    <x-paginazione :elementi="$soci" nome="soci" />
    Con la scelta delle righe per pagina (parametro per_pagina, gli altri
    parametri della pagina restano):
    <x-paginazione :elementi="$righe" nome="operazioni" :scelte="[10 => '10', 0 => 'Tutte']" :scelta="10" />
--}}
@props(['elementi', 'nome' => 'risultati', 'scelte' => [], 'scelta' => null])
<div class="table-footer">
    <div class="table-footer-info">
        @if ($elementi->total() > 0)
            <span>{{ ucfirst($nome) }} {{ $elementi->firstItem() }}–{{ $elementi->lastItem() }} di {{ $elementi->total() }}</span>
        @endif
        @if ($scelte && $elementi->total() > min(array_filter(array_keys($scelte)) ?: [0]))
            <form method="GET" class="pag-righe" x-data>
                @foreach (request()->except(['page', 'per_pagina']) as $chiave => $valore)
                    @if (is_scalar($valore))
                        <input type="hidden" name="{{ $chiave }}" value="{{ $valore }}">
                    @endif
                @endforeach
                <label for="pag-righe-scelta">Righe per pagina</label>
                <select id="pag-righe-scelta" name="per_pagina" class="form-select" @change="$el.form.submit()">
                    @foreach ($scelte as $valore => $etichetta)
                        <option value="{{ $valore }}" @selected((int) $scelta === (int) $valore)>{{ $etichetta }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-secondary btn-sm">Applica</button></noscript>
            </form>
        @endif
    </div>
    @if ($elementi->hasPages())
        <nav class="paginazione" aria-label="Paginazione">
            @if ($elementi->onFirstPage())
                <span class="pag-btn pag-solo-mobile" aria-disabled="true">‹ Prec.</span>
            @else
                <a class="pag-btn pag-solo-mobile" href="{{ $elementi->previousPageUrl() }}" rel="prev">‹ Prec.</a>
            @endif

            @foreach ($elementi->getUrlRange(max(1, $elementi->currentPage() - 2), min($elementi->lastPage(), $elementi->currentPage() + 2)) as $pagina => $url)
                @if ($pagina === $elementi->currentPage())
                    <span class="pag-btn pag-solo-desktop attiva" aria-current="page">{{ $pagina }}</span>
                @else
                    <a class="pag-btn pag-solo-desktop" href="{{ $url }}">{{ $pagina }}</a>
                @endif
            @endforeach

            @if ($elementi->hasMorePages())
                <a class="pag-btn pag-solo-mobile" href="{{ $elementi->nextPageUrl() }}" rel="next">Succ. ›</a>
            @else
                <span class="pag-btn pag-solo-mobile" aria-disabled="true">Succ. ›</span>
            @endif
        </nav>
    @endif
</div>
