@extends('layouts.peguam')

@section('title', 'Profil')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Profil Peguam<span class="dot"></span></h1>
            <p class="tap-head__sub">Maklumat panel &amp; akaun</p>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px;">
        <div class="tap-card">
            <div class="tap-card__eyebrow">Akaun</div>
            <div class="tap-card__row"><div class="k">Nama</div><div class="v">{{ $user->name }}</div></div>
            <div class="tap-card__row"><div class="k">Emel</div><div class="v">{{ $user->email }}</div></div>
            <div class="tap-card__row"><div class="k">ID Peguam</div><div class="v">{{ $user->id_peguam_panel ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Log Masuk Terakhir</div><div class="v">{{ optional($user->last_login_at)->format('d/m/Y H:i') ?: '—' }}</div></div>
        </div>

        <div class="tap-card">
            <div class="tap-card__eyebrow">Rekod Panel</div>
            @if ($profile)
                <div class="tap-card__row"><div class="k">Nama Peguam</div><div class="v">{{ $profile->nama_peguam }}</div></div>
                <div class="tap-card__row"><div class="k">No. KP</div><div class="v">{{ $profile->kp_peguam }}</div></div>
                <div class="tap-card__row"><div class="k">Telefon</div><div class="v">{{ $profile->tel_peguam ?: '—' }}</div></div>
                <div class="tap-card__row"><div class="k">Emel</div><div class="v">{{ $profile->emel_peguam ?: '—' }}</div></div>
                <div class="tap-card__row"><div class="k">Firma</div><div class="v">{{ $profile->nama_firma ?: '—' }}</div></div>
                <div class="tap-card__row"><div class="k">Alamat Firma</div><div class="v">{{ trim(($profile->alamat_firma_1 ?? '').' '.($profile->alamat_firma_2 ?? '').' '.($profile->negeri_firma ?? '')) ?: '—' }}</div></div>
            @else
                <div class="dash-empty__sub" style="padding:8px 0;">Akaun belum dipautkan ke rekod peguam panel.</div>
            @endif
        </div>
    </div>

    <div style="margin-top:18px;">
        <div class="tap-card__eyebrow" style="margin-bottom:12px;">Profil Terperinci</div>
        @include('peguam-panel._butiran', ['b' => $b])
    </div>
@endsection
