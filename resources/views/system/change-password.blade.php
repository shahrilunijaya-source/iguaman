<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tukar Kata Laluan · Sistem Integrated Bantuan Guaman</title>
    @vite(['resources/css/system.css'])
</head>
<body class="system">

<div class="vb-page">
    <div class="vb-left">
        <div class="vb-left__top">
            <div class="wm"><span class="i">i</span>Guaman<span class="dot"></span></div>
            <div class="meta">INTEGRATED · KESELAMATAN AKAUN</div>
        </div>
        <div>
            <h2 class="vb-left__hero">
                <span class="line">Set kata</span>
                <span class="line"><span class="accent">laluan</span> baharu.</span>
            </h2>
            <p class="vb-left__lede">
                @if (auth()->user()->must_change_password)
                    Akaun anda dipindahkan dari sistem lama. Demi keselamatan, sila tetapkan kata laluan baharu sebelum meneruskan.
                @else
                    Kemaskini kata laluan akaun anda.
                @endif
            </p>
        </div>
        <div class="vb-left__foot"><span class="stamp">iGuaman · Sistem Integrated Bantuan Guaman</span></div>
    </div>

    <div class="vb-right">
        <div class="vb-right__inner">
            <div class="swap__screen">
                <div class="eyebrow" style="margin-bottom:6px;">Akaun · {{ auth()->user()->email }}</div>
                <h1 class="vb-h1">Tukar kata laluan.<span class="dot"></span></h1>
                <p class="vb-sub">Masukkan kata laluan semasa dan kata laluan baharu (minimum 8 aksara).</p>

                <form class="va-form" method="POST" action="{{ route('password.change.update') }}">
                    @csrf
                    <div class="field">
                        <label class="field__label">Kata Laluan Semasa</label>
                        <input type="password" name="current_password" class="field__input" required autofocus>
                    </div>
                    <div class="field">
                        <label class="field__label">Kata Laluan Baharu</label>
                        <input type="password" name="password" class="field__input" required>
                    </div>
                    <div class="field">
                        <label class="field__label">Sahkan Kata Laluan Baharu</label>
                        <input type="password" name="password_confirmation" class="field__input" required>
                    </div>

                    @if ($errors->any())
                        <div class="formerr">{{ $errors->first() }}</div>
                    @endif

                    <button type="submit" class="btn btn--primary btn--block">Simpan Kata Laluan</button>
                </form>

                <form method="POST" action="{{ route('system.logout') }}" style="margin-top:16px;">
                    @csrf
                    <button type="submit" style="background:none;border:0;color:var(--mute);font-size:12px;cursor:pointer;">Log keluar</button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
