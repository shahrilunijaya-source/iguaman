@extends('layouts.staff')

@section('title', 'Cuti Negeri')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Cuti Negeri<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($cuti->total()) }}</strong> cuti negeri berdaftar</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('cuti-negeri.create') }}" class="btn btn--primary" style="height:38px;">+ Daftar Cuti Negeri</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('cuti-negeri.index') }}" class="tap-filters">
        <select name="akan" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Tarikh</option>
            <option value="1" @selected(($filters['akan'] ?? '') === '1')>Akan Datang</option>
        </select>
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama cuti…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 1.6fr 1.4fr 2.4fr 70px;">
            <div class="tap-table__th">Nama Cuti</div>
            <div class="tap-table__th">Tarikh</div>
            <div class="tap-table__th">Negeri Terlibat</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($cuti as $row)
            @php
                $names = \App\Support\CutiNegeri::labels($row->idnegeri, $negeriList);
            @endphp
            <div class="tap-row" style="grid-template-columns: 1.6fr 1.4fr 2.4fr 70px;">
                <div class="tap-row__title">{{ $row->nama_cuti }}</div>
                <div class="tap-row__tujuan">
                    {{ optional($row->tarikh_mula)->format('d/m/Y') ?: '—' }}
                    @if ($row->tarikh_tamat && optional($row->tarikh_mula)?->format('Y-m-d') !== $row->tarikh_tamat->format('Y-m-d'))
                        – {{ $row->tarikh_tamat->format('d/m/Y') }}
                    @endif
                </div>
                <div class="tap-row__tujuan">
                    @if (count($names))
                        {{ implode(', ', $names) }}
                    @else
                        —
                    @endif
                </div>
                <div style="text-align:right;"><a href="{{ route('cuti-negeri.edit', $row) }}" class="tap-head__btn">✎</a></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada cuti negeri<span class="dot"></span></div>
                <div class="dash-empty__sub">Daftar cuti negeri atau laraskan carian.</div>
            </div>
        @endforelse

        @if ($cuti->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $cuti->currentPage() }} / {{ $cuti->lastPage() }} · {{ number_format($cuti->total()) }} cuti</span>
                <div class="tap-page__nav">
                    @if ($cuti->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $cuti->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($cuti->hasMorePages())
                        <a href="{{ $cuti->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
