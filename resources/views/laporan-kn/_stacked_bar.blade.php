{{-- Stacked-bar chart for a month pivot. Inputs: $pivot, $canvasId, $ariaLabel. --}}
@php
    $monthLabels = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
    $palette = ['#00B8A9', '#0083B0', '#F6A623', '#7B61FF', '#E1495B', '#2DBE6C', '#94A3B8', '#FF8A5B', '#3F8EFC', '#B25FD0', '#6FCF97', '#F2C94C'];
    $datasets = [];
    foreach (array_values($pivot) as $i => $r) {
        $datasets[] = [
            'label' => $r['label'],
            'data' => array_values($r['months']),
            'backgroundColor' => $palette[$i % count($palette)],
        ];
    }
@endphp

<div class="tap-card" style="padding:16px; min-height:320px; margin-bottom:16px;">
    <canvas id="{{ $canvasId }}" role="img" aria-label="{{ $ariaLabel }}"></canvas>
</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById(@json($canvasId)), {
            type: 'bar',
            data: { labels: @json($monthLabels), datasets: @json($datasets) },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
            },
        });
    </script>
@endpush
