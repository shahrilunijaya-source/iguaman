@extends('layouts.staff')

@section('title', 'Cawangan')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Cawangan<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($cawangan->total()) }}</strong> cawangan berdaftar</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('cawangan.create') }}" class="btn btn--primary" style="height:38px;">+ Tambah Cawangan</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('cawangan.index') }}" class="tap-filters">
        <select name="jenis" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Jenis</option>
            @foreach (\App\Models\Cawangan::JENIS as $j)
                <option value="{{ $j }}" @selected(($filters['jenis'] ?? '') === $j)>{{ $j }}</option>
            @endforeach
        </select>
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama cawangan…" aria-label="Cari nama cawangan…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 2.2fr 0.8fr 1.6fr 0.8fr 70px;">
            <div class="tap-table__th">Nama</div>
            <div class="tap-table__th">Jenis</div>
            <div class="tap-table__th">Negeri</div>
            <div class="tap-table__th">Bilik</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($cawangan as $row)
            <div class="tap-row" style="grid-template-columns: 2.2fr 0.8fr 1.6fr 0.8fr 70px;">
                <div class="tap-row__title">
                    {{ $row->nama }}
                    @unless ($row->status_aktif)<span class="pill" style="opacity:.6;">Tidak Aktif</span>@endunless
                </div>
                <div class="tap-row__tujuan"><span class="pill pill--received">{{ $row->jenis }}</span></div>
                <div class="tap-row__tujuan">{{ $row->negeri->nama ?? '-' }}</div>
                <div class="tap-row__tujuan">{{ $row->bilik_count }}</div>
                <div style="text-align:right;"><a href="{{ route('cawangan.edit', $row) }}" class="tap-head__btn">✎</a></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada cawangan<span class="dot"></span></div>
                <div class="dash-empty__sub">Tambah cawangan atau laraskan carian.</div>
            </div>
        @endforelse

        @if ($cawangan->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $cawangan->currentPage() }} / {{ $cawangan->lastPage() }} · {{ number_format($cawangan->total()) }} cawangan</span>
                <div class="tap-page__nav">
                    @if ($cawangan->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $cawangan->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($cawangan->hasMorePages())
                        <a href="{{ $cawangan->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
