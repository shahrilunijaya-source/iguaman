@extends('layouts.staff')

@section('title', 'Senarai Kes')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Senarai Kes<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($kes->total()) }}</strong> kes direkodkan</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('kes.create') }}" class="btn btn--primary" style="height:38px;">+ Permohonan Baharu</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">
            {{ session('status') }}
        </div>
    @endif

    <form method="GET" action="{{ route('kes.index') }}" class="tap-filters">
        <select name="cawangan" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Cawangan</option>
            @foreach ($cawanganList as $c)
                <option value="{{ $c }}" @selected(($filters['cawangan'] ?? '') === $c)>{{ $c }}</option>
            @endforeach
        </select>

        <select name="kategori" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            @foreach ($kategoriList as $k)
                <option value="{{ $k }}" @selected(($filters['kategori'] ?? '') === $k)>{{ $k }}</option>
            @endforeach
        </select>

        <select name="status" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach ($statusList as $s)
                <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
            @endforeach
        </select>

        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama, no. KP, atau no. fail…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 150px 2fr 1fr 1.2fr 1fr 120px;">
            <div class="tap-table__th">No. Fail</div>
            <div class="tap-table__th">Pemohon</div>
            <div class="tap-table__th">Cawangan</div>
            <div class="tap-table__th">Kategori</div>
            <div class="tap-table__th">Status</div>
            <div class="tap-table__th">Tarikh Mohon</div>
        </div>

        @forelse ($kes as $row)
            <a href="{{ route('kes.show', $row) }}" class="tap-row" style="grid-template-columns: 150px 2fr 1fr 1.2fr 1fr 120px;">
                <div class="tap-row__no">{{ $row->no_fail ?: '—' }}</div>
                <div>
                    <div class="tap-row__title">{{ $row->nama ?: 'Tanpa Nama' }}</div>
                    <div class="tap-row__sub">{{ $row->nokp ?: '—' }}</div>
                </div>
                <div class="tap-row__tujuan">{{ $row->cawangan ?: '—' }}</div>
                <div class="tap-row__tujuan">{{ $row->kategori_kes ?: '—' }}</div>
                <div><span class="pill pill--received">{{ $row->status ?: 'baru' }}</span></div>
                <div class="tap-row__due">
                    <div class="tap-row__due-label">{{ optional($row->tarikh_permohonan)->format('d/m/Y') ?: '—' }}</div>
                </div>
            </a>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada kes dijumpai<span class="dot"></span></div>
                <div class="dash-empty__sub">Laraskan penapis atau carian.</div>
            </div>
        @endforelse

        @if ($kes->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $kes->currentPage() }} / {{ $kes->lastPage() }} · {{ number_format($kes->total()) }} kes</span>
                <div class="tap-page__nav">
                    @if ($kes->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $kes->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($kes->hasMorePages())
                        <a href="{{ $kes->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
