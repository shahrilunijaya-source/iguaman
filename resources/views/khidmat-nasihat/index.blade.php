@extends('layouts.staff')

@section('title', 'Khidmat Nasihat')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Khidmat Nasihat<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($khidmat->total()) }}</strong> permohonan</p>
        </div>
        @can('khidmat.manage')
            <div class="tap-head__cluster">
                {{-- Slice 3: route through the eligibility screening gate before the wizard. --}}
                <a href="{{ route('khidmat.saringan') }}" class="btn btn--primary">+ Permohonan Baharu</a>
            </div>
        @endcan
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('khidmat.index') }}" class="tap-filters">
        <select name="status_kn" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach ($statusList as $s)
                <option value="{{ $s }}" @selected(($filters['status_kn'] ?? '') === $s)>{{ str_replace('_', ' ', $s) }}</option>
            @endforeach
        </select>
        <select name="status_bayaran" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Bayaran</option>
            <option value="1" @selected(($filters['status_bayaran'] ?? '') === '1')>Sudah Bayar</option>
            <option value="0" @selected(($filters['status_bayaran'] ?? '') === '0')>Belum Bayar</option>
        </select>
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari no. permohonan / nama mangsa…" aria-label="Cari no. permohonan / nama mangsa…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 1.4fr 2fr 1.6fr 1fr 1fr 70px;">
            <div class="tap-table__th">No. Permohonan</div>
            <div class="tap-table__th">Nama Mangsa</div>
            <div class="tap-table__th">Cawangan</div>
            <div class="tap-table__th">Status</div>
            <div class="tap-table__th">Bayaran</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($khidmat as $row)
            <div class="tap-row" style="grid-template-columns: 1.4fr 2fr 1.6fr 1fr 1fr 70px;">
                <div class="tap-row__title">{{ $row->no_permohonan ?? '—' }}</div>
                <div class="tap-row__tujuan">{{ $row->nama_mangsa ?? '—' }}</div>
                <div class="tap-row__tujuan">{{ $row->cawangan->nama ?? '—' }}</div>
                <div class="tap-row__tujuan"><span class="pill pill--received">{{ str_replace('_', ' ', $row->status_kn) }}</span></div>
                <div class="tap-row__tujuan">
                    @if ($row->status_bayaran)
                        <span class="pill" style="color: var(--success);">Sudah</span>
                    @else
                        <span class="pill" style="opacity:.6;">Belum</span>
                    @endif
                </div>
                <div style="text-align:right;"><a href="{{ route('khidmat.show', $row) }}" class="tap-head__btn">›</a></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada permohonan<span class="dot"></span></div>
                <div class="dash-empty__sub">Laraskan carian atau penapis status.</div>
            </div>
        @endforelse

        @if ($khidmat->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $khidmat->currentPage() }} / {{ $khidmat->lastPage() }} · {{ number_format($khidmat->total()) }} permohonan</span>
                <div class="tap-page__nav">
                    @if ($khidmat->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $khidmat->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($khidmat->hasMorePages())
                        <a href="{{ $khidmat->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
