<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lupa Kata Laluan · Sistem Integrated Bantuan Guaman</title>
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
                <span class="line">Lupa kata</span>
                <span class="line"><span class="accent">laluan</span>?</span>
            </h2>
            <p class="vb-left__lede">Masukkan emel anda. Pautan set semula akan dihantar.</p>
        </div>
        <div class="vb-left__foot"><span class="stamp">iGuaman · Sistem Integrated Bantuan Guaman</span></div>
    </div>

    <div class="vb-right">
        <div class="vb-right__inner">
            <div class="swap__screen">
                <div class="eyebrow" style="margin-bottom: 6px;">Pemulihan</div>
                <h1 class="vb-h1">Set semula kata laluan.<span class="dot"></span></h1>
                <p class="vb-sub">Kami akan emelkan pautan untuk set semula.</p>

                @if (session('status'))
                    <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18);">
                        {{ session('status') }}
                    </div>
                @endif

                <form class="va-form" method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="field">
                        <label class="field__label">Emel</label>
                        <input type="email" name="email" class="field__input" value="{{ old('email') }}" autofocus required>
                    </div>
                    @error('email') <div class="formerr">{{ $message }}</div> @enderror
                    <button type="submit" class="btn btn--primary btn--block">Hantar Pautan</button>
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
