@extends('layouts.staff')

@section('title', 'Cara Mengetahui JBG')

@php
    $labels = array_values($buckets);
    $data = [];
    foreach ($buckets as $key => $label) {
        $data[] = $counts[$key] ?? 0;
    }
    $total = array_sum($data);
@endphp

@section('content')
    @include('laporan-kn._print_css')

    <div class="tap-nav js-no-print" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('laporan-kn.index') }}" class="tap-nav__back">← Laporan KN</a>
        <span class="tap-nav__crumb">Cara Mengetahui JBG</span>
    </div>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Cara Mengetahui JBG<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($total) }}</strong> jumlah maklum balas</p>
        </div>
    </div>

    @include('laporan-kn._filters', ['routeName' => 'laporan-kn.cara-mengetahui', 'show' => ['cawangan', 'bulan', 'tahun']])

    <div style="display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:16px;">
        <div class="tap-card" style="padding:16px; min-height:280px;">
            <canvas id="caraChart" role="img" aria-label="Carta pai cara mengetahui JBG"></canvas>
        </div>
        <div class="tap-card" style="overflow-x:auto;">
            <table class="lkn-table">
                <thead>
                    <tr><th>Cara Mengetahui</th><th class="lkn-num">Bilangan</th></tr>
                </thead>
                <tbody>
                    @foreach ($buckets as $key => $label)
                        <tr><td>{{ $label }}</td><td class="lkn-num">{{ number_format($counts[$key] ?? 0) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td>JUMLAH</td><td class="lkn-num">{{ number_format($total) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('caraChart'), {
            type: 'pie',
            data: {
                labels: @json($labels),
                datasets: [{ data: @json($data), backgroundColor: ['#1a6fa8', '#0083B0', '#F6A623', '#7B61FF', '#94A3B8'] }],
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
        });
    </script>
@endpush
