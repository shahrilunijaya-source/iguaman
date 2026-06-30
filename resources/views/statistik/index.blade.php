@extends('layouts.staff')

@section('title', 'Statistik')

@php
    $palette = ['#1a6fa8', '#0d2e48', '#5BC0BE', '#1B7F77', '#86E0D6', '#2A9D8F', '#0E7C7B', '#9AD9CF'];
    $bar = fn ($n, $max) => $max > 0 ? max(2, round($n / $max * 100)) : 0;

    // Donut geometry (stacked stroke-dasharray segments).
    $donutData = array_filter($byStatus);
    $donutTotal = array_sum($donutData);
    $r = 54; $circ = 2 * M_PI * $r;

    // Line geometry for monthly trend (oldest → newest).
    $series = array_reverse($byBulan, true);
    $vals = array_values($series);
    $labels = array_keys($series);
    $n = count($vals);
    $maxv = $n ? max($vals) : 0;
    $W = 580; $H = 190; $pl = 34; $pr = 12; $pt = 16; $pb = 26;
    $iw = $W - $pl - $pr; $ih = $H - $pt - $pb;
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $n > 1 ? $pl + $i / ($n - 1) * $iw : $pl + $iw / 2;
        $y = $pt + ($maxv ? (1 - $v / $maxv) : 0) * $ih;
        $pts[] = [round($x, 1), round($y, 1)];
    }
    $poly = implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $pts));
    $baseY = $pt + $ih;
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Statistik<span class="dot"></span></h1>
            <p class="tap-head__sub">Papan pemuka kes, pengantaraan &amp; panel peguam</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('statistik.excel', request()->query()) }}" class="tap-head__btn">⬇ Excel</a>
            <a href="{{ route('statistik.pdf', request()->query()) }}" class="tap-head__btn">⬇ PDF</a>
        </div>
    </div>

    <form method="GET" action="{{ route('statistik.index') }}" class="tap-filters">
        <select name="cawangan" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Cawangan</option>
            @foreach ($cawanganList as $c)
                <option value="{{ $c }}" @selected(($filters['cawangan'] ?? '') === $c)>{{ $c }}</option>
            @endforeach
        </select>
        @if (request()->hasAny(['cawangan', 'status', 'kategori']))
            <a href="{{ route('statistik.index') }}" class="tap-chip">✕ Reset</a>
        @endif
    </form>

    {{-- ===== KPI grid ===== --}}
    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:14px; margin-bottom:22px;">
        <div class="dash-kpi"><div class="dash-kpi__eyebrow">Jumlah Kes</div><div class="dash-kpi__value">{{ number_format($kpi['jumlah']) }}</div></div>
        <div class="dash-kpi is-ok"><div class="dash-kpi__eyebrow">Aktif</div><div class="dash-kpi__value">{{ number_format($kpi['aktif']) }}</div></div>
        <div class="dash-kpi"><div class="dash-kpi__eyebrow">Ditutup</div><div class="dash-kpi__value">{{ number_format($kpi['tutup']) }}</div></div>
        <div class="dash-kpi"><div class="dash-kpi__eyebrow">Pengantaraan</div><div class="dash-kpi__value">{{ number_format($kpi['pengantaraan']) }}</div></div>
        <div class="dash-kpi"><div class="dash-kpi__eyebrow">Diagih</div><div class="dash-kpi__value">{{ number_format($kpi['diagih']) }}</div></div>
        <div class="dash-kpi"><div class="dash-kpi__eyebrow">Belum Diagih</div><div class="dash-kpi__value">{{ number_format($kpi['belum_agih']) }}</div></div>
        <div class="dash-kpi"><div class="dash-kpi__eyebrow">Rekod OYD</div><div class="dash-kpi__value">{{ number_format($kpi['oyd']) }}</div></div>
        <div class="dash-kpi"><div class="dash-kpi__eyebrow">Peguam Panel</div><div class="dash-kpi__value">{{ number_format($kpi['peguam']) }}</div></div>
    </div>

    {{-- ===== Monthly trend line ===== --}}
    <div class="tap-card" style="margin-bottom:18px;">
        <div class="tap-card__eyebrow">Trend Permohonan Bulanan</div>
        @if ($n)
            <svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" preserveAspectRatio="xMidYMid meet" style="display:block;">
                <defs>
                    <linearGradient id="areaFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#1a6fa8" stop-opacity="0.28"/>
                        <stop offset="100%" stop-color="#1a6fa8" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                @for ($g = 0; $g <= 4; $g++)
                    @php $gy = $pt + $g / 4 * $ih; @endphp
                    <line x1="{{ $pl }}" y1="{{ round($gy, 1) }}" x2="{{ $W - $pr }}" y2="{{ round($gy, 1) }}" stroke="#eef2f1" stroke-width="1"/>
                    <text x="{{ $pl - 6 }}" y="{{ round($gy + 3, 1) }}" text-anchor="end" font-size="9" fill="#9aa">{{ round($maxv - $g / 4 * $maxv) }}</text>
                @endfor
                <polygon points="{{ $pl }},{{ $baseY }} {{ $poly }} {{ $pl + $iw }},{{ $baseY }}" fill="url(#areaFill)"/>
                <polyline points="{{ $poly }}" fill="none" stroke="#1a6fa8" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
                @foreach ($pts as $i => $p)
                    <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="3" fill="#fff" stroke="#0d2e48" stroke-width="2"/>
                    <text x="{{ $p[0] }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="8.5" fill="#778">{{ \Illuminate\Support\Str::afterLast($labels[$i], '-') }}</text>
                @endforeach
            </svg>
        @else
            <div class="dash-empty__sub" style="padding:10px 0;">Tiada data tarikh permohonan.</div>
        @endif
    </div>

    {{-- ===== Donut + Cawangan ===== --}}
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px; margin-bottom:18px;">
        <div class="tap-card">
            <div class="tap-card__eyebrow">Taburan Status</div>
            @if ($donutTotal)
                <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
                    <svg viewBox="0 0 140 140" width="150" height="150">
                        <g transform="rotate(-90 70 70)">
                            @php $off = 0; @endphp
                            @foreach ($donutData as $label => $val)
                                @php $len = $donutTotal ? $val / $donutTotal * $circ : 0; @endphp
                                <circle cx="70" cy="70" r="{{ $r }}" fill="none" stroke="{{ $palette[$loop->index % count($palette)] }}"
                                        stroke-width="17" stroke-dasharray="{{ round($len, 2) }} {{ round($circ - $len, 2) }}" stroke-dashoffset="{{ round(-$off, 2) }}"/>
                                @php $off += $len; @endphp
                            @endforeach
                        </g>
                        <text x="70" y="66" text-anchor="middle" font-size="22" font-weight="700" fill="#0d2e48">{{ $donutTotal }}</text>
                        <text x="70" y="84" text-anchor="middle" font-size="9" fill="#889" letter-spacing="1">KES</text>
                    </svg>
                    <div style="flex:1; min-width:120px;">
                        @foreach ($donutData as $label => $val)
                            <div style="display:flex; align-items:center; gap:8px; margin:5px 0; font-size:12px;">
                                <span style="width:10px; height:10px; border-radius:2px; background:{{ $palette[$loop->index % count($palette)] }}; flex:none;"></span>
                                <span style="flex:1; color:var(--ink);">{{ $label }}</span>
                                <span class="tabular" style="color:var(--mute); font-weight:600;">{{ number_format($val) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="dash-empty__sub" style="padding:10px 0;">Tiada data status.</div>
            @endif
        </div>

        @include('statistik.partials.bars', ['title' => 'Mengikut Cawangan', 'data' => $byCawangan])
    </div>

    {{-- ===== Bar breakdowns ===== --}}
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px;">
        @include('statistik.partials.bars', ['title' => 'Mengikut Kategori', 'data' => $byKategori])
        @include('statistik.partials.bars', ['title' => 'Mengikut Jenis Kes', 'data' => $byJenis])
        @include('statistik.partials.bars', ['title' => 'Mengikut Keputusan', 'data' => $byKeputusan])
        @include('statistik.partials.bars', ['title' => 'Cara Selesai (Pengantaraan)', 'data' => $byCaraSelesai])
    </div>
@endsection
