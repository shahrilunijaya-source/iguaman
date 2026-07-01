@extends('layouts.staff')

@php $label = $jenis === 'syariah' ? 'Syariah' : 'Sivil'; @endphp

@section('title', 'Mahkamah ' . $label)

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Mahkamah {{ $label }}<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($mahkamah->total()) }}</strong> mahkamah berdaftar</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('mahkamah-ref.create', ['jenis' => $jenis]) }}" class="btn btn--primary" style="height:38px;">+ Tambah Mahkamah</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <div class="tap-filters" style="margin-bottom:14px;">
        <a href="{{ route('mahkamah-ref.index', ['jenis' => 'sivil']) }}" class="tap-chip {{ $jenis === 'sivil' ? 'pill--received' : '' }}" style="text-decoration:none;{{ $jenis === 'sivil' ? 'font-weight:600;' : '' }}">Sivil</a>
        <a href="{{ route('mahkamah-ref.index', ['jenis' => 'syariah']) }}" class="tap-chip {{ $jenis === 'syariah' ? 'pill--received' : '' }}" style="text-decoration:none;{{ $jenis === 'syariah' ? 'font-weight:600;' : '' }}">Syariah</a>
    </div>

    <form method="GET" action="{{ route('mahkamah-ref.index', ['jenis' => $jenis]) }}" class="tap-filters">
        <select name="negeri" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Negeri</option>
            @foreach ($negeriList as $n)
                <option value="{{ $n }}" @selected(($filters['negeri'] ?? '') === $n)>{{ $n }}</option>
            @endforeach
        </select>
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama atau negeri…" aria-label="Cari nama atau negeri…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 2fr 1.2fr 1.4fr 1.2fr 90px;">
            <div class="tap-table__th">Nama Mahkamah</div>
            <div class="tap-table__th">Negeri</div>
            <div class="tap-table__th">Lokaliti</div>
            <div class="tap-table__th">Jenis</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($mahkamah as $row)
            <div class="tap-row" style="grid-template-columns: 2fr 1.2fr 1.4fr 1.2fr 90px;">
                <div class="tap-row__title">{{ $row->nama_mahkamah }}</div>
                <div class="tap-row__tujuan">{{ $row->negeri_mahkamah ?: '—' }}</div>
                <div class="tap-row__tujuan">{{ $row->lokaliti_mahkamah ?: '—' }}</div>
                <div class="tap-row__tujuan">{{ $row->jenis_mahkamah ?: '—' }}</div>
                <div style="text-align:right;"><a href="{{ route('mahkamah-ref.edit', ['jenis' => $jenis, 'id' => $row->id]) }}" class="tap-head__btn">✎</a></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada mahkamah<span class="dot"></span></div>
                <div class="dash-empty__sub">Tambah atau laraskan carian.</div>
            </div>
        @endforelse

        @if ($mahkamah->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $mahkamah->currentPage() }} / {{ $mahkamah->lastPage() }} · {{ number_format($mahkamah->total()) }} mahkamah</span>
                <div class="tap-page__nav">
                    @if ($mahkamah->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $mahkamah->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($mahkamah->hasMorePages())
                        <a href="{{ $mahkamah->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
