@extends('layouts.staff')

@section('title', 'Pencapaian Penugasan Pengantaraan')

@section('content')
    @php $qs = array_filter(['tahun' => $year, 'kategori' => $kategori]); @endphp
    <div class="tap-head">
        <div>
            <a href="{{ route('statistik-pengantaraan.index') }}" class="dash-empty__sub" style="text-decoration:none;">← Statistik Pengantaraan</a>
            <h1 class="tap-head__title">Pencapaian Penugasan Pengantaraan<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $year ?: 'Semua tahun' }}{{ $kategori ? ' · '.$kategori : '' }}
                · Keseluruhan F1 <strong>{{ number_format($data['total']['f1'], 2) }}%</strong>
                · F2 <strong>{{ number_format($data['total']['f2'], 2) }}%</strong>
                · F3 <strong>{{ number_format($data['total']['f3'], 2) }}%</strong>
            </p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('statistik-pengantaraan.pdf', ['jenis' => 'pencapaian'] + $qs) }}" class="tap-head__btn">⬇ PDF</a>
        </div>
    </div>

    <p class="dash-empty__sub" style="margin:0 0 12px;">Maklumat fail yang melalui proses pemindahan fail ke cawangan lain termasuk dalam pengiraan KPI.</p>

    <form method="GET" action="{{ route('statistik-pengantaraan.pencapaian') }}" class="tap-filters">
        <label class="field__label" style="margin:0;">Tahun</label>
        <input type="number" name="tahun" value="{{ $year }}" min="2000" max="2100" placeholder="Semua" class="field__input" style="width:120px;">
        <select name="kategori" class="tap-chip">
            <option value="">Semua Kategori</option>
            <option value="Sivil" @selected($kategori === 'Sivil')>Sivil</option>
            <option value="Syariah" @selected($kategori === 'Syariah')>Syariah</option>
        </select>
        <button type="submit" class="btn btn--primary">Tapis</button>
        @if ($year || $kategori)
            <a href="{{ route('statistik-pengantaraan.pencapaian') }}" class="tap-chip">✕ Reset</a>
        @endif
    </form>

    <div class="tap-card" style="overflow-x:auto;">
        @include('statistik.pengantaraan._pencapaian_table', ['data' => $data, 'branches' => $branches])
    </div>
@endsection
