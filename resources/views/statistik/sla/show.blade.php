@extends('layouts.staff')

@section('title', $data['def']['label'])

@section('content')
    <div class="tap-head">
        <div>
            <a href="{{ route('statistik-sla.index') }}" class="dash-empty__sub" style="text-decoration:none;">← Statistik SLA</a>
            <h1 class="tap-head__title">{{ $data['def']['label'] }}<span class="dot"></span></h1>
            <p class="tap-head__sub">SLA {{ $data['def']['target'] }} hari · {{ $year ? 'Tahun '.$year : 'Semua tahun' }}
                @if ($data['grand']['peratus'] !== null)
                    · Keseluruhan <strong>{{ number_format($data['grand']['peratus'], 2) }}%</strong> ({{ number_format($data['grand']['total']) }} kes)
                @endif
            </p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('statistik-sla.pdf', ['key' => $key] + ($year ? ['tahun' => $year] : [])) }}" class="tap-head__btn">⬇ PDF</a>
        </div>
    </div>

    <form method="GET" action="{{ route('statistik-sla.show', $key) }}" class="tap-filters">
        <label class="field__label" style="margin:0;">Tahun</label>
        <input type="number" name="tahun" value="{{ $year }}" min="2000" max="2100" placeholder="Semua" class="field__input" style="width:130px;">
        <button type="submit" class="btn btn--primary">Tapis</button>
        @if ($year)
            <a href="{{ route('statistik-sla.show', $key) }}" class="tap-chip">✕ Reset</a>
        @endif
    </form>

    <div class="tap-card" style="overflow-x:auto;">
        @include('statistik.sla._table', ['data' => $data, 'branches' => $branches, 'kategori' => $kategori])
    </div>
@endsection
