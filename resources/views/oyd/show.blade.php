@extends('layouts.staff')

@section('title', 'OYD · '.$oyd->nama_oyd)

@php
    $alamat = collect([$oyd->alamat_oyd1, $oyd->alamat_oyd2, $oyd->alamat_oyd3,
        trim(($oyd->poskod_oyd ?? '').' '.($oyd->bandar_oyd ?? '')), $oyd->negeri_oyd])->filter()->implode(', ');
@endphp

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('oyd.index') }}" class="tap-nav__back">← Senarai OYD</a>
        <span class="tap-nav__crumb">{{ $oyd->nama_oyd }}</span>
        <div class="tap-nav__cluster">
            <a href="{{ route('oyd.edit', $oyd) }}" class="tap-head__btn">✎ Kemaskini</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <div class="tap-title" style="border:1px solid var(--line); border-radius: var(--r-lg); margin-bottom: 18px;">
        <div>
            <h1 class="tap-title__h1">{{ $oyd->nama_oyd ?: 'Tanpa Nama' }}<span class="dot"></span></h1>
            <p class="tap-title__sub">No. KP <strong>{{ $oyd->kp_oyd ?: '—' }}</strong></p>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; align-items:start;">
        <div class="tap-card">
            <div class="tap-card__eyebrow">Identiti</div>
            <div class="tap-card__row"><div class="k">Umur</div><div class="v">{{ $oyd->umur_oyd ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Jantina</div><div class="v">{{ $oyd->jantina_oyd ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Agama</div><div class="v">{{ $oyd->agama_oyd ?: '—' }} {{ $oyd->agamaLain_oyd ? '('.$oyd->agamaLain_oyd.')' : '' }}</div></div>
            <div class="tap-card__row"><div class="k">Bangsa / Etnik</div><div class="v">{{ $oyd->bangsa_oyd ?: '—' }} {{ $oyd->etnik_oyd ? '· '.$oyd->etnik_oyd : '' }}</div></div>
            <div class="tap-card__row"><div class="k">OKU</div><div class="v">{{ $oyd->oku_oyd ?: '—' }}</div></div>
        </div>

        <div class="tap-card">
            <div class="tap-card__eyebrow">Hubungan & Alamat</div>
            <div class="tap-card__row"><div class="k">Telefon</div><div class="v">{{ $oyd->notelefon_oyd ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Emel</div><div class="v">{{ $oyd->email_oyd ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Alamat</div><div class="v">{{ $alamat ?: '—' }}</div></div>
            @if ($oyd->createdBy_oyd)
                <div class="tap-card__row"><div class="k">Didaftar Oleh</div><div class="v">{{ $oyd->createdBy_oyd }} · {{ optional($oyd->createdDate_oyd)->format('d/m/Y') }}</div></div>
            @endif
            @if ($oyd->modifiedBy_oyd)
                <div class="tap-card__row"><div class="k">Kemaskini Oleh</div><div class="v">{{ $oyd->modifiedBy_oyd }} · {{ optional($oyd->modifiedDate_oyd)->format('d/m/Y') }}</div></div>
            @endif
        </div>
    </div>
@endsection
