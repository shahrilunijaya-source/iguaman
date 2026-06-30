@extends('layouts.staff')

@section('title', 'Mengikut Kategori Kes')

@section('content')
    @include('laporan-kn._print_css')

    <div class="tap-nav js-no-print" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('laporan-kn.index') }}" class="tap-nav__back">← Laporan KN</a>
        <span class="tap-nav__crumb">Mengikut Kategori Kes</span>
    </div>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Mengikut Kategori Kes<span class="dot"></span></h1>
            <p class="tap-head__sub">Bilangan permohonan KN mengikut kategori × bulan ({{ $filters['tahun'] ?? now()->year }}).</p>
        </div>
    </div>

    @include('laporan-kn._filters', ['routeName' => 'laporan-kn.mengikut-kategori', 'show' => ['cawangan', 'tahun']])

    @include('laporan-kn._stacked_bar', ['canvasId' => 'kategoriChart', 'ariaLabel' => 'Carta bar bertindan permohonan mengikut kategori'])
    @include('laporan-kn._month_pivot_table', ['rowHeading' => 'Kategori'])
@endsection
