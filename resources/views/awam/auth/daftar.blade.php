<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Akaun · Khidmat Nasihat JBG</title>
    @vite(['resources/css/system.css'])
</head>
<body class="system">

<div class="vb-page">

    {{-- ============ LEFT - editorial ============ --}}
    <div class="vb-left">
        <div>
            <div class="vb-left__top">
                <div class="wm"><span class="i">Khidmat</span> Nasihat<span class="dot"></span></div>
                <div class="meta">JBG · BANTUAN GUAMAN</div>
            </div>
        </div>

        <div>
            <h2 class="vb-left__hero">
                <span class="line">Satu akaun,</span>
                <span class="line">seluruh <span class="accent">perjalanan</span></span>
                <span class="line">bantuan</span>
                <span class="line">guaman anda.<span class="dot dot--lg"></span></span>
            </h2>
            <p class="vb-left__lede">
                Daftar dengan No. Kad Pengenalan anda untuk mohon nasihat, menyemak
                kelayakan dan menguruskan janji temu di cawangan JBG berhampiran.
            </p>

            <div class="vb-left__decisive">
                <div class="vb-decisive"><span class="word">Percuma</span>.</div>
                <div class="vb-decisive"><span class="word">Dalam talian</span>.</div>
                <div class="vb-decisive"><span class="word">Tanpa beratur</span>.</div>
            </div>
        </div>

        <div class="vb-left__foot">
            <span class="stamp">JBG · Khidmat Nasihat</span>
            <span>Sesi disulitkan TLS 1.3</span>
        </div>
    </div>

    {{-- ============ RIGHT - White form ============ --}}
    <div class="vb-right">
        <div class="vb-right__inner">
            <div class="swap__screen">
                <div class="vb-right__head">
                    <div>
                        <div class="eyebrow" style="margin-bottom: 6px;">Portal Awam</div>
                        <h1 class="vb-h1">Daftar akaun.<span class="dot"></span></h1>
                    </div>
                </div>
                <p class="vb-sub">Lengkapkan butiran di bawah untuk membuka akaun portal Khidmat Nasihat.</p>

                @if ($errors->any())
                    <div class="formerr" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                        <strong style="display:flex; align-items:center; gap:6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                            {{ $errors->first() }}
                        </strong>
                    </div>
                @endif

                <form class="va-form" method="POST" action="{{ route('awam.daftar.store') }}">
                    @csrf

                    <div class="field">
                        <label class="field__label">Nama Penuh</label>
                        <input type="text" name="name" class="field__input" value="{{ old('name') }}" autofocus required>
                        @error('name') <span class="field__hint field__hint--error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label">No. Kad Pengenalan</label>
                        <input type="text" name="nokp" class="field__input" placeholder="900101015555" aria-label="900101015555" value="{{ old('nokp') }}" required>
                        @error('nokp') <span class="field__hint field__hint--error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label">Emel <span style="text-transform:none; font-weight:400; color: var(--mute-2);">(pilihan)</span></label>
                        <input type="email" name="email" class="field__input" placeholder="nama@contoh.com" aria-label="nama@contoh.com" value="{{ old('email') }}">
                        @error('email') <span class="field__hint field__hint--error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label">Kata Laluan</label>
                        <div class="field__row">
                            <input type="password" name="password" id="passwordField" class="field__input" placeholder="••••••••" aria-label="••••••••" required>
                            <button type="button" class="field__eye" aria-label="Tunjuk kata laluan" onclick="
                                const f = document.getElementById('passwordField');
                                f.type = f.type === 'text' ? 'password' : 'text';
                            ">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        @error('password') <span class="field__hint field__hint--error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label">Sahkan Kata Laluan</label>
                        <input type="password" name="password_confirmation" class="field__input" placeholder="••••••••" aria-label="••••••••" required>
                    </div>

                    <div class="field">
                        <label class="field__label">Pengesahan · Berapa {{ $captchaA ?? '?' }} + {{ $captchaB ?? '?' }}?</label>
                        <input type="number" name="captcha" class="field__input" placeholder="Jawapan" aria-label="Jawapan" required>
                        @error('captcha') <span class="field__hint field__hint--error">{{ $message }}</span> @enderror
                    </div>

                    {{-- Honeypot: hidden from humans, visible to bots. --}}
                    <div style="position:absolute; left:-9999px;" aria-hidden="true">
                        <label>Website<input type="text" name="website" value="{{ old('website') }}" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <button type="submit" class="btn btn--primary btn--block">
                        Daftar Akaun
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                    </button>
                </form>

                <p style="text-align: center; margin-top: 16px; font-size: 12px; color: var(--mute);">
                    Sudah ada akaun? <a href="{{ route('awam.login') }}" style="color: var(--pine); font-weight: 500;">Log masuk</a>
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
