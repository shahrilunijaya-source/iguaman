@extends('layouts.staff')

@section('title', 'Mengikut Kaum / Jantina')

@php
    $labels = array_map(fn ($r) => $r['label'], $pivot);
    $series = [];
    foreach ($jantina as $col) {
        $series[$col] = array_map(fn ($r) => $r[$col] ?? 0, $pivot);
    }
    $colTotals = [];
    foreach ($jantina as $col) { $colTotals[$col] = array_sum($series[$col]); }
    $grand = array_sum(array_map(fn ($r) => $r['total'], $pivot));
    $barColors = ['Lelaki' => '#0083B0', 'Perempuan' => '#E1495B'];
@endphp

@section('content')
    @include('laporan-kn._print_css')

    <div class="tap-nav js-no-print" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('laporan-kn.index') }}" class="tap-nav__back">← Laporan KN</a>
        <span class="tap-nav__crumb">Mengikut Kaum / Jantina</span>
    </div>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Mengikut Kaum / Jantina<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($grand) }}</strong> permohonan</p>
        </div>
    </div>

    @include('laporan-kn._filters', ['routeName' => 'laporan-kn.kaum-jantina', 'show' => ['cawangan', 'kategori', 'bulan', 'tahun']])

    <div class="tap-card" style="padding:16px; min-height:320px; margin-bottom:16px;">
        <canvas id="kaumChart" role="img" aria-label="Carta bar bertindan permohonan mengikut kaum dan jantina"></canvas>
    </div>

    <div class="tap-card" style="overflow-x:auto;">
        <table class="lkn-table">
            <thead>
                <tr>
                    <th>Kaum / Bangsa</th>
                    @foreach ($jantina as $col)
                        <th class="lkn-num">{{ $col }}</th>
                    @endforeach
                    <th class="lkn-num">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pivot as $r)
                    <tr>
                        <td>{{ $r['label'] }}</td>
                        @foreach ($jantina as $col)
                            <td class="lkn-num">{{ $r[$col] ?: '' }}</td>
                        @endforeach
                        <td class="lkn-num">{{ number_format($r['total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($jantina) + 2 }}" style="padding:16px; color:var(--mute); text-align:center;">Tiada rekod.</td></tr>
                @endforelse
            </tbody>
            @if (count($pivot))
                <tfoot>
                    <tr>
                        <td>JUMLAH</td>
                        @foreach ($jantina as $col)
                            <td class="lkn-num">{{ $colTotals[$col] ?: '' }}</td>
                        @endforeach
                        <td class="lkn-num">{{ number_format($grand) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('kaumChart'), {
            type: 'bar',
            data: {
                labels: @json($labels),
                datasets: [
                    @foreach ($jantina as $col)
                        { label: @json($col), data: @json($series[$col]), backgroundColor: @json($barColors[$col] ?? '#94A3B8') },
                    @endforeach
                ],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
            },
        });
    </script>
@endpush
