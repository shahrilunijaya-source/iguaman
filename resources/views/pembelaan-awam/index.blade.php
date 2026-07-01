@extends('layouts.staff')

@section('title', 'Pembelaan Awam')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Pembelaan Awam<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($kes->total()) }}</strong> fail pembelaan jenayah</p>
        </div>
        <div class="tap-head__cluster">
            @can('pembelaan.manage')
                <a href="{{ route('pembelaan.create') }}" class="btn btn--primary">+ Pembelaan Baharu</a>
            @endcan
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="formerr" style="margin-bottom:14px;">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('pembelaan.index') }}" class="tap-filters">
        <select name="cawangan" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Cawangan</option>
            @foreach ($cawanganList as $c)
                <option value="{{ $c }}" @selected(($filters['cawangan'] ?? '') === $c)>{{ $c }}</option>
            @endforeach
        </select>
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama / KP / no. fail / no. pertuduhan…" aria-label="Cari nama / KP / no. fail / no. pertuduhan…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 1.5fr 1.4fr 1.3fr 1fr 70px;">
            <div class="tap-table__th">No. Fail</div>
            <div class="tap-table__th">OYD / Tertuduh</div>
            <div class="tap-table__th">No. Pertuduhan</div>
            <div class="tap-table__th">Status Agihan</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($kes as $row)
            <div class="tap-row" style="grid-template-columns: 1.5fr 1.4fr 1.3fr 1fr 70px;">
                <div class="tap-row__title">
                    {{ $row->no_fail ?? '—' }}
                    @if ($row->is_segera)<span style="margin-left:6px; font-size:11px; font-weight:700; color:#b91c1c; background:rgba(185,28,28,0.1); padding:1px 7px; border-radius:6px;">SEGERA</span>@endif
                </div>
                <div class="tap-row__tujuan">{{ $row->nama ?? '—' }}<br><span style="color:var(--muted,#64748b); font-size:12px;">{{ $row->nokp ?? '' }}</span></div>
                <div class="tap-row__tujuan">{{ $row->no_pertuduhan ?? '—' }}</div>
                <div class="tap-row__tujuan"><span class="pill pill--received">{{ \App\Support\StatusAgihan::label($row->status_agihan) }}</span></div>
                <div style="text-align:right;"><a href="{{ route('pembelaan.show', $row) }}" class="tap-head__btn">›</a></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada fail pembelaan<span class="dot"></span></div>
                <div class="dash-empty__sub">Daftar permohonan Pembelaan Awam baharu atau laraskan carian.</div>
            </div>
        @endforelse

        @if ($kes->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $kes->currentPage() }} / {{ $kes->lastPage() }} · {{ number_format($kes->total()) }} fail</span>
                <span>{{ $kes->onEachSide(1)->links() }}</span>
            </div>
        @endif
    </div>
@endsection
