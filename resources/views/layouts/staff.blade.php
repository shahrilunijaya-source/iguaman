<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'iGuaman 2in1') · iGuaman 2in1</title>
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

    <aside class="ws-sidebar">
        <div class="ws-side-section">Utama</div>
        <a href="{{ route('system.utama') }}" class="ws-side-top {{ request()->routeIs('system.utama') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">▣</span><span class="ws-side-label">Dashboard</span>
        </a>

        <div class="ws-side-section">Rekod Kes</div>
        <a href="{{ route('kes.index') }}" class="ws-side-top {{ request()->routeIs('kes.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">▤</span><span class="ws-side-label">Senarai Kes</span>
        </a>
        <a href="{{ route('kes.create') }}" class="ws-side-top {{ request()->routeIs('kes.create') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">＋</span><span class="ws-side-label">Permohonan Baharu</span>
        </a>
        <a href="{{ route('oyd.index') }}" class="ws-side-top {{ request()->routeIs('oyd.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">☺</span><span class="ws-side-label">OYD</span>
        </a>
        <a href="{{ route('kes.tutup') }}" class="ws-side-top {{ request()->routeIs('kes.tutup') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">🔒</span><span class="ws-side-label">Fail Tutup</span>
        </a>
        <a href="{{ route('statistik.index') }}" class="ws-side-top {{ request()->routeIs('statistik.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">▦</span><span class="ws-side-label">Statistik</span>
        </a>
        <a href="{{ route('statistik-sla.index') }}" class="ws-side-top {{ request()->routeIs('statistik-sla.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">▩</span><span class="ws-side-label">Statistik SLA</span>
        </a>
        <a href="{{ route('statistik-kesilapan.index') }}" class="ws-side-top {{ request()->routeIs('statistik-kesilapan.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">⚠</span><span class="ws-side-label">Kesilapan No. Fail</span>
        </a>
        <a href="{{ route('statistik-pengantaraan.index') }}" class="ws-side-top {{ request()->routeIs('statistik-pengantaraan.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">⇆</span><span class="ws-side-label">Statistik Pengantaraan</span>
        </a>
        <a href="{{ route('kpi.index') }}" class="ws-side-top {{ request()->routeIs('kpi.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">◔</span><span class="ws-side-label">KPI</span>
        </a>
        <a href="{{ route('laporan.index') }}" class="ws-side-top {{ request()->routeIs('laporan.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">▭</span><span class="ws-side-label">Laporan</span>
        </a>

        <div class="ws-side-section">Panel Peguam</div>
        <a href="{{ route('permohonan-peguam.index') }}" class="ws-side-top {{ request()->routeIs('permohonan-peguam.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">▧</span><span class="ws-side-label">Permohonan Peguam</span>
        </a>
        <a href="{{ route('agihan.senarai', 'baru') }}" class="ws-side-top {{ request()->routeIs('agihan.senarai') || request()->routeIs('agihan.maklumat') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">⇄</span><span class="ws-side-label">Agihan Kes</span>
        </a>
        <a href="{{ route('agihan.beban') }}" class="ws-side-top {{ request()->routeIs('agihan.beban') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">▥</span><span class="ws-side-label">Beban Tugas Peguam</span>
        </a>
        <a href="{{ route('tarikdiri.senarai') }}" class="ws-side-top {{ request()->routeIs('tarikdiri.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">⤴</span><span class="ws-side-label">Permohonan Tarik Diri</span>
        </a>
        @php $bidangPending = \App\Support\PengkhususanService::pendingCount(); @endphp
        <a href="{{ route('kemaskini-bidang.index') }}" class="ws-side-top {{ request()->routeIs('kemaskini-bidang.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">◳</span><span class="ws-side-label">Kemaskini Bidang @if ($bidangPending > 0)<strong style="color:var(--brand,#00B8A9);">({{ $bidangPending }})</strong>@endif</span>
        </a>

        @can('menu.selenggara')
            <div class="ws-side-section">Pentadbiran</div>
            <a href="{{ route('pengguna.index') }}" class="ws-side-top {{ request()->routeIs('pengguna.*') ? 'is-active' : '' }}">
                <span class="ws-side-top__icon">👤</span><span class="ws-side-label">Pengguna</span>
            </a>
            <a href="{{ route('pegawai.index') }}" class="ws-side-top {{ request()->routeIs('pegawai.*') ? 'is-active' : '' }}">
                <span class="ws-side-top__icon">☰</span><span class="ws-side-label">Pegawai JBG</span>
            </a>
            <a href="{{ route('audit.index') }}" class="ws-side-top {{ request()->routeIs('audit.*') ? 'is-active' : '' }}">
                <span class="ws-side-top__icon">≣</span><span class="ws-side-label">Log Audit</span>
            </a>
            @can('urus.peranan')
                <a href="{{ route('peranan.index') }}" class="ws-side-top {{ request()->routeIs('peranan.*') ? 'is-active' : '' }}">
                    <span class="ws-side-top__icon">🔑</span><span class="ws-side-label">Peranan &amp; Akses</span>
                </a>
            @endcan

            <div class="ws-side-section">Selenggara</div>
            <a href="{{ route('ref-kes.index') }}" class="ws-side-top {{ request()->routeIs('ref-kes.*') ? 'is-active' : '' }}">
                <span class="ws-side-top__icon">▦</span><span class="ws-side-label">Jenis Kes</span>
            </a>
            <a href="{{ route('mahkamah-ref.index', ['jenis' => 'sivil']) }}" class="ws-side-top {{ request()->routeIs('mahkamah-ref.*') ? 'is-active' : '' }}">
                <span class="ws-side-top__icon">⚖</span><span class="ws-side-label">Mahkamah</span>
            </a>
            <a href="{{ route('cuti.index') }}" class="ws-side-top {{ request()->routeIs('cuti.*') ? 'is-active' : '' }}">
                <span class="ws-side-top__icon">📅</span><span class="ws-side-label">Cuti Umum</span>
            </a>
            <a href="{{ route('poster.index') }}" class="ws-side-top {{ request()->routeIs('poster.*') ? 'is-active' : '' }}">
                <span class="ws-side-top__icon">🖼</span><span class="ws-side-label">e-Poster</span>
            </a>
        @endcan
    </aside>

    <main class="ws-content">
        <div class="ws-page is-full">
            <div class="ws-page__main">
                @yield('content')
            </div>
        </div>
    </main>

</div>

@stack('scripts')
</body>
</html>
