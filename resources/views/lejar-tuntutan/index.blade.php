@extends('layouts.staff')

@section('title', 'Lejar Tuntutan Bayaran')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Lejar Tuntutan Bayaran<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($tuntutan->total()) }}</strong> tuntutan</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('tuntutan.eksport', request()->query()) }}" class="btn">Eksport Excel</a>
            @can('tuntutan.manage')
                <a href="{{ route('tuntutan.create') }}" class="btn btn--primary">+ Tuntutan Baharu</a>
            @endcan
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="formerr" style="margin-bottom:14px;">{{ session('error') }}</div>
    @endif

    <div class="tap-cards" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
        @foreach ($counts as $status => $n)
            <div class="dash-card" style="padding:12px 16px;">
                <div class="dash-card__num">{{ number_format($n) }}</div>
                <div class="dash-card__label">{{ \App\Models\LejarTuntutanBayaran::STATUS_LABELS[$status] ?? $status }}</div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('tuntutan.index') }}" class="tap-filters">
        <select name="status_tuntutan" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            @foreach ($statusList as $s)
                <option value="{{ $s }}" @selected(($filters['status_tuntutan'] ?? '') === $s)>{{ \App\Models\LejarTuntutanBayaran::STATUS_LABELS[$s] ?? $s }}</option>
            @endforeach
        </select>
        <select name="sumber" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Sumber</option>
            @foreach (['KN','PEMBELAAN_AWAM','MEDIASI','PEGUAM_LUAR','LAIN'] as $s)
                <option value="{{ $s }}" @selected(($filters['sumber'] ?? '') === $s)>{{ str_replace('_', ' ', $s) }}</option>
            @endforeach
        </select>
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari no. tuntutan / KP peguam…" aria-label="Cari no. tuntutan / KP peguam…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 1.4fr 1.1fr 1.6fr 1fr 1fr 70px;">
            <div class="tap-table__th">No. Tuntutan</div>
            <div class="tap-table__th">Sumber</div>
            <div class="tap-table__th">No. Fail / Peguam</div>
            <div class="tap-table__th">Jumlah (RM)</div>
            <div class="tap-table__th">Status</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($tuntutan as $row)
            <div class="tap-row" style="grid-template-columns: 1.4fr 1.1fr 1.6fr 1fr 1fr 70px;">
                <div class="tap-row__title">{{ $row->no_tuntutan ?? '—' }}</div>
                <div class="tap-row__tujuan">{{ str_replace('_', ' ', $row->sumber) }}</div>
                <div class="tap-row__tujuan">{{ $row->form->no_fail ?? $row->kp_peguam ?? '—' }}</div>
                <div class="tap-row__tujuan">{{ number_format((float) $row->jumlah_tuntutan, 2) }}</div>
                <div class="tap-row__tujuan"><span class="pill pill--received">{{ $row->statusLabel() }}</span></div>
                <div style="text-align:right;"><a href="{{ route('tuntutan.show', $row) }}" class="tap-head__btn">›</a></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada tuntutan<span class="dot"></span></div>
                <div class="dash-empty__sub">Laraskan carian atau penapis.</div>
            </div>
        @endforelse

        @if ($tuntutan->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $tuntutan->currentPage() }} / {{ $tuntutan->lastPage() }} · {{ number_format($tuntutan->total()) }} tuntutan</span>
                <span>{{ $tuntutan->onEachSide(1)->links() }}</span>
            </div>
        @endif
    </div>
@endsection
