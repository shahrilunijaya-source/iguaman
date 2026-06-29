@extends('layouts.staff')

@section('title', 'Statistik SLA')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Statistik SLA<span class="dot"></span></h1>
            <p class="tap-head__sub">Matriks pencapaian SLA mengikut cawangan (semua {{ count(\App\Support\SlaMatrix::BRANCHES) }} cawangan)</p>
        </div>
    </div>

    <form method="GET" action="{{ route('statistik-sla.index') }}" class="tap-filters">
        <label class="field__label" style="margin:0;">Tahun</label>
        <input type="number" name="tahun" value="{{ $year }}" min="2000" max="2100" placeholder="Semua" class="field__input" style="width:130px;">
        <button type="submit" class="btn btn--primary">Tapis</button>
        @if ($year)
            <a href="{{ route('statistik-sla.index') }}" class="tap-chip">✕ Reset</a>
        @endif
    </form>

    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
        @foreach ($defs as $slug => $def)
            <a href="{{ route('statistik-sla.show', ['key' => $slug] + ($year ? ['tahun' => $year] : [])) }}" class="tap-card" style="text-decoration:none; color:inherit; display:block;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                    <div class="tap-card__eyebrow" style="margin-bottom:6px;">{{ $def['label'] }}</div>
                    <span class="dash-kpi__eyebrow" style="flex:none; background:var(--brand,#00B8A9); color:#fff; padding:2px 9px; border-radius:999px; font-weight:700;">{{ $def['target'] }} hari</span>
                </div>
                <p class="dash-empty__sub" style="margin:0;">{{ $def['desc'] }}</p>
                <div class="dash-empty__sub" style="margin-top:10px; color:var(--brand,#00B8A9); font-weight:600;">Lihat matriks →</div>
            </a>
        @endforeach
    </div>
@endsection
