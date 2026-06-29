@extends('layouts.staff')

@section('title', 'Dashboard')

@section('content')
    <div class="dash-greet">
        <div>
            <h1 class="dash-greet__h1">Selamat datang, {{ auth()->user()->name }}.<span class="dot"></span></h1>
            <p class="dash-greet__sub">Ruang kerja <strong>iGuaman 2in1</strong> — rekod kes &amp; panel peguam dalam satu sistem.</p>
        </div>
    </div>

    <div class="dash-sec">
        <div class="dash-sec__head">
            <span class="dash-sec__eyebrow">Ringkasan</span>
            <a href="{{ route('kes.index') }}" class="dash-sec__cta">Lihat semua kes →</a>
        </div>
        <div class="dash-kpis">
            <a href="{{ route('kes.index') }}" class="dash-kpi" style="text-decoration:none; color:inherit;">
                <div class="dash-kpi__eyebrow">Jumlah Kes</div>
                <div class="dash-kpi__value">{{ number_format($stats['kes']) }}</div>
                <div class="dash-kpi__sub">{{ number_format($stats['kes_tutup']) }} fail ditutup</div>
            </a>
            <div class="dash-kpi is-ok">
                <div class="dash-kpi__eyebrow">Peguam Panel</div>
                <div class="dash-kpi__value">{{ number_format($stats['peguam']) }}</div>
                <div class="dash-kpi__sub">aktif dalam panel</div>
            </div>
            <div class="dash-kpi {{ $stats['mohon_peguam'] > 0 ? 'is-warn' : '' }}">
                <div class="dash-kpi__eyebrow">Permohonan Peguam</div>
                <div class="dash-kpi__value">{{ number_format($stats['mohon_peguam']) }}</div>
                <div class="dash-kpi__sub">menunggu keputusan</div>
            </div>
            <div class="dash-kpi">
                <div class="dash-kpi__eyebrow">Pengguna Staf</div>
                <div class="dash-kpi__value">{{ number_format($stats['pengguna']) }}</div>
                <div class="dash-kpi__sub">akaun dalaman</div>
            </div>
        </div>
    </div>

    <div class="dash-empty">
        <div class="dash-empty__title">Modul akan datang<span class="dot"></span></div>
        <div class="dash-empty__sub">
            Senarai Kes kini aktif. Seterusnya: permohonan (5-peringkat), pengantaraan, kes mahkamah, statistik (Phase 3b–d), dan panel peguam (Phase 4).
        </div>
    </div>
@endsection
