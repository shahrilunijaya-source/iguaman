@extends('layouts.staff')

@section('title', 'Pegawai JBG')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Pegawai JBG<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($pegawai->total()) }}</strong> pegawai berdaftar</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('pegawai.create') }}" class="btn btn--primary" style="height:38px;">+ Tambah Pegawai</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('pegawai.index') }}" class="tap-filters">
        <select name="cawangan" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Cawangan</option>
            @foreach ($cawanganList as $c)
                <option value="{{ $c }}" @selected(($filters['cawangan'] ?? '') === $c)>{{ $c }}</option>
            @endforeach
        </select>
        <select name="status" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="1" @selected(($filters['status'] ?? '') === '1')>Aktif</option>
            <option value="0" @selected(($filters['status'] ?? '') === '0')>Tidak Aktif</option>
        </select>
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama atau jawatan…" aria-label="Cari nama atau jawatan…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 2fr 1.4fr 1.2fr 1fr 90px 90px;">
            <div class="tap-table__th">Nama</div>
            <div class="tap-table__th">Jawatan</div>
            <div class="tap-table__th">Bahagian</div>
            <div class="tap-table__th">Cawangan</div>
            <div class="tap-table__th">Status</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($pegawai as $row)
            <div class="tap-row" style="grid-template-columns: 2fr 1.4fr 1.2fr 1fr 90px 90px;">
                <div class="tap-row__title">{{ $row->nama }}</div>
                <div class="tap-row__tujuan">{{ $row->jawatan ?: '-' }}</div>
                <div class="tap-row__tujuan">{{ $row->bahagian ?: '-' }}</div>
                <div class="tap-row__tujuan">{{ $row->cawangan ?: '-' }}</div>
                <div><span class="pill {{ ($row->status_aktif ?? '1') === '1' ? 'pill--received' : 'pill--overdue' }}">{{ ($row->status_aktif ?? '1') === '1' ? 'Aktif' : 'Tidak' }}</span></div>
                <div style="text-align:right;"><a href="{{ route('pegawai.edit', $row) }}" class="tap-head__btn">✎</a></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada pegawai<span class="dot"></span></div>
                <div class="dash-empty__sub">Tambah atau laraskan carian.</div>
            </div>
        @endforelse

        @if ($pegawai->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $pegawai->currentPage() }} / {{ $pegawai->lastPage() }} · {{ number_format($pegawai->total()) }} pegawai</span>
                <div class="tap-page__nav">
                    @if ($pegawai->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $pegawai->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($pegawai->hasMorePages())
                        <a href="{{ $pegawai->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
