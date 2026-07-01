@extends('layouts.staff')

@section('title', 'Pemprosesan Khidmat Nasihat')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Pemprosesan Khidmat Nasihat<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($khidmat->total()) }}</strong> permohonan dalam cawangan anda</p>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="formerr" style="margin-bottom:14px;">{{ session('error') }}</div>
    @endif

    {{-- Dashboard count tiles (slice A): counts by status for the officer's branch. --}}
    <div class="dash-tiles" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:14px; margin-bottom:18px;">
        <div class="tap-card" style="margin:0;">
            <div class="tap-card__eyebrow">Baharu</div>
            <div style="font-size:28px; font-weight:700;">{{ number_format($counts[\App\Models\KhidmatNasihat::STATUS_BAHARU]) }}</div>
        </div>
        <div class="tap-card" style="margin:0;">
            <div class="tap-card__eyebrow">Dalam Proses</div>
            <div style="font-size:28px; font-weight:700;">{{ number_format($counts[\App\Models\KhidmatNasihat::STATUS_DALAM_PROSES]) }}</div>
        </div>
        <div class="tap-card" style="margin:0;">
            <div class="tap-card__eyebrow">Selesai</div>
            <div style="font-size:28px; font-weight:700;">{{ number_format($counts[\App\Models\KhidmatNasihat::STATUS_SELESAI]) }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('khidmat.proses.index') }}" class="tap-filters">
        <select name="status_kn" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach ($statusList as $s)
                <option value="{{ $s }}" @selected(($filters['status_kn'] ?? '') === $s)>{{ str_replace('_', ' ', $s) }}</option>
            @endforeach
        </select>
        <select name="id_pegawai_kn" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Pegawai</option>
            @foreach ($pegawaiList as $id => $nama)
                <option value="{{ $id }}" @selected((string) ($filters['id_pegawai_kn'] ?? '') === (string) $id)>{{ $nama }}</option>
            @endforeach
        </select>
        <select name="id_kategori" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Kategori</option>
            @foreach ($kategoriList as $k)
                <option value="{{ $k->id }}" @selected((string) ($filters['id_kategori'] ?? '') === (string) $k->id)>{{ $k->jenis_kategori }}</option>
            @endforeach
        </select>
        <input type="date" name="dari" value="{{ $filters['dari'] ?? '' }}" class="tap-chip" onchange="this.form.submit()" aria-label="Dari tarikh">
        <input type="date" name="hingga" value="{{ $filters['hingga'] ?? '' }}" class="tap-chip" onchange="this.form.submit()" aria-label="Hingga tarikh">
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari no. permohonan / nama mangsa…" aria-label="Cari no. permohonan / nama mangsa…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 1.3fr 1.7fr 1.3fr 1fr 1.3fr 1.4fr;">
            <div class="tap-table__th">No. Permohonan</div>
            <div class="tap-table__th">Nama Mangsa</div>
            <div class="tap-table__th">Cawangan</div>
            <div class="tap-table__th">Status</div>
            <div class="tap-table__th">Pegawai (PKN)</div>
            <div class="tap-table__th">Tindakan</div>
        </div>

        @forelse ($khidmat as $row)
            <div class="tap-row" style="grid-template-columns: 1.3fr 1.7fr 1.3fr 1fr 1.3fr 1.4fr;">
                <div class="tap-row__title">{{ $row->no_permohonan ?? '-' }}</div>
                <div class="tap-row__tujuan">{{ $row->nama_mangsa ?? '-' }}</div>
                <div class="tap-row__tujuan">{{ $row->cawangan->nama ?? '-' }}</div>
                <div class="tap-row__tujuan"><span class="pill pill--received">{{ str_replace('_', ' ', $row->status_kn) }}</span></div>
                <div class="tap-row__tujuan">{{ $row->pegawaiKn->name ?? '-' }}</div>
                <div class="tap-row__tujuan" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                    @include('khidmat-nasihat.partials.proses-actions', ['row' => $row, 'pegawaiList' => $pegawaiList])
                    <a href="{{ route('khidmat.show', $row) }}" class="tap-head__btn">›</a>
                </div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada permohonan<span class="dot"></span></div>
                <div class="dash-empty__sub">Laraskan carian atau penapis.</div>
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
