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
        <a href="{{ route('statistik.index') }}" class="ws-side-top {{ request()->routeIs('statistik.*') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">▦</span><span class="ws-side-label">Statistik</span>
        </a>

        <div class="ws-side-section">Panel Peguam</div>
        <a href="{{ route('agihan.beban') }}" class="ws-side-top {{ request()->routeIs('agihan.beban') ? 'is-active' : '' }}">
            <span class="ws-side-top__icon">▥</span><span class="ws-side-label">Beban Tugas Peguam</span>
        </a>
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
