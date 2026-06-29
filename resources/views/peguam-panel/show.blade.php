@extends('layouts.staff')

@section('title', 'Peguam · '.$peguam->nama_peguam)

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('agihan.beban') }}" class="tap-nav__back">← Beban Tugas</a>
        <span class="tap-nav__crumb">{{ $peguam->nama_peguam }}</span>
        <div class="tap-nav__cluster">
            <a href="{{ route('peguam-panel.edit', $peguam) }}" class="tap-head__btn">✎ Kemaskini</a>
        </div>
    </div>

    <div class="tap-title" style="border:1px solid var(--line); border-radius: var(--r-lg); margin-bottom: 18px;">
        <div>
            <h1 class="tap-title__h1">{{ $peguam->nama_peguam }}<span class="dot"></span></h1>
            <p class="tap-title__sub">No. KP <strong>{{ $peguam->kp_peguam ?: '—' }}</strong> · {{ $peguam->nama_firma ?: '—' }}</p>
            <div class="tap-title__chips">
                <span class="tap-title__chip">{{ $peguam->tel_peguam ?: '—' }}</span>
                <span class="tap-title__chip">{{ $peguam->emel_peguam ?: '—' }}</span>
                <span class="tap-title__chip">{{ $kes->count() }} kes ditugaskan</span>
            </div>
        </div>
    </div>

    @include('peguam-panel._butiran', ['b' => $b])

    <div class="tap-card" style="margin-top:18px;">
        <div class="tap-card__eyebrow">Kes Ditugaskan ({{ $kes->count() }})</div>
        @forelse ($kes as $k)
            <a href="{{ route('kes.show', $k->id) }}" class="tap-card__row" style="text-decoration:none;">
                <div class="k">{{ $k->no_fail ?: '#'.$k->id }} · {{ $k->nama ?: 'Tanpa Nama' }}</div>
                <div class="v">{{ $k->kategori_kes ?: '—' }} · {{ $k->status ?: 'baru' }}</div>
            </a>
        @empty
            <div class="dash-empty__sub" style="padding:6px 0;">Tiada kes ditugaskan.</div>
        @endforelse
    </div>
@endsection
