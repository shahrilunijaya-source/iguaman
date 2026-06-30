{{--
    Penugasan Pengantaraan — branch × [Sivil, Syariah, Jumlah]. Inline-styled so
    it renders identically in the browser and in dompdf. Expects $data, $branches.
--}}
@php
    $th = 'border:1px solid #cdd6d4; padding:5px 7px; font-size:10px; text-align:center; background:#0d2e48; color:#fff; font-weight:700;';
    $td = 'border:1px solid #d7dedc; padding:4px 7px; font-size:10px; text-align:center;';
    $tdL = 'border:1px solid #d7dedc; padding:4px 7px; font-size:10px; text-align:left; white-space:nowrap;';
    $foot = 'border:1px solid #b9c6c3; padding:5px 7px; font-size:10px; text-align:center; background:#e7f3f1; font-weight:700;';
    $footL = 'border:1px solid #b9c6c3; padding:5px 7px; font-size:10px; text-align:left; background:#e7f3f1; font-weight:700;';
@endphp
<table style="border-collapse:collapse; width:100%; font-family:Arial, sans-serif;">
    <thead>
        <tr>
            <th style="{{ $th }}">BIL.</th>
            <th style="{{ $th }} text-align:left;">CAWANGAN</th>
            <th style="{{ $th }}">PENGANTARAAN SIVIL</th>
            <th style="{{ $th }}">PENGANTARAAN SYARIAH</th>
            <th style="{{ $th }}">JUMLAH PENUGASAN PENGANTARAAN</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($branches as $i => $b)
            @php $c = $data['matrix'][$b]; @endphp
            <tr style="{{ $i % 2 ? 'background:#f6faf9;' : '' }}">
                <td style="{{ $td }}">{{ $i + 1 }}</td>
                <td style="{{ $tdL }}">{{ $b }}</td>
                <td style="{{ $td }}">{{ $c['sivil'] }}</td>
                <td style="{{ $td }}">{{ $c['syariah'] }}</td>
                <td style="{{ $td }}">{{ $c['jumlah'] }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2" style="{{ $footL }}">JUMLAH</td>
            <td style="{{ $foot }}">{{ $data['total']['sivil'] }}</td>
            <td style="{{ $foot }}">{{ $data['total']['syariah'] }}</td>
            <td style="{{ $foot }}">{{ $data['total']['jumlah'] }}</td>
        </tr>
    </tfoot>
</table>
