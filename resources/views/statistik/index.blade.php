@extends('layouts.staff')

@section('title', 'Statistik')

@php
    $bar = fn ($n, $max) => $max > 0 ? max(2, round($n / $max * 100)) : 0;
    $renderRows = function (array $data) use ($bar) {
        $max = count($data) ? max($data) : 0;
        return [$data, $max, $bar];
    };
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Statistik<span class="dot"></span></h1>
            <p class="tap-head__sub">Ringkasan kes &amp; pengantaraan</p>
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

    <div class="dash-kpis" style="margin-bottom:24px;">
        <div class="dash-kpi">
            <div class="dash-kpi__eyebrow">Jumlah Kes</div>
            <div class="dash-kpi__value">{{ number_format($kpi['jumlah']) }}</div>
        </div>
        <div class="dash-kpi is-ok">
            <div class="dash-kpi__eyebrow">Aktif</div>
            <div class="dash-kpi__value">{{ number_format($kpi['aktif']) }}</div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi__eyebrow">Ditutup</div>
            <div class="dash-kpi__value">{{ number_format($kpi['tutup']) }}</div>
        </div>
        <div class="dash-kpi">
            <div class="dash-kpi__eyebrow">Pengantaraan</div>
            <div class="dash-kpi__value">{{ number_format($kpi['pengantaraan']) }}</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px;">
        @foreach (['Mengikut Cawangan' => $byCawangan, 'Mengikut Kategori' => $byKategori, 'Mengikut Status' => $byStatus, 'Mengikut Bulan (Permohonan)' => $byBulan] as $title => $data)
            <div class="tap-card">
                <div class="tap-card__eyebrow">{{ $title }}</div>
                @php $max = count($data) ? max($data) : 0; @endphp
                @forelse ($data as $label => $n)
                    <div style="margin:8px 0;">
                        <div style="display:flex; justify-content:space-between; font-size:12.5px; margin-bottom:4px;">
                            <span style="color:var(--ink); font-weight:500;">{{ $label }}</span>
                            <span class="tabular" style="color:var(--mute); font-weight:600;">{{ number_format($n) }}</span>
                        </div>
                        <div style="height:6px; background:var(--paper-2); border-radius:3px; overflow:hidden;">
                            <div style="height:100%; width:{{ $bar($n, $max) }}%; background:var(--teal); border-radius:3px;"></div>
                        </div>
                    </div>
                @empty
                    <div class="dash-empty__sub" style="padding:6px 0;">Tiada data.</div>
                @endforelse
            </div>
        @endforeach
    </div>
@endsection
