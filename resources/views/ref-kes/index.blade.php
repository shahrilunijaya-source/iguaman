@extends('layouts.staff')

@section('title', 'Jenis Kes')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Jenis Kes<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($refKes->total()) }}</strong> jenis kes berdaftar</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('ref-kes.create') }}" class="btn btn--primary" style="height:38px;">+ Tambah Jenis Kes</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('ref-kes.index') }}" class="tap-filters">
        <select name="aktif" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="1" @selected(($filters['aktif'] ?? '') === '1')>Aktif</option>
            <option value="0" @selected(($filters['aktif'] ?? '') === '0')>Tidak Aktif</option>
        </select>
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari jenis, kategori atau deskripsi…" aria-label="Cari jenis, kategori atau deskripsi…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 0.9fr 1.4fr 2fr 1.1fr 90px 90px;">
            <div class="tap-table__th">Jenis</div>
            <div class="tap-table__th">Kategori</div>
            <div class="tap-table__th">Deskripsi</div>
            <div class="tap-table__th">Kuatkuasa</div>
            <div class="tap-table__th">Status</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($refKes as $row)
            <div class="tap-row" style="grid-template-columns: 0.9fr 1.4fr 2fr 1.1fr 90px 90px;">
                <div class="tap-row__title">{{ $row->jenis_kes }}</div>
                <div class="tap-row__tujuan">{{ $row->kategori_kes ?: '-' }}</div>
                <div class="tap-row__tujuan">{{ $row->deskripsi ?: '-' }}</div>
                <div class="tap-row__tujuan">{{ $row->tarikh_kuatkuasa ? $row->tarikh_kuatkuasa->format('d/m/Y') : '-' }}</div>
                <div><span class="pill {{ (string) $row->aktif_kes === '1' ? 'pill--received' : 'pill--overdue' }}">{{ (string) $row->aktif_kes === '1' ? 'Aktif' : 'Tidak' }}</span></div>
                <div style="text-align:right;"><a href="{{ route('ref-kes.edit', $row) }}" class="tap-head__btn" aria-label="Sunting rujukan kes" title="Sunting">✎</a></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada jenis kes<span class="dot"></span></div>
                <div class="dash-empty__sub">Tambah atau laraskan carian.</div>
            </div>
        @endforelse

        @if ($refKes->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $refKes->currentPage() }} / {{ $refKes->lastPage() }} · {{ number_format($refKes->total()) }} jenis kes</span>
                <div class="tap-page__nav">
                    @if ($refKes->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $refKes->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($refKes->hasMorePages())
                        <a href="{{ $refKes->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
