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

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="formerr" style="margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

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

    @if ($tuntutan->status_tuntutan === \App\Models\LejarTuntutanBayaran::STATUS_DRAF)
        {{-- W5: a claim seeded at external-lawyer assignment — fill the amount + submit. --}}
        <div class="card" style="padding:18px; margin-top:16px; border-left:3px solid var(--brand,#1a6fa8);">
            <h3 style="margin:0 0 4px; font-size:15px;">Lengkapkan &amp; Hantar Tuntutan</h3>
            <p class="dash-empty__sub" style="margin:0 0 12px;">Tuntutan ini disediakan semasa kes diagihkan kepada anda. Isi jumlah dan hantar untuk semakan JBG.</p>
            <form method="POST" action="{{ route('peguam.tuntutan.lengkap', $tuntutan) }}" class="va-form" onsubmit="return confirm('Hantar tuntutan ini untuk semakan? Tidak boleh diubah selepas dihantar.')">
                @csrf
                <input class="field__input" name="jenis_tuntutan" value="{{ old('jenis_tuntutan', $tuntutan->jenis_tuntutan) }}" placeholder="Jenis tuntutan" maxlength="100" required>
                <textarea class="field__input" name="keterangan" rows="2" placeholder="Keterangan (pilihan)" aria-label="Keterangan (pilihan)">{{ old('keterangan', $tuntutan->keterangan) }}</textarea>
                <input class="field__input" type="number" step="0.01" min="0.01" name="jumlah_tuntutan" value="{{ old('jumlah_tuntutan') }}" placeholder="Jumlah tuntutan (RM)" aria-label="Jumlah tuntutan (RM)" required>
                <button type="submit" class="btn btn--primary">Hantar Tuntutan</button>
            </form>
        </div>
    @endif
@endsection
