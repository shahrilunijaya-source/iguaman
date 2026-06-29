<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ruang Peguam · iGuaman 2in1</title>
    @vite(['resources/css/system.css'])
</head>
<body class="system">

<div class="ws-app">

    <header class="ws-topbar">
        <a href="{{ route('peguam.dashboard') }}" class="ws-topbar__brand"><span class="i">i</span>Guaman</a>
        <span class="ws-topbar__sep"></span>
        <span class="ws-topbar__context">2in1 · Ruang Peguam Panel</span>
        <span class="ws-topbar__spacer"></span>

        <div class="ws-topbar__cluster">
            <div class="ws-topbar__user">
                <div class="ws-topbar__avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
                <div class="ws-topbar__name">
                    {{ auth()->user()->name }}
                    <span class="sub">PEGUAM PANEL</span>
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
                        <h1 class="dash-greet__h1">Salam, {{ $stats['nama'] }}.<span class="dot"></span></h1>
                        <p class="dash-greet__sub">Ruang <strong>Peguam Panel</strong>. Kes yang ditugaskan kepada anda.</p>
                    </div>
                </div>

                <div class="dash-sec">
                    <div class="dash-sec__head"><span class="dash-sec__eyebrow">Ringkasan</span></div>
                    <div class="dash-kpis">
                        <div class="dash-kpi">
                            <div class="dash-kpi__eyebrow">Kes Saya</div>
                            <div class="dash-kpi__value">{{ number_format($stats['kes_saya']) }}</div>
                            <div class="dash-kpi__sub">ditugaskan</div>
                        </div>
                    </div>
                </div>

                <div class="dash-empty">
                    <div class="dash-empty__title">Modul peguam akan datang<span class="dot"></span></div>
                    <div class="dash-empty__sub">
                        Phase 4: agihan kes (baru / semasa / semula), profil peguam, daftar / tarik diri, beban tugas.
                    </div>
                </div>

            </div>
        </div>
    </main>

</div>

</body>
</html>
