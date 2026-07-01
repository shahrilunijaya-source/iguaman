@extends('layouts.staff')

@section('title', 'Pengantaraan')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Pengantaraan<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ $senarai->total() }}</strong> rekod pengantaraan.</p>
        </div>
        <a href="{{ route('pengantaraan.create') }}" class="tap-head__btn">＋ Pendaftaran Terus</a>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('pengantaraan.senarai') }}" style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
        <input class="field__input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama / KP / no. pengantaraan" aria-label="Cari nama / KP / no. pengantaraan" style="max-width:280px;">
        <select class="field__input" name="sumber" style="max-width:200px;">
            <option value="">Semua sumber</option>
            <option value="TERUS" @selected(($filters['sumber'] ?? '') === 'TERUS')>Terus</option>
            <option value="LITIGASI" @selected(($filters['sumber'] ?? '') === 'LITIGASI')>Dari Litigasi</option>
        </select>
        <button type="submit" class="btn btn--primary">Cari</button>
        <a href="{{ route('pengantaraan.senarai') }}" class="btn btn--ghost">Set Semula</a>
    </form>

    @forelse ($senarai as $m)
        <div class="tap-card" style="margin-bottom:12px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px;">
                <div>
                    <div class="tap-card__eyebrow">{{ $m->no_pengantaraan ?: 'Tiada No.' }} · {{ $m->sumber_pengantaraan ?: '-' }}</div>
                    <h3 style="margin:2px 0 4px; font-size:15px;">{{ $m->nama ?: 'Tanpa Nama' }}</h3>
                    <p class="dash-empty__sub" style="margin:0;">
                        {{ $m->nokp ?: '-' }} · {{ $m->cawangan ?: '-' }} · {{ $m->jenis_kes ?: '-' }}<br>
                        Status: {{ $m->status_pengantaraan ?: '-' }}
                        @if ($m->nama_pegawai_pengantara) · Pengantara: {{ $m->nama_pegawai_pengantara }} @endif
                    </p>
                </div>
                <a href="{{ route('pengantaraan.edit', $m) }}" class="btn btn--ghost">Urus →</a>
            </div>
        </div>
    @empty
        <div class="dash-empty">
            <div class="dash-empty__title">Tiada rekod pengantaraan<span class="dot"></span></div>
            <div class="dash-empty__sub">Daftar pengantaraan terus atau buka dari kes litigasi.</div>
        </div>
    @endforelse

    <div style="margin-top:16px;">{{ $senarai->links() }}</div>
@endsection
