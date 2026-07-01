@extends('layouts.staff')

@section('title', 'Senarai OYD')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Orang Yang Dibantu<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($oyd->total()) }}</strong> rekod OYD</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('oyd.create') }}" class="btn btn--primary" style="height:38px;">+ Tambah OYD</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">
            {{ session('status') }}
        </div>
    @endif

    <form method="GET" action="{{ route('oyd.index') }}" class="tap-filters">
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Cari nama, no. KP, atau telefon…" aria-label="Cari nama, no. KP, atau telefon…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 160px 2fr 1.2fr 1.4fr 80px;">
            <div class="tap-table__th">No. KP</div>
            <div class="tap-table__th">Nama</div>
            <div class="tap-table__th">Telefon</div>
            <div class="tap-table__th">Negeri</div>
            <div class="tap-table__th">Umur</div>
        </div>

        @forelse ($oyd as $row)
            <a href="{{ route('oyd.show', $row) }}" class="tap-row" style="grid-template-columns: 160px 2fr 1.2fr 1.4fr 80px;">
                <div class="tap-row__no">{{ $row->kp_oyd ?: '-' }}</div>
                <div>
                    <div class="tap-row__title">{{ $row->nama_oyd ?: 'Tanpa Nama' }}</div>
                    <div class="tap-row__sub">{{ $row->bandar_oyd ?: '-' }}</div>
                </div>
                <div class="tap-row__tujuan">{{ $row->notelefon_oyd ?: '-' }}</div>
                <div class="tap-row__tujuan">{{ $row->negeri_oyd ?: '-' }}</div>
                <div class="tap-row__tujuan">{{ $row->umur_oyd ?: '-' }}</div>
            </a>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada rekod OYD<span class="dot"></span></div>
                <div class="dash-empty__sub">Tambah rekod baharu atau laraskan carian.</div>
            </div>
        @endforelse

        @if ($oyd->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $oyd->currentPage() }} / {{ $oyd->lastPage() }} · {{ number_format($oyd->total()) }} rekod</span>
                <div class="tap-page__nav">
                    @if ($oyd->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $oyd->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($oyd->hasMorePages())
                        <a href="{{ $oyd->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
