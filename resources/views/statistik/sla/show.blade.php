@extends('layouts.staff')

@section('title', $data['def']['label'])

@section('content')
    @php
        $bulanNama = [1 => 'Januari', 2 => 'Februari', 3 => 'Mac', 4 => 'April', 5 => 'Mei', 6 => 'Jun', 7 => 'Julai', 8 => 'Ogos', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Disember'];
        $qs = array_filter(['tahun' => $year, 'bulan' => $month]);
        $tempoh = ($month ? $bulanNama[$month].' ' : '').($year ? $year : (! $month ? 'Semua tahun' : 'semua tahun'));
    @endphp
    <div class="tap-head">
        <div>
            <a href="{{ route('statistik-sla.index') }}" class="dash-empty__sub" style="text-decoration:none;">← Statistik SLA</a>
            <h1 class="tap-head__title">{{ $data['def']['label'] }}<span class="dot"></span></h1>
            <p class="tap-head__sub">SLA {{ $data['def']['target'] }} hari · {{ $tempoh }}
                @if ($data['grand']['peratus'] !== null)
                    · Keseluruhan <strong>{{ number_format($data['grand']['peratus'], 2) }}%</strong> ({{ number_format($data['grand']['total']) }} kes)
                @endif
            </p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('statistik-sla.senarai', ['key' => $key] + $qs) }}" class="tap-head__btn">⬇ Senarai TIDAK CAPAI (CSV)</a>
            <a href="{{ route('statistik-sla.pdf', ['key' => $key] + $qs) }}" class="tap-head__btn">⬇ PDF</a>
        </div>
    </div>

    <form method="GET" action="{{ route('statistik-sla.show', $key) }}" class="tap-filters">
        <label class="field__label" style="margin:0;">Tahun</label>
        <input type="number" name="tahun" value="{{ $year }}" min="2000" max="2100" placeholder="Semua" class="field__input" style="width:120px;">
        <select name="bulan" class="tap-chip">
            <option value="">Semua Bulan</option>
            @foreach ($bulanNama as $n => $nama)
                <option value="{{ $n }}" @selected($month === $n)>{{ $nama }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn--primary">Tapis</button>
        @if ($year || $month)
            <a href="{{ route('statistik-sla.show', $key) }}" class="tap-chip">✕ Reset</a>
        @endif
    </form>

    <div class="tap-card" style="overflow-x:auto;">
        @include('statistik.sla._table', ['data' => $data, 'branches' => $branches, 'kategori' => $kategori, 'drill' => true, 'key' => $key, 'qs' => $qs])
    </div>
@endsection
