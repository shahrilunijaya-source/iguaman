<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log Masuk · iGuaman 2in1</title>
    @vite(['resources/css/system.css'])
</head>
<body class="system">

<div class="vb-page">

    {{-- ============ LEFT — editorial ============ --}}
    <div class="vb-left">
        <div>
            <div class="vb-left__top">
                <div class="wm"><span class="i">i</span>Guaman<span class="dot"></span></div>
                <div class="meta">2IN1 · BANTUAN GUAMAN</div>
            </div>
        </div>

        <div>
            <h2 class="vb-left__hero">
                <span class="line">Dua sistem,</span>
                <span class="line"><span class="accent">satu</span> ruang.</span>
                <span class="line">Rekod kes &amp;</span>
                <span class="line">panel peguam.<span class="dot dot--lg"></span></span>
            </h2>
            <p class="vb-left__lede">
                Ruang kerja iGuaman 2in1 — rekod kes, pengantaraan, mahkamah dan panel peguam dalam satu sistem.
            </p>

            <div class="vb-left__decisive">
                <div class="vb-decisive"><span class="word">Direkod</span>.</div>
                <div class="vb-decisive"><span class="word">Diagih</span>.</div>
                <div class="vb-decisive"><span class="word">Diselesaikan</span>.</div>
            </div>
        </div>

        <div class="vb-left__foot">
            <span class="stamp">iGuaman · 2in1</span>
            <span>Sesi disulitkan TLS 1.3</span>
        </div>
    </div>

    {{-- ============ RIGHT — White form ============ --}}
    <div class="vb-right">
        <div class="vb-right__inner">
            <div class="swap__screen">
                <div class="vb-right__head">
                    <div>
                        <div class="eyebrow" style="margin-bottom: 6px;">Akses Pengguna</div>
                        <h1 class="vb-h1">Log masuk.<span class="dot"></span></h1>
                    </div>
                </div>
                <p class="vb-sub">Masukkan emel dan kata laluan anda untuk meneruskan.</p>

                @if (session('status'))
                    <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18);">
                        {{ session('status') }}
                    </div>
                @endif

                <form class="va-form" method="POST" action="{{ route('system.login.attempt') }}">
                    @csrf

                    <div class="field">
                        <label class="field__label">Emel</label>
                        <input
                            type="email"
                            name="email"
                            class="field__input"
                            placeholder="nama@jbg.gov.my"
                            value="{{ old('email') }}"
                            autofocus
                            required>
                    </div>

                    <div class="field">
                        <label class="field__label">Kata Laluan</label>
                        <div class="field__row">
                            <input
                                type="password"
                                name="password"
                                id="passwordField"
                                class="field__input"
                                placeholder="••••••••"
                                required>
                            <button type="button" class="field__eye" onclick="
                                const f = document.getElementById('passwordField');
                                f.type = f.type === 'text' ? 'password' : 'text';
                            ">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="va-form__row">
                        <label style="color: var(--mute); display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="remember" style="margin: 0;">
                            <span>Ingat saya</span>
                        </label>
                        <a href="{{ route('password.request') }}">Lupa kata laluan?</a>
                    </div>

                    @if ($errors->any())
                        <div class="formerr">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <button type="submit" class="btn btn--primary btn--block">
                        Log Masuk
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                    </button>
                </form>

                <div style="margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; align-items: center;">
                    <a href="{{ route('home') }}" style="color: var(--mute); text-decoration: none; font-size: 11px;">← Laman awam</a>
                </div>
            </div>
        </div>
    </div>

</div>

</body>
</html>
