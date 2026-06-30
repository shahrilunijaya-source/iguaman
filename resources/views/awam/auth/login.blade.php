<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log Masuk · Khidmat Nasihat JBG</title>
    @vite(['resources/css/system.css'])
</head>
<body class="system">

<div class="vb-page">

    {{-- ============ LEFT — editorial ============ --}}
    <div class="vb-left">
        <div>
            <div class="vb-left__top">
                <div class="wm"><span class="i">Khidmat</span> Nasihat<span class="dot"></span></div>
                <div class="meta">JBG · BANTUAN GUAMAN</div>
            </div>
        </div>

        <div>
            <h2 class="vb-left__hero">
                <span class="line">Nasihat guaman</span>
                <span class="line"><span class="accent">percuma</span>,</span>
                <span class="line">dekat dengan</span>
                <span class="line">anda.<span class="dot dot--lg"></span></span>
            </h2>
            <p class="vb-left__lede">
                Portal Khidmat Nasihat Jabatan Bantuan Guaman Malaysia — mohon nasihat
                undang-undang, semak kelayakan dan tempah janji temu, semuanya dalam talian.
            </p>

            <div class="vb-left__decisive">
                <div class="vb-decisive"><span class="word">Mohon</span>.</div>
                <div class="vb-decisive"><span class="word">Tempah</span>.</div>
                <div class="vb-decisive"><span class="word">Dapat nasihat</span>.</div>
            </div>
        </div>

        <div class="vb-left__foot">
            <span class="stamp">JBG · Khidmat Nasihat</span>
            <span>Sesi disulitkan TLS 1.3</span>
        </div>
    </div>

    {{-- ============ RIGHT — White form ============ --}}
    <div class="vb-right">
        <div class="vb-right__inner">
            <div class="swap__screen">
                <div class="vb-right__head">
                    <div>
                        <div class="eyebrow" style="margin-bottom: 6px;">Portal Awam</div>
                        <h1 class="vb-h1">Log masuk.<span class="dot"></span></h1>
                    </div>
                </div>
                <p class="vb-sub">Masukkan No. Kad Pengenalan dan kata laluan anda untuk meneruskan.</p>

                @if (session('status'))
                    <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18);">
                        {{ session('status') }}
                    </div>
                @endif

                <form class="va-form" method="POST" action="{{ route('awam.login.attempt') }}">
                    @csrf

                    <div class="field">
                        <label class="field__label">No. Kad Pengenalan</label>
                        <input
                            type="text"
                            name="nokp"
                            class="field__input"
                            placeholder="900101015555"
                            value="{{ old('nokp') }}"
                            autofocus
                            required>
                        @error('nokp') <span class="field__hint" style="color: var(--danger);">{{ $message }}</span> @enderror
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
                            <button type="button" class="field__eye" aria-label="Tunjuk kata laluan" onclick="
                                const f = document.getElementById('passwordField');
                                f.type = f.type === 'text' ? 'password' : 'text';
                            ">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="field">
                        <label class="field__label">Pengesahan · Berapa {{ $captchaA ?? '?' }} + {{ $captchaB ?? '?' }}?</label>
                        <input type="number" name="captcha" class="field__input" placeholder="Jawapan" required>
                        @error('captcha') <span class="field__hint" style="color: var(--danger);">{{ $message }}</span> @enderror
                    </div>

                    @if ($errors->any() && !$errors->has('nokp') && !$errors->has('captcha'))
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

                <p style="text-align: center; margin-top: 16px; font-size: 12px; color: var(--mute);">
                    Belum berdaftar? <a href="{{ route('awam.daftar') }}" style="color: var(--pine); font-weight: 500;">Daftar akaun baharu</a>
                </p>

                <div style="margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                    <a href="{{ route('peguam.daftar') }}" style="color: var(--mute); text-decoration: none; font-size: 11px;">Peguam panel? Daftar di sini</a>
                    <a href="{{ route('home') }}" style="color: var(--mute); text-decoration: none; font-size: 11px;">← Laman utama</a>
                </div>
            </div>
        </div>
    </div>

</div>

</body>
</html>
