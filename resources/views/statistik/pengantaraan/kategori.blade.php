@extends('layouts.staff')

@section('title', 'Penugasan Pengantaraan - Kategori')

@section('content')
    @php $qs = array_filter(['tahun' => $year]); @endphp
    <div class="tap-head">
        <div>
            <a href="{{ route('statistik-pengantaraan.index') }}" class="dash-empty__sub" style="text-decoration:none;">← Statistik Pengantaraan</a>
            <h1 class="tap-head__title">Penugasan mengikut Kategori<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $year ?: 'Semua tahun' }}
                · Keseluruhan <strong>{{ number_format($data['total']['jumlah']) }}</strong> penugasan
            </p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('statistik-pengantaraan.pdf', ['jenis' => 'kategori'] + $qs) }}" class="tap-head__btn">⬇ PDF</a>
        </div>
    </div>

    <form method="GET" action="{{ route('statistik-pengantaraan.kategori') }}" class="tap-filters">
        <label class="field__label" style="margin:0;">Tahun</label>
        <input type="number" name="tahun" value="{{ $year }}" min="2000" max="2100" placeholder="Semua" aria-label="Semua" class="field__input" style="width:120px;">
        <button type="submit" class="btn btn--primary">Tapis</button>
        @if ($year)
            <a href="{{ route('statistik-pengantaraan.kategori') }}" class="tap-chip">✕ Reset</a>
        @endif
    </form>

    <div class="tap-card" style="overflow-x:auto;">
        @include('statistik.pengantaraan._kategori_table', ['data' => $data, 'branches' => $branches])
    </div>
@endsection
