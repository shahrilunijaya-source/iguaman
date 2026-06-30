@extends('layouts.staff')

@section('title', 'Statistik Pemindahan — '.$tajuk)

@section('content')
<style>
    .sp-tabs { display:flex; gap:8px; margin-bottom:16px; }
    .sp-tab { padding:6px 14px; border:1px solid var(--line); border-radius:999px; font-size:13px; color:var(--mute); text-decoration:none; }
    .sp-tab.is-active { background:var(--pine-deep,#0d2e48); color:#fff; border-color:var(--pine-deep,#0d2e48); }
    .sp-bar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
    .sp-table { width:100%; border-collapse:collapse; font-size:12px; }
    .sp-table th, .sp-table td { padding:7px 8px; border-bottom:1px solid var(--line); text-align:center; }
    .sp-table th:first-child, .sp-table td:first-child { text-align:left; white-space:nowrap; font-weight:600; }
    .sp-table thead th { color:var(--mute); text-transform:uppercase; font-size:11px; letter-spacing:.03em; border-bottom:2px solid var(--line); }
    .sp-in { color:#10b981; font-weight:600; }
    .sp-out { color:var(--danger,#ef4444); font-weight:600; }
    .sp-zero { color:var(--line); }
    .sp-legend { font-size:12px; color:var(--mute); }
</style>

<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Statistik Pemindahan Cawangan<span class="dot"></span></h1>
        <p class="tap-head__sub">Bilangan kes/khidmat nasihat dipindah <span class="sp-in">masuk</span> / <span class="sp-out">keluar</span> setiap cawangan, mengikut bulan. Pemindahan ditolak tidak dikira.</p>
    </div>
</div>

<div class="sp-tabs">
    <a href="{{ route('kpi.pindah.kes', ['tahun' => $year]) }}" class="sp-tab {{ $aktif === 'kpi.pindah.kes' ? 'is-active' : '' }}">Kes (Litigasi)</a>
    <a href="{{ route('kpi.pindah.kn', ['tahun' => $year]) }}" class="sp-tab {{ $aktif === 'kpi.pindah.kn' ? 'is-active' : '' }}">Khidmat Nasihat</a>
</div>

<form method="GET" class="sp-bar">
    <label class="sp-legend" for="tahun">Tahun</label>
    <select name="tahun" id="tahun" class="field__input" style="max-width:120px;" onchange="this.form.submit()">
        @for ($y = now()->year; $y >= now()->year - 6; $y--)
            <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
        @endfor
    </select>
    <span class="sp-legend">Jumlah {{ $year }}: <span class="sp-in">▲ {{ $totals['masuk'] }} masuk</span> · <span class="sp-out">▼ {{ $totals['keluar'] }} keluar</span></span>
</form>

<div class="tap-card">
    @if (empty($matrix))
        <div class="dash-empty__sub" style="padding:12px 0;">Tiada pemindahan {{ strtolower($tajuk) }} bagi tahun {{ $year }}.</div>
    @else
        <table class="sp-table">
            <thead>
                <tr>
                    <th>Cawangan</th>
                    @foreach ($bulan as $b)
                        <th>{{ $b }}</th>
                    @endforeach
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($matrix as $cawangan => $bulanan)
                    @php $rowMasuk = 0; $rowKeluar = 0; @endphp
                    <tr>
                        <td>{{ $cawangan }}</td>
                        @for ($m = 1; $m <= 12; $m++)
                            @php
                                $cell = $bulanan[$m];
                                $rowMasuk += $cell['masuk'];
                                $rowKeluar += $cell['keluar'];
                            @endphp
                            <td>
                                @if ($cell['masuk'] || $cell['keluar'])
                                    <span class="sp-in">{{ $cell['masuk'] ?: '·' }}</span><span class="sp-zero">/</span><span class="sp-out">{{ $cell['keluar'] ?: '·' }}</span>
                                @else
                                    <span class="sp-zero">–</span>
                                @endif
                            </td>
                        @endfor
                        <td><span class="sp-in">{{ $rowMasuk }}</span><span class="sp-zero">/</span><span class="sp-out">{{ $rowKeluar }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="sp-legend" style="margin-top:12px;">Setiap sel: <span class="sp-in">masuk</span> / <span class="sp-out">keluar</span>.</p>
    @endif
</div>
@endsection
