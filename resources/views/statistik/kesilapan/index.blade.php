@extends('layouts.staff')

@section('title', 'Kesilapan Nombor Fail')

@php
    $th = 'border:1px solid #cdd6d4; padding:5px 6px; font-size:10px; text-align:center; background:#0d2e48; color:#fff; font-weight:700;';
    $td = 'border:1px solid #d7dedc; padding:4px 6px; font-size:10px; text-align:center;';
    $tdL = 'border:1px solid #d7dedc; padding:4px 7px; font-size:10px; text-align:left; white-space:nowrap;';
    $foot = 'border:1px solid #b9c6c3; padding:5px 6px; font-size:10px; text-align:center; background:#e7f3f1; font-weight:700;';
    $footL = 'border:1px solid #b9c6c3; padding:5px 7px; font-size:10px; text-align:left; background:#e7f3f1; font-weight:700;';
    $qs = array_filter(['tahun' => $year, 'kategori' => $kategori]);
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Kesilapan Penjanaan Nombor Fail<span class="dot"></span></h1>
            <p class="tap-head__sub">Fail ditutup atas sebab kesilapan menjana nombor fail · {{ $year }} · <strong>{{ number_format($data['grand']) }}</strong> rekod</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('statistik-kesilapan.csv', $qs) }}" class="tap-head__btn">⬇ CSV</a>
        </div>
    </div>

    <form method="GET" action="{{ route('statistik-kesilapan.index') }}" class="tap-filters">
        <label class="field__label" style="margin:0;">Tahun</label>
        <input type="number" name="tahun" value="{{ $year }}" min="2000" max="2100" class="field__input" style="width:120px;">
        <select name="kategori" class="tap-chip">
            <option value="">Semua Kategori</option>
            @foreach ($kategoriList as $k)
                <option value="{{ $k }}" @selected($kategori === $k)>{{ $k }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn--primary">Tapis</button>
        @if ($kategori)
            <a href="{{ route('statistik-kesilapan.index', ['tahun' => $year]) }}" class="tap-chip">✕ Reset</a>
        @endif
    </form>

    <div class="tap-card" style="overflow-x:auto;">
        <table style="border-collapse:collapse; width:100%; font-family:Arial, sans-serif;">
            <thead>
                <tr>
                    <th style="{{ $th }} text-align:left;">CAWANGAN</th>
                    @foreach ($bulan as $nama)
                        <th style="{{ $th }}">{{ strtoupper(substr($nama, 0, 3)) }}</th>
                    @endforeach
                    <th style="{{ $th }}">JUMLAH</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($branches as $i => $b)
                    <tr style="{{ $i % 2 ? 'background:#f6faf9;' : '' }}">
                        <td style="{{ $tdL }}">{{ $b }}</td>
                        @for ($m = 1; $m <= 12; $m++)
                            <td style="{{ $td }} {{ $data['matrix'][$b][$m] ? '' : 'color:#c2cdca;' }}">{{ $data['matrix'][$b][$m] ?: '·' }}</td>
                        @endfor
                        <td style="{{ $td }} font-weight:700;">{{ $data['matrix'][$b]['jumlah'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td style="{{ $footL }}">JUMLAH KESELURUHAN</td>
                    @for ($m = 1; $m <= 12; $m++)
                        <td style="{{ $foot }}">{{ $data['bulanan'][$m] }}</td>
                    @endfor
                    <td style="{{ $foot }}">{{ $data['grand'] }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endsection
