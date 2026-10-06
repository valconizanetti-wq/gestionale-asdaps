@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-sub">Benvenuto, {{ auth()->user()->name }}</p>
        </div>
    </div>

    {{-- Provvisoria: si riempirà con i riepiloghi dei moduli man mano che
         vengono costruiti (quote da sollecitare, scadenze, anomalie). --}}
    <div class="card">
        <div class="empty-state">
            I moduli compariranno nel menu a sinistra man mano che vengono attivati.
        </div>
    </div>
@endsection
