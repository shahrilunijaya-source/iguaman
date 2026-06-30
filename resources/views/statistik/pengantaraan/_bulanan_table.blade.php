{{--
    Penugasan Pengantaraan Bulanan — branch × 12 months + Jumlah. Inline-styled
    for browser + dompdf parity. Expects $data, $branches, $bulan (1..12 labels).
--}}
@php
    $th = 'border:1px solid #cdd6d4; padding:5px 5px; font-size:9px; text-align:center; background:#0d2e48; color:#fff; font-weight:700;';
    $td = 'border:1px solid #d7dedc; padding:4px 5px; font-size:9px; text-align:center;';
    $tdL = 'border:1px solid #d7dedc; padding:4px 6px; font-size:9px; text-align:left; white-space:nowrap;';
    $foot = 'border:1px solid #b9c6c3; padding:5px 5px; font-size:9px; text-align:center; background:#e7f3f1; font-weight:700;';
    $footL = 'border:1px solid #b9c6c3; padding:5px 6px; font-size:9px; text-align:left; background:#e7f3f1; font-weight:700;';
@endphp
<table style="border-collapse:collapse; width:100%; font-family:Arial, sans-serif;">
    <thead>
        <tr>
            <th style="{{ $th }}">BIL.</th>
            <th style="{{ $th }} text-align:left;">CAWANGAN</th>
            @foreach ($bulan as $nama)
                <th style="{{ $th }}">{{ strtoupper($nama) }}</th>
            @endforeach
            <th style="{{ $th }}">JUMLAH</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($branches as $i => $b)
            @php $c = $data['matrix'][$b]; @endphp
            <tr style="{{ $i % 2 ? 'background:#f6faf9;' : '' }}">
                <td style="{{ $td }}">{{ $i + 1 }}</td>
                <td style="{{ $tdL }}">{{ $b }}</td>
                @for ($m = 1; $m <= 12; $m++)
                    <td style="{{ $td }}">{{ $c[$m] }}</td>
                @endfor
                <td style="{{ $td }} font-weight:700;">{{ $c['jumlah'] }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2" style="{{ $footL }}">JUMLAH KESELURUHAN</td>
            @for ($m = 1; $m <= 12; $m++)
                <td style="{{ $foot }}">{{ $data['bulanan'][$m] }}</td>
            @endfor
            <td style="{{ $foot }}">{{ $data['grand'] }}</td>
        </tr>
    </tfoot>
</table>
