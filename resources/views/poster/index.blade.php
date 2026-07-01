@extends('layouts.staff')

@section('title', 'e-Poster')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">e-Poster<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($poster->total()) }}</strong> poster berdaftar</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('poster.create') }}" class="btn btn--primary" style="height:38px;">+ Tambah Poster</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('poster.index') }}" class="tap-filters">
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Cari tajuk poster…" aria-label="Cari tajuk poster…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 3fr 1.2fr 110px 90px;">
            <div class="tap-table__th">Tajuk</div>
            <div class="tap-table__th">Status</div>
            <div class="tap-table__th">Imej</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($poster as $row)
            <div class="tap-row" style="grid-template-columns: 3fr 1.2fr 110px 90px;">
                <div class="tap-row__title">{{ $row->tajuk_poster }}</div>
                <div><span class="pill {{ ($row->status_poster ?? 'Aktif') === 'Aktif' ? 'pill--received' : 'pill--overdue' }}">{{ $row->status_poster ?: 'Aktif' }}</span></div>
                <div class="tap-row__tujuan">{{ $row->image_path ? 'Ada' : '—' }}</div>
                <div style="text-align:right;"><a href="{{ route('poster.edit', $row) }}" class="tap-head__btn">✎</a></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada poster<span class="dot"></span></div>
                <div class="dash-empty__sub">Tambah atau laraskan carian.</div>
            </div>
        @endforelse

        @if ($poster->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $poster->currentPage() }} / {{ $poster->lastPage() }} · {{ number_format($poster->total()) }} poster</span>
                <div class="tap-page__nav">
                    @if ($poster->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $poster->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($poster->hasMorePages())
                        <a href="{{ $poster->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
