@extends('layouts.peguam')

@section('title', 'Dashboard')

@section('content')
    <div class="dash-greet">
        <div>
            <h1 class="dash-greet__h1">Salam, {{ $stats['nama'] }}.<span class="dot"></span></h1>
            <p class="dash-greet__sub">Ruang <strong>Peguam Panel</strong>. Kes yang ditugaskan kepada anda.</p>
        </div>
    </div>

    <div class="dash-sec">
        <div class="dash-sec__head">
            <span class="dash-sec__eyebrow">Ringkasan</span>
            <a href="{{ route('peguam.kes') }}" class="dash-sec__cta">Lihat kes saya →</a>
        </div>
        <div class="dash-kpis">
            <a href="{{ route('peguam.kes') }}" class="dash-kpi" style="text-decoration:none; color:inherit;">
                <div class="dash-kpi__eyebrow">Kes Saya</div>
                <div class="dash-kpi__value">{{ number_format($stats['kes_saya']) }}</div>
                <div class="dash-kpi__sub">ditugaskan</div>
            </a>
        </div>
    </div>
@endsection
