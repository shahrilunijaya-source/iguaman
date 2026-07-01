@extends('layouts.staff')

@section('title', 'Statistik Penugasan Pengantaraan')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Statistik Penugasan Pengantaraan<span class="dot"></span></h1>
            <p class="tap-head__sub">Matriks penugasan kes pengantaraan mengikut cawangan (semua {{ count(\App\Support\PengantaraanMatrix::BRANCHES) }} cawangan)</p>
        </div>
    </div>

    @php $qs = array_filter(['tahun' => $year]); @endphp

    <form method="GET" action="{{ route('statistik-pengantaraan.index') }}" class="tap-filters">
        <label class="field__label" style="margin:0;">Tahun</label>
        <input type="number" name="tahun" value="{{ $year }}" min="2000" max="2100" placeholder="Semua" aria-label="Semua" class="field__input" style="width:120px;">
        <button type="submit" class="btn btn--primary">Tapis</button>
        @if ($year)
            <a href="{{ route('statistik-pengantaraan.index') }}" class="tap-chip">✕ Reset</a>
        @endif
    </form>

    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
        <a href="{{ route('statistik-pengantaraan.kategori', $qs) }}" class="tap-card" style="text-decoration:none; color:inherit; display:block;">
            <div class="tap-card__eyebrow" style="margin-bottom:6px;">Penugasan mengikut Kategori</div>
            <p class="dash-empty__sub" style="margin:0;">Bilangan penugasan pengantaraan (Sivil / Syariah) setiap cawangan.</p>
            <div class="dash-empty__sub" style="margin-top:10px; color:var(--brand,#1a6fa8); font-weight:600;">Lihat matriks →</div>
        </a>
        <a href="{{ route('statistik-pengantaraan.bulanan', $qs) }}" class="tap-card" style="text-decoration:none; color:inherit; display:block;">
            <div class="tap-card__eyebrow" style="margin-bottom:6px;">Penugasan Bulanan</div>
            <p class="dash-empty__sub" style="margin:0;">Bilangan penugasan pengantaraan setiap bulan (Jan–Dis) setiap cawangan.</p>
            <div class="dash-empty__sub" style="margin-top:10px; color:var(--brand,#1a6fa8); font-weight:600;">Lihat matriks →</div>
        </a>
        <a href="{{ route('statistik-pengantaraan.pencapaian', $qs) }}" class="tap-card" style="text-decoration:none; color:inherit; display:block;">
            <div class="tap-card__eyebrow" style="margin-bottom:6px;">Pencapaian (KPI)</div>
            <p class="dash-empty__sub" style="margin:0;">Peratus pencapaian: penugasan vs perakuan, sidang vs penugasan, perjanjian penyelesaian.</p>
            <div class="dash-empty__sub" style="margin-top:10px; color:var(--brand,#1a6fa8); font-weight:600;">Lihat matriks →</div>
        </a>
    </div>
@endsection
