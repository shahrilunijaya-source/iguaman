@extends('layouts.staff')

@section('title', 'Penetapan KPI')

@section('content')
    <div style="text-align:center; margin-bottom:6px;">
        <h1 class="tap-head__title" style="justify-content:center;">Penetapan Petunjuk Prestasi Utama (KPI)<span class="dot"></span></h1>
        <p class="tap-head__sub">Tahun: <strong>{{ $year }}</strong></p>
    </div>

    <form method="GET" action="{{ route('kpi.index') }}" style="display:flex; gap:10px; justify-content:center; align-items:center; margin-bottom:22px;">
        <label class="field__label" style="margin:0;">Tahun</label>
        <input type="number" name="tahun" value="{{ $year }}" min="2000" max="2100" class="field__input" style="width:120px;">
        <button type="submit" class="btn btn--primary">Cari</button>
        <a href="{{ route('kpi.index') }}" class="btn btn--ghost">Set Semula</a>
    </form>

    <div style="display:flex; flex-direction:column; gap:18px;">
        @foreach ($kpis as $kpi)
            <div class="tap-card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:6px;">
                    <div>
                        <div class="tap-card__eyebrow" style="margin-bottom:4px;">{{ $kpi['def']['label'] }}</div>
                        <p class="dash-empty__sub" style="margin:0; max-width:760px;">{{ $kpi['def']['desc'] }}</p>
                    </div>
                    <div style="text-align:right; flex:none;">
                        @if ($kpi['achieved'] !== null)
                            <div class="dash-kpi__value" style="font-size:24px; color:{{ $kpi['achieved'] >= 100 ? 'var(--success)' : ($kpi['achieved'] >= 80 ? '#C98A00' : 'var(--danger)') }};">{{ $kpi['achieved'] }}%</div>
                            <div class="dash-empty__sub" style="margin:0;">{{ number_format($kpi['total']) }} kes</div>
                        @else
                            <div class="dash-empty__sub" style="margin:0;">Tiada data {{ $year }}</div>
                        @endif
                    </div>
                </div>
                @include('kpi.partials.chart', ['kpi' => $kpi])
            </div>
        @endforeach
    </div>
@endsection
