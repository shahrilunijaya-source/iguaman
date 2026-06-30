@extends('layouts.staff')

@section('title', 'Tahap Kepuasan Pelanggan')

@php
    $labels = array_keys($counts);
    $data = array_values($counts);
    $total = array_sum($data);
@endphp

@section('content')
    @include('laporan-kn._print_css')

    <div class="tap-nav js-no-print" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('laporan-kn.index') }}" class="tap-nav__back">← Laporan KN</a>
        <span class="tap-nav__crumb">Tahap Kepuasan Pelanggan</span>
    </div>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Tahap Kepuasan Pelanggan<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($total) }}</strong> jumlah maklum balas</p>
        </div>
    </div>

    @include('laporan-kn._filters', ['routeName' => 'laporan-kn.kepuasan', 'show' => ['cawangan', 'bulan', 'tahun']])

    <div style="display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:16px;">
        <div class="tap-card" style="padding:16px; min-height:280px;">
            <canvas id="kepuasanChart" role="img" aria-label="Carta pai tahap kepuasan pelanggan"></canvas>
        </div>
        <div class="tap-card" style="overflow-x:auto;">
            <table class="lkn-table">
                <thead>
                    <tr><th>Tahap Kepuasan</th><th class="lkn-num">Bilangan</th></tr>
                </thead>
                <tbody>
                    @foreach ($counts as $level => $bil)
                        <tr><td>{{ $level }}</td><td class="lkn-num">{{ number_format($bil) }}</td></tr>
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
        new Chart(document.getElementById('kepuasanChart'), {
            type: 'pie',
            data: {
                labels: @json($labels),
                datasets: [{ data: @json($data), backgroundColor: ['#1a6fa8', '#F6A623', '#E1495B'] }],
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
        });
    </script>
@endpush
