@extends('layouts.peguam')

@section('title', 'Kes Grab')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Kes Grab<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ $pool->count() }}</strong> Khidmat Nasihat terbuka - grab dalam tempoh {{ $grabDays }} hari, siapa cepat dia dapat.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="formerr" style="margin-bottom:14px;">{{ session('error') }}</div>
    @endif

    @unless ($profile)
        <div class="formerr" style="margin-bottom:14px;">Akaun anda belum dipautkan ke rekod peguam panel - grab tidak tersedia.</div>
    @endunless

    @forelse ($pool as $k)
        <div class="tap-card" style="margin-bottom:14px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px;">
                <div>
                    <div class="tap-card__eyebrow">{{ $k->no_permohonan ?: 'KN #'.$k->id }}</div>
                    <h3 style="margin:2px 0 4px; font-size:16px;">{{ $k->nama_mangsa ?: 'Tanpa Nama' }}</h3>
                    <p class="dash-empty__sub" style="margin:0;">
                        {{ optional($k->kategori)->jenis_kategori ?: '-' }} · {{ $k->jenis_kes ?: '-' }} · {{ optional($k->cawangan)->nama ?: '-' }}<br>
                        Dibuka grab: {{ optional($k->tarikh_buka_grab)->format('d/m/Y') ?: '-' }}
                    </p>
                </div>
                <div style="min-width:200px;">
                    <form method="POST" action="{{ route('peguam.grab', $k) }}" onsubmit="return confirm('Grab kes ini? Anda akan bertanggungjawab ke atas kes ini.')">
                        @csrf
                        <button type="submit" class="btn btn--primary btn--block" @disabled(! $profile)>✋ Grab Kes</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="dash-empty">
            <div class="dash-empty__title">Tiada kes grab<span class="dot"></span></div>
            <div class="dash-empty__sub">Tiada Khidmat Nasihat yang dibuka untuk grab pada masa ini.</div>
        </div>
    @endforelse
@endsection
