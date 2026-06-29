<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Utama · iGuaman 2in1</title>
    @vite(['resources/css/system.css'])
</head>
<body class="system">

<div class="ws-app">

    {{-- Top bar --}}
    <header class="ws-topbar">
        <a href="{{ route('system.utama') }}" class="ws-topbar__brand"><span class="i">i</span>Guaman</a>
        <span class="ws-topbar__sep"></span>
        <span class="ws-topbar__context">2in1 · Ruang Pengguna</span>
        <span class="ws-topbar__spacer"></span>

        <div class="ws-topbar__cluster">
            <div class="ws-topbar__user">
                <div class="ws-topbar__avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
                <div class="ws-topbar__name">
                    {{ auth()->user()->name }}
                    <span class="sub">{{ auth()->user()->email }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('system.logout') }}">
                @csrf
                <button type="submit" class="ws-topbar__btn">Log Keluar</button>
            </form>
        </div>
    </header>

    {{-- Content --}}
    <main class="ws-content" style="margin-left: 0;">
        <div class="ws-page is-full">
            <div class="ws-page__main">

                <div class="dash-greet">
                    <div>
                        <h1 class="dash-greet__h1">Selamat datang, {{ auth()->user()->name }}.<span class="dot"></span></h1>
                        <p class="dash-greet__sub">Ruang kerja <strong>iGuaman 2in1</strong>. Stub awal — bina modul di sini.</p>
                    </div>
                </div>

                <div class="dash-empty">
                    <div class="dash-empty__title">Tiada modul lagi<span class="dot"></span></div>
                    <div class="dash-empty__sub">
                        Dashboard kosong. Tambah KPI, senarai tugasan, dan tapisan kes mengikut keperluan iGuaman 2in1.
                    </div>
                </div>

            </div>
        </div>
    </main>

</div>

</body>
</html>
