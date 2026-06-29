{{-- KPI monthly chart. Param: $kpi (compute() output). Per month, per type: stacked met/missed bar (y = %). --}}
@php
    $colors = ['Sivil' => '#00B8A9', 'Syariah' => '#1B7F77', 'Jenayah' => '#C98A00', 'Pendamping Guaman' => '#7A5AF8'];
    $bulan = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
    $types = $kpi['def']['types'];
    $nT = count($types);

    $W = 900; $H = 240; $padL = 36; $padR = 10; $padT = 10; $padB = 28;
    $innerW = $W - $padL - $padR; $innerH = $H - $padT - $padB;
    $slotW = $innerW / 12;
    $groupW = $slotW * 0.72;
    $barW = $groupW / max($nT, 1);
@endphp

<svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" preserveAspectRatio="xMidYMid meet" style="display:block;">
    {{-- gridlines + y labels --}}
    @foreach ([0, 25, 50, 75, 100] as $g)
        @php $gy = $padT + (1 - $g / 100) * $innerH; @endphp
        <line x1="{{ $padL }}" y1="{{ round($gy, 1) }}" x2="{{ $W - $padR }}" y2="{{ round($gy, 1) }}" stroke="#eef2f1" stroke-width="1"/>
        <text x="{{ $padL - 6 }}" y="{{ round($gy + 3, 1) }}" text-anchor="end" font-size="9" fill="#9aa">{{ $g }}</text>
    @endforeach

    @for ($m = 1; $m <= 12; $m++)
        @php $slotX = $padL + ($m - 1) * $slotW + ($slotW - $groupW) / 2; @endphp
        @foreach ($types as $i => $t)
            @php
                $cell = $kpi['matrix'][$t][$m];
                $tot = $cell['met'] + $cell['missed'];
                $x = round($slotX + $i * $barW, 1);
                $bw = round($barW * 0.82, 1);
            @endphp
            @if ($tot > 0)
                @php
                    $metFrac = $cell['met'] / $tot;
                    $metH = round($innerH * $metFrac, 1);
                    $missH = round($innerH * (1 - $metFrac), 1);
                    $metY = round($padT + $innerH - $metH, 1);
                @endphp
                <rect x="{{ $x }}" y="{{ $padT }}" width="{{ $bw }}" height="{{ round($missH, 1) }}" fill="#e0e7e5"/>
                <rect x="{{ $x }}" y="{{ $metY }}" width="{{ $bw }}" height="{{ $metH }}" fill="{{ $colors[$t] ?? '#999' }}"/>
            @endif
        @endforeach
        <text x="{{ round($padL + ($m - 1) * $slotW + $slotW / 2, 1) }}" y="{{ $H - 9 }}" text-anchor="middle" font-size="8.5" fill="#778">{{ $bulan[$m - 1] }}</text>
    @endfor
</svg>

<div style="display:flex; flex-wrap:wrap; gap:10px 16px; margin-top:10px; font-size:11.5px;">
    @foreach ($types as $t)
        <span style="display:inline-flex; align-items:center; gap:6px;">
            <span style="width:11px; height:11px; border-radius:2px; background:{{ $colors[$t] ?? '#999' }};"></span>
            {{ $t }} ≤{{ $kpi['def']['target'] }} hari
        </span>
    @endforeach
    <span style="display:inline-flex; align-items:center; gap:6px;">
        <span style="width:11px; height:11px; border-radius:2px; background:#e0e7e5;"></span>
        Melebihi tempoh
    </span>
</div>
