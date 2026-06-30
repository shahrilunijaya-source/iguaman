{{--
    Shared SLA matrix table. Fully inline-styled so it renders identically in the
    browser and in dompdf (which has no access to the compiled app CSS).
    Expects: $data (SlaMatrix::compute result), $branches, $kategori.
--}}
@php
    $drill = $drill ?? false; // browser-only: linkify TIDAK counts to the breach senarai CSV. Off in PDF.
    $key = $key ?? null;
    $qs = $qs ?? [];
    $pct = fn ($cell) => $cell['peratus'] === null ? '–' : number_format($cell['peratus'], 2).'%';
    $th = 'border:1px solid #cdd6d4; padding:5px 7px; font-size:10px; text-align:center; background:#003D3A; color:#fff; font-weight:700;';
    $td = 'border:1px solid #d7dedc; padding:4px 7px; font-size:10px; text-align:center;';
    $tdL = 'border:1px solid #d7dedc; padding:4px 7px; font-size:10px; text-align:left; white-space:nowrap;';
    $foot = 'border:1px solid #b9c6c3; padding:5px 7px; font-size:10px; text-align:center; background:#e7f3f1; font-weight:700;';
    $footL = 'border:1px solid #b9c6c3; padding:5px 7px; font-size:10px; text-align:left; background:#e7f3f1; font-weight:700;';
@endphp
<table style="border-collapse:collapse; width:100%; font-family:Arial, sans-serif;">
    <thead>
        <tr>
            <th rowspan="2" style="{{ $th }}">BIL.</th>
            <th rowspan="2" style="{{ $th }} text-align:left;">CAWANGAN</th>
            @foreach ($kategori as $k)
                <th colspan="3" style="{{ $th }}">{{ strtoupper($k) }}</th>
            @endforeach
        </tr>
        <tr>
            @foreach ($kategori as $k)
                <th style="{{ $th }}">CAPAI</th>
                <th style="{{ $th }}">TIDAK</th>
                <th style="{{ $th }}">%</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($branches as $i => $b)
            <tr style="{{ $i % 2 ? 'background:#f6faf9;' : '' }}">
                <td style="{{ $td }}">{{ $i + 1 }}</td>
                <td style="{{ $tdL }}">{{ $b }}</td>
                @foreach ($kategori as $k)
                    @php $c = $data['matrix'][$b][$k]; @endphp
                    <td style="{{ $td }}">{{ $c['capai'] }}</td>
                    @if ($drill && $key && $c['tidak'] > 0)
                        <td style="{{ $td }}"><a href="{{ route('statistik-sla.senarai', ['key' => $key, 'cawangan' => $b, 'kategori' => $k] + $qs) }}" style="color:#b3261e; font-weight:700; text-decoration:none;" title="Muat turun senarai kes TIDAK CAPAI">{{ $c['tidak'] }}</a></td>
                    @else
                        <td style="{{ $td }}">{{ $c['tidak'] }}</td>
                    @endif
                    <td style="{{ $td }}">{{ $pct($c) }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2" style="{{ $footL }}">JUMLAH KESELURUHAN</td>
            @foreach ($kategori as $k)
                @php $j = $data['jumlah'][$k]; @endphp
                <td style="{{ $foot }}">{{ $j['capai'] }}</td>
                <td style="{{ $foot }}">{{ $j['tidak'] }}</td>
                <td style="{{ $foot }}">{{ $pct($j) }}</td>
            @endforeach
        </tr>
    </tfoot>
</table>
