{{--
    Pencapaian Penugasan Pengantaraan — branch × 3 KPI formulas, each [denominator,
    numerator, %]. Inline-styled for browser + dompdf parity. Expects $data, $branches.
    Per-group column order = denominator, numerator, peratus (legacy display order).
--}}
@php
    $th = 'border:1px solid #cdd6d4; padding:4px 4px; font-size:8px; text-align:center; background:#0d2e48; color:#fff; font-weight:700;';
    $td = 'border:1px solid #d7dedc; padding:4px 5px; font-size:9px; text-align:center;';
    $tdL = 'border:1px solid #d7dedc; padding:4px 6px; font-size:9px; text-align:left; white-space:nowrap;';
    $tdP = 'border:1px solid #d7dedc; padding:4px 5px; font-size:9px; text-align:center; font-weight:700; background:#f2faf8;';
    $foot = 'border:1px solid #b9c6c3; padding:5px 5px; font-size:9px; text-align:center; background:#e7f3f1; font-weight:700;';
    $footL = 'border:1px solid #b9c6c3; padding:5px 6px; font-size:9px; text-align:left; background:#e7f3f1; font-weight:700;';
    $pct = fn ($v) => number_format($v, 2).'%';
@endphp
<table style="border-collapse:collapse; width:100%; font-family:Arial, sans-serif;">
    <thead>
        <tr>
            <th rowspan="2" style="{{ $th }}">BIL.</th>
            <th rowspan="2" style="{{ $th }} text-align:left;">CAWANGAN</th>
            <th colspan="3" style="{{ $th }}">JUMLAH PERBANDINGAN PENUGASAN PENGANTARAAN DENGAN PERAKUAN BANTUAN GUAMAN</th>
            <th colspan="3" style="{{ $th }}">JUMLAH PERBANDINGAN SIDANG PENGANTARAAN DENGAN PENUGASAN PENGANTARAAN</th>
            <th colspan="3" style="{{ $th }}">JUMLAH PERJANJIAN PENYELESAIAN PENGANTARAAN</th>
        </tr>
        <tr>
            <th style="{{ $th }}">JUMLAH PERAKUAN BANTUAN GUAMAN (BORANG II)</th>
            <th style="{{ $th }}">JUMLAH PENUGASAN PENGANTARAAN</th>
            <th style="{{ $th }}">PERATUS (%)</th>
            <th style="{{ $th }}">JUMLAH PENUGASAN PENGANTARAAN</th>
            <th style="{{ $th }}">JUMLAH SIDANG PENGANTARAAN yang DIKENDALIKAN</th>
            <th style="{{ $th }}">PERATUS (%)</th>
            <th style="{{ $th }}">JUMLAH SIDANG PENGANTARAAN yang DIKENDALIKAN</th>
            <th style="{{ $th }}">JUMLAH PERJANJIAN PENYELESAIAN YANG BERJAYA</th>
            <th style="{{ $th }}">PERATUS (%)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($branches as $i => $b)
            @php $c = $data['matrix'][$b]; @endphp
            <tr style="{{ $i % 2 ? 'background:#f6faf9;' : '' }}">
                <td style="{{ $td }}">{{ $i + 1 }}</td>
                <td style="{{ $tdL }}">{{ $b }}</td>
                <td style="{{ $td }}">{{ $c['perakuan'] }}</td>
                <td style="{{ $td }}">{{ $c['penugasan'] }}</td>
                <td style="{{ $tdP }}">{{ $pct($c['f1']) }}</td>
                <td style="{{ $td }}">{{ $c['penugasan'] }}</td>
                <td style="{{ $td }}">{{ $c['rujuk_minta'] }}</td>
                <td style="{{ $tdP }}">{{ $pct($c['f2']) }}</td>
                <td style="{{ $td }}">{{ $c['rujuk_minta'] }}</td>
                <td style="{{ $td }}">{{ $c['selesai'] }}</td>
                <td style="{{ $tdP }}">{{ $pct($c['f3']) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        @php $t = $data['total']; @endphp
        <tr>
            <td colspan="2" style="{{ $footL }}">JUMLAH KESELURUHAN</td>
            <td style="{{ $foot }}">{{ $t['perakuan'] }}</td>
            <td style="{{ $foot }}">{{ $t['penugasan'] }}</td>
            <td style="{{ $foot }}">{{ $pct($t['f1']) }}</td>
            <td style="{{ $foot }}">{{ $t['penugasan'] }}</td>
            <td style="{{ $foot }}">{{ $t['rujuk_minta'] }}</td>
            <td style="{{ $foot }}">{{ $pct($t['f2']) }}</td>
            <td style="{{ $foot }}">{{ $t['rujuk_minta'] }}</td>
            <td style="{{ $foot }}">{{ $t['selesai'] }}</td>
            <td style="{{ $foot }}">{{ $pct($t['f3']) }}</td>
        </tr>
    </tfoot>
</table>
