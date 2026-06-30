@extends('layouts.peguam')

@section('title', 'Tuntutan '.$tuntutan->no_tuntutan)

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $tuntutan->no_tuntutan }}<span class="dot"></span></h1>
            <p class="tap-head__sub"><span class="pill pill--received">{{ $tuntutan->statusLabel() }}</span></p>
        </div>
        <a href="{{ route('peguam.tuntutan.index') }}" class="btn">‹ Tuntutan Saya</a>
    </div>

    <div class="card" style="padding:18px;">
        <dl style="display:grid; grid-template-columns: 180px 1fr; gap:8px 16px; margin:0;">
            <dt>No. Fail Kes</dt><dd>{{ $tuntutan->form->no_fail ?? '—' }}</dd>
            <dt>Jenis Tuntutan</dt><dd>{{ $tuntutan->jenis_tuntutan ?? '—' }}</dd>
            <dt>Keterangan</dt><dd>{{ $tuntutan->keterangan ?? '—' }}</dd>
            <dt>Jumlah Tuntutan</dt><dd>RM {{ number_format((float) $tuntutan->jumlah_tuntutan, 2) }}</dd>
            <dt>Jumlah Diluluskan</dt><dd>{{ $tuntutan->jumlah_diluluskan !== null ? 'RM '.number_format((float) $tuntutan->jumlah_diluluskan, 2) : '—' }}</dd>
            <dt>Status Bayaran</dt><dd>{{ $tuntutan->status_bayaran ? 'Sudah dibayar' : 'Belum' }}</dd>
            <dt>No. Resit</dt><dd>{{ $tuntutan->nombor_resit ?? '—' }}</dd>
        </dl>
    </div>
@endsection
