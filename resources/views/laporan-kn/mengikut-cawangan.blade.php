@extends('layouts.staff')

@section('title', 'Mengikut Cawangan')

@section('content')
    @include('laporan-kn._print_css')

    <div class="tap-nav js-no-print" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('laporan-kn.index') }}" class="tap-nav__back">← Laporan KN</a>
        <span class="tap-nav__crumb">Mengikut Cawangan</span>
    </div>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Mengikut Cawangan<span class="dot"></span></h1>
            <p class="tap-head__sub">Bilangan permohonan KN mengikut cawangan × bulan ({{ $filters['tahun'] ?? now()->year }}).</p>
        </div>
    </div>

    @include('laporan-kn._filters', ['routeName' => 'laporan-kn.mengikut-cawangan', 'show' => ['tahun', 'kategori']])

    @include('laporan-kn._stacked_bar', ['canvasId' => 'cawanganChart', 'ariaLabel' => 'Carta bar bertindan permohonan mengikut cawangan'])
    @include('laporan-kn._month_pivot_table', ['rowHeading' => 'Cawangan'])
@endsection
