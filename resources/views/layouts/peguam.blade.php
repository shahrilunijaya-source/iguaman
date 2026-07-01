<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Ruang Peguam') · Sistem Integrated Bantuan Guaman</title>
    @vite(['resources/css/system.css'])
</head>
<body class="system">

<div class="ws-app">

    <header class="ws-topbar">
        <a href="{{ route('peguam.dashboard') }}" class="ws-topbar__brand"><span class="i">i</span>Guaman</a>
        <span class="ws-topbar__sep"></span>
        <span class="ws-topbar__context">iGuaman · Ruang Peguam Panel</span>
        <span class="ws-topbar__spacer"></span>

        <div class="ws-topbar__cluster">
            <a href="{{ route('peguam.dashboard') }}" class="ws-topbar__btn {{ request()->routeIs('peguam.dashboard') ? 'is-active' : '' }}">Dashboard</a>
            <a href="{{ route('peguam.kes') }}" class="ws-topbar__btn {{ request()->routeIs('peguam.kes*') ? 'is-active' : '' }}">Kes Saya</a>
            <a href="{{ route('peguam.tawaran') }}" class="ws-topbar__btn {{ request()->routeIs('peguam.tawaran*') ? 'is-active' : '' }}">Tawaran</a>
            <a href="{{ route('peguam.grab.index') }}" class="ws-topbar__btn {{ request()->routeIs('peguam.grab.*') ? 'is-active' : '' }}">Kes Grab</a>
            <a href="{{ route('peguam.profil') }}" class="ws-topbar__btn {{ request()->routeIs('peguam.profil*') ? 'is-active' : '' }}">Profil</a>
            <span class="ws-topbar__sep"></span>
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
                @yield('content')
            </div>
        </div>
    </main>

</div>

</body>
</html>
