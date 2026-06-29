<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Utama · iGuaman 2in1</title>
    @vite(['resources/css/system.css'])
</head>
<body class="system">

<div class="ws-app">

    <header class="ws-topbar">
        <a href="{{ route('system.utama') }}" class="ws-topbar__brand"><span class="i">i</span>Guaman</a>
        <span class="ws-topbar__sep"></span>
        <span class="ws-topbar__context">2in1 · Ruang Pegawai</span>
        <span class="ws-topbar__spacer"></span>

        <div class="ws-topbar__cluster">
            <div class="ws-topbar__user">
                <div class="ws-topbar__avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
                <div class="ws-topbar__name">
                    {{ auth()->user()->name }}
                    <span class="sub">{{ strtoupper(auth()->user()->role) }} · {{ auth()->user()->cawangan ?? 'JBG' }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('system.logout') }}">
                @csrf
                <button type="submit" class="ws-topbar__btn">Log Keluar</button>
            </form>
        </div>
    </header>

    <main class="ws-content" style="margin-left: 0;">
        <div class="ws-page is-full">
            <div class="ws-page__main">

                <div class="dash-greet">
                    <div>
                        <h1 class="dash-greet__h1">Selamat datang, {{ auth()->user()->name }}.<span class="dot"></span></h1>
                        <p class="dash-greet__sub">Ruang kerja <strong>iGuaman 2in1</strong> — rekod kes &amp; panel peguam dalam satu sistem.</p>
                    </div>
                </div>

                <div class="dash-sec">
                    <div class="dash-sec__head"><span class="dash-sec__eyebrow">Ringkasan</span></div>
                    <div class="dash-kpis">
                        <div class="dash-kpi">
                            <div class="dash-kpi__eyebrow">Jumlah Kes</div>
                            <div class="dash-kpi__value">{{ number_format($stats['kes']) }}</div>
                            <div class="dash-kpi__sub">{{ number_format($stats['kes_tutup']) }} fail ditutup</div>
                        </div>
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
                        Phase 3 (rekod kes: permohonan · pengantaraan · kes mahkamah · statistik) dan
                        Phase 4 (panel peguam: agihan · profil) dibina di atas asas data ini.
                    </div>
                </div>

            </div>
        </div>
    </main>

</div>

</body>
</html>
