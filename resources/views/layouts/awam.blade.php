<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal Khidmat Nasihat') — JBG</title>
    @vite(['resources/css/theme.css', 'resources/css/system.css', 'resources/js/app.js'])
</head>
<body class="awam-shell">
    <header class="awam-header">
        <a href="{{ route('awam.dashboard') }}" class="awam-brand">JBG · Khidmat Nasihat</a>
        <nav class="awam-nav">
            @auth
                <a href="{{ route('awam.dashboard') }}">Permohonan Saya</a>
                <form method="POST" action="{{ route('awam.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="link-button">Log Keluar</button>
                </form>
            @else
                <a href="{{ route('awam.login') }}">Log Masuk</a>
                <a href="{{ route('awam.daftar') }}">Daftar</a>
            @endauth
        </nav>
    </header>

    <main class="awam-main">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="awam-footer">
        <p>&copy; {{ now()->year }} Jabatan Bantuan Guaman Malaysia</p>
    </footer>
</body>
</html>
