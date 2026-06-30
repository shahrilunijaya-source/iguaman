@extends('layouts.peguam')

@section('title', 'Tuntutan Saya')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Tuntutan Bayaran Saya<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($tuntutan->total()) }}</strong> tuntutan</p>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 1.4fr 1.6fr 1fr 1fr 70px;">
            <div class="tap-table__th">No. Tuntutan</div>
            <div class="tap-table__th">No. Fail Kes</div>
            <div class="tap-table__th">Jumlah (RM)</div>
            <div class="tap-table__th">Status</div>
            <div class="tap-table__th"></div>
        </div>
        @forelse ($tuntutan as $row)
            <div class="tap-row" style="grid-template-columns: 1.4fr 1.6fr 1fr 1fr 70px;">
                <div class="tap-row__title">{{ $row->no_tuntutan }}</div>
                <div class="tap-row__tujuan">{{ $row->form->no_fail ?? '—' }}</div>
                <div class="tap-row__tujuan">{{ number_format((float) $row->jumlah_tuntutan, 2) }}</div>
                <div class="tap-row__tujuan"><span class="pill pill--received">{{ $row->statusLabel() }}</span></div>
                <div style="text-align:right;"><a href="{{ route('peguam.tuntutan.show', $row) }}" class="tap-head__btn">›</a></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada tuntutan<span class="dot"></span></div>
                <div class="dash-empty__sub">Failkan tuntutan dari halaman kes yang ditugaskan kepada anda.</div>
            </div>
        @endforelse
    </div>
@endsection
