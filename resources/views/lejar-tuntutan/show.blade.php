@extends('layouts.staff')

@section('title', 'Tuntutan '.$tuntutan->no_tuntutan)

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $tuntutan->no_tuntutan }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ str_replace('_', ' ', $tuntutan->sumber) }} · <span class="pill pill--received">{{ $tuntutan->statusLabel() }}</span></p>
        </div>
        <a href="{{ route('tuntutan.index') }}" class="btn">‹ Senarai</a>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="formerr" style="margin-bottom:14px;">{{ session('error') }}</div>
    @endif

    <div class="card" style="padding:18px; margin-bottom:16px;">
        <dl style="display:grid; grid-template-columns: 200px 1fr; gap:8px 16px; margin:0;">
            <dt>No. Fail Kes</dt><dd>{{ $tuntutan->form->no_fail ?? '—' }}</dd>
            <dt>Peguam (KP)</dt><dd>{{ $tuntutan->kp_peguam ?? $tuntutan->peguam->kp_peguam ?? '—' }}</dd>
            <dt>Jenis Tuntutan</dt><dd>{{ $tuntutan->jenis_tuntutan ?? '—' }}</dd>
            <dt>Keterangan</dt><dd>{{ $tuntutan->keterangan ?? '—' }}</dd>
            <dt>Jumlah Tuntutan</dt><dd>RM {{ number_format((float) $tuntutan->jumlah_tuntutan, 2) }}</dd>
            <dt>Jumlah Diluluskan</dt><dd>{{ $tuntutan->jumlah_diluluskan !== null ? 'RM '.number_format((float) $tuntutan->jumlah_diluluskan, 2) : '—' }}</dd>
            <dt>Jumlah Bayaran</dt><dd>{{ $tuntutan->jumlah_bayaran !== null ? 'RM '.number_format((float) $tuntutan->jumlah_bayaran, 2) : '—' }}</dd>
            <dt>No. Resit</dt><dd>{{ $tuntutan->nombor_resit ?? '—' }}</dd>
            <dt>Status Bayaran</dt><dd>{{ $tuntutan->status_bayaran ? 'Sudah dibayar' : 'Belum' }}</dd>
        </dl>
    </div>

    {{-- Lifecycle actions, each gated by permission + current status. --}}
    <div class="card" style="padding:18px; display:flex; gap:10px; flex-wrap:wrap;">
        @if ($tuntutan->status_tuntutan === 'DRAF')
            @can('tuntutan.manage')
                <form method="POST" action="{{ route('tuntutan.hantar', $tuntutan) }}">@csrf<button class="btn btn--primary">Hantar</button></form>
            @endcan
        @endif

        @if ($tuntutan->status_tuntutan === 'DIHANTAR')
            @can('tuntutan.semak')
                <form method="POST" action="{{ route('tuntutan.semak', $tuntutan) }}">@csrf<button class="btn">Mula Semakan</button></form>
            @endcan
        @endif

        @if ($tuntutan->status_tuntutan === 'SEMAKAN')
            @can('tuntutan.lulus')
                <form method="POST" action="{{ route('tuntutan.lulus', $tuntutan) }}" style="display:flex; gap:8px; align-items:center;">
                    @csrf
                    <input type="number" step="0.01" name="jumlah_diluluskan" placeholder="Jumlah diluluskan" value="{{ $tuntutan->jumlah_tuntutan }}" class="tap-chip">
                    <button class="btn btn--primary">Luluskan</button>
                </form>
                <form method="POST" action="{{ route('tuntutan.tolak', $tuntutan) }}" style="display:flex; gap:8px; align-items:center;">
                    @csrf
                    <input type="text" name="ulasan_pelulus" placeholder="Sebab tolak" class="tap-chip" required>
                    <button class="btn">Tolak</button>
                </form>
            @endcan
        @endif

        @if ($tuntutan->status_tuntutan === 'DILULUS')
            @can('tuntutan.bayar')
                <form method="POST" action="{{ route('tuntutan.bayar', $tuntutan) }}" style="display:grid; grid-template-columns: repeat(2, 1fr); gap:8px; max-width:520px;">
                    @csrf
                    <input type="text" name="nombor_resit" placeholder="No. Resit" class="tap-chip" required>
                    <input type="date" name="tarikh_resit" class="tap-chip" required>
                    <input type="text" name="kaedah_bayaran" placeholder="Kaedah (EFT/Cek/Tunai)" class="tap-chip" required>
                    <input type="text" name="rujukan_bayaran" placeholder="Rujukan bayaran" class="tap-chip">
                    <input type="number" step="0.01" name="jumlah_bayaran" placeholder="Jumlah bayaran" value="{{ $tuntutan->jumlah_diluluskan ?? $tuntutan->jumlah_tuntutan }}" class="tap-chip" required>
                    <button class="btn btn--primary">Rekod Bayaran</button>
                </form>
            @endcan
        @endif
    </div>
@endsection
