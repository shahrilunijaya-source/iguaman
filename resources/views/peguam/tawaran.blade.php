@extends('layouts.peguam')

@section('title', 'Tawaran Kes')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Tawaran Penugasan<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ $tawaran->count() }}</strong> tawaran menunggu - terima dalam {{ $deadlineDays }} hari.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    @forelse ($tawaran as $t)
        @php $overdue = $t->tarikh_penugasan_peguam_panel && $t->tarikh_penugasan_peguam_panel->lt(now()->subDays($deadlineDays)); @endphp
        <div class="tap-card" style="margin-bottom:14px; {{ $overdue ? 'border-left:3px solid var(--danger);' : '' }}">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px;">
                <div>
                    <div class="tap-card__eyebrow">{{ $t->no_fail ?: '#'.$t->id }}</div>
                    <h3 style="margin:2px 0 4px; font-size:16px;">{{ $t->nama ?: 'Tanpa Nama' }}</h3>
                    <p class="dash-empty__sub" style="margin:0;">
                        {{ $t->kategori_kes ?: '-' }} · {{ $t->jenis_kes ?: '' }} · {{ $t->cawangan ?: '-' }}<br>
                        Ditawarkan: {{ optional($t->tarikh_penugasan_peguam_panel)->format('d/m/Y') ?: '-' }}
                        @if ($overdue)<span class="pill pill--overdue" style="margin-left:6px;">Lewat tempoh</span>@endif
                    </p>
                </div>
                <div style="display:flex; flex-direction:column; gap:8px; min-width:220px;">
                    <form method="POST" action="{{ route('peguam.terima', $t) }}" onsubmit="return confirm('Terima tawaran kes ini?')">
                        @csrf
                        <button type="submit" class="btn btn--primary btn--block">✓ Terima</button>
                    </form>
                    <form method="POST" action="{{ route('peguam.tolak', $t) }}" class="va-form" onsubmit="return confirm('Tolak tawaran ini? Kes dikembalikan kepada JBG.')">
                        @csrf
                        <input class="field__input" name="alasan" placeholder="Sebab tolak (pilihan)" aria-label="Sebab tolak (pilihan)" maxlength="255">
                        <button type="submit" class="btn btn--ghost btn--block" style="color:var(--danger);">✕ Tolak</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="dash-empty">
            <div class="dash-empty__title">Tiada tawaran baharu<span class="dot"></span></div>
            <div class="dash-empty__sub">Semua kes yang ditawarkan kepada anda telah diterima atau ditolak.</div>
        </div>
    @endforelse
@endsection
