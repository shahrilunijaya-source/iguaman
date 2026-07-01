<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Semula Kata Laluan · Sistem Integrated Bantuan Guaman</title>
    @vite(['resources/css/system.css'])
</head>
<body class="system">

<div class="vb-page">
    <div class="vb-left">
        <div class="vb-left__top">
            <div class="wm"><span class="i">i</span>Guaman<span class="dot"></span></div>
            <div class="meta">INTEGRATED · PEMULIHAN AKAUN</div>
        </div>
        <div>
            <h2 class="vb-left__hero">
                <span class="line">Kata laluan</span>
                <span class="line"><span class="accent">baharu</span>.</span>
            </h2>
            <p class="vb-left__lede">Pilih kata laluan kukuh - minimum 8 aksara.</p>
        </div>
        <div class="vb-left__foot"><span class="stamp">iGuaman · Sistem Integrated Bantuan Guaman</span></div>
    </div>

    <div class="vb-right">
        <div class="vb-right__inner">
            <div class="swap__screen">
                <div class="eyebrow" style="margin-bottom: 6px;">Pemulihan</div>
                <h1 class="vb-h1">Set kata laluan baharu.<span class="dot"></span></h1>
                <p class="vb-sub">Masukkan kata laluan baharu untuk akaun anda.</p>

                <form class="va-form" method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="field">
                        <label class="field__label">Emel</label>
                        <input type="email" name="email" class="field__input" value="{{ old('email', $email) }}" required>
                    </div>
                    <div class="field">
                        <label class="field__label">Kata Laluan Baharu</label>
                        <input type="password" name="password" class="field__input" placeholder="••••••••" aria-label="••••••••" required>
                    </div>
                    <div class="field">
                        <label class="field__label">Sahkan Kata Laluan</label>
                        <input type="password" name="password_confirmation" class="field__input" placeholder="••••••••" aria-label="••••••••" required>
                    </div>

                    @if ($errors->any())
                        <div class="formerr">{{ $errors->first() }}</div>
                    @endif

                    <button type="submit" class="btn btn--primary btn--block">Set Semula</button>
                </form>

                <div style="margin-top: 18px;">
                    <a href="{{ route('system.login') }}" style="color: var(--mute); text-decoration: none; font-size: 12px;">← Kembali ke log masuk</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
