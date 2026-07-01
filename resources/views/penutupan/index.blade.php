@extends('layouts.staff')

@section('title', 'Penutupan Operasi')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Penutupan Operasi<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($penutupan->total()) }}</strong> rekod penutupan</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('penutupan.create') }}" class="btn btn--primary" style="height:38px;">+ Tambah Penutupan</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('penutupan.index') }}" class="tap-filters">
        <select name="cawangan_id" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Cawangan</option>
            @foreach ($cawanganList as $id => $nama)
                <option value="{{ $id }}" @selected((string) ($filters['cawangan_id'] ?? '') === (string) $id)>{{ $nama }}</option>
            @endforeach
        </select>
        <select name="akan" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Tarikh</option>
            <option value="1" @selected(($filters['akan'] ?? '') === '1')>Akan Datang</option>
        </select>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 1.6fr 1.2fr 1.4fr 2fr 70px;">
            <div class="tap-table__th">Cawangan</div>
            <div class="tap-table__th">Bilik</div>
            <div class="tap-table__th">Tarikh</div>
            <div class="tap-table__th">Sebab</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($penutupan as $row)
            <div class="tap-row" style="grid-template-columns: 1.6fr 1.2fr 1.4fr 2fr 70px;">
                <div class="tap-row__title">{{ $row->cawangan->nama ?? '-' }}</div>
                <div class="tap-row__tujuan">{{ $row->bilik->nama_bilik ?? 'Seluruh cawangan' }}</div>
                <div class="tap-row__tujuan">
                    {{ optional($row->tarikh_mula)->format('d/m/Y') ?: '-' }}
                    @if ($row->tarikh_tamat && optional($row->tarikh_mula)?->format('Y-m-d') !== $row->tarikh_tamat->format('Y-m-d'))
                        – {{ $row->tarikh_tamat->format('d/m/Y') }}
                    @endif
                </div>
                <div class="tap-row__tujuan">{{ $row->sebab ?: '-' }}</div>
                <div style="text-align:right;">
                    <form method="POST" action="{{ route('penutupan.destroy', $row) }}" onsubmit="return confirm('Padam penutupan ini?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="tap-head__btn" style="color:var(--danger);">✕</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada penutupan<span class="dot"></span></div>
                <div class="dash-empty__sub">Tambah penutupan operasi atau laraskan tapisan.</div>
            </div>
        @endforelse

        @if ($penutupan->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $penutupan->currentPage() }} / {{ $penutupan->lastPage() }} · {{ number_format($penutupan->total()) }} rekod</span>
                <div class="tap-page__nav">
                    @if ($penutupan->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $penutupan->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($penutupan->hasMorePages())
                        <a href="{{ $penutupan->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
