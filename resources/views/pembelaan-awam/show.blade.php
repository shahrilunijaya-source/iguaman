@extends('layouts.staff')

@section('title', 'Pembelaan — '.($kes->no_fail ?? ('#'.$kes->id)))

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">
                {{ $kes->no_fail ?? ('Kes #'.$kes->id) }}<span class="dot"></span>
                @if ($kes->is_segera)<span style="margin-left:8px; font-size:12px; font-weight:700; color:#b91c1c; background:rgba(185,28,28,0.1); padding:2px 9px; border-radius:6px;">SEGERA</span>@endif
            </h1>
            <p class="tap-head__sub">{{ $kes->nama ?? 'OYD' }} · {{ $statusAgihan }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('pembelaan.index') }}" class="btn">‹ Senarai</a>
            <a href="{{ route('agihan.maklumat', $kes) }}" class="btn btn--primary">Agihan / Tindakan</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="formerr" style="margin-bottom:14px;">{{ session('error') }}</div>
    @endif

    @php
        $f = fn ($v) => $v instanceof \Illuminate\Support\Carbon ? $v->format('d/m/Y') : (($v === null || $v === '') ? '—' : $v);
    @endphp

    <div class="card" style="padding:18px; margin-bottom:16px;">
        <h2 style="margin:0 0 12px; font-size:15px;">Butiran Tertuduh &amp; Pertuduhan</h2>
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px 24px;">
            <div><span style="color:var(--muted,#64748b); font-size:12px;">Nama Tertuduh</span><br>{{ $f($kes->nama) }}</div>
            <div><span style="color:var(--muted,#64748b); font-size:12px;">No. KP</span><br>{{ $f($kes->nokp) }}</div>
            <div><span style="color:var(--muted,#64748b); font-size:12px;">Cawangan</span><br>{{ $f($kes->cawangan) }}</div>
            <div><span style="color:var(--muted,#64748b); font-size:12px;">Jenis Permohonan</span><br>{{ $f($kes->jenis_pemohonan_pembelaan) }}</div>
            <div><span style="color:var(--muted,#64748b); font-size:12px;">No. Pertuduhan</span><br>{{ $f($kes->no_pertuduhan) }}</div>
            <div><span style="color:var(--muted,#64748b); font-size:12px;">Seksyen Kesalahan</span><br>{{ $f($kes->seksyen_kesalahan) }}</div>
            <div><span style="color:var(--muted,#64748b); font-size:12px;">Mahkamah</span><br>{{ $f($kes->mahkamah_pembelaan) }}</div>
            <div><span style="color:var(--muted,#64748b); font-size:12px;">Tarikh Pertuduhan</span><br>{{ $f($kes->tarikh_pertuduhan) }}</div>
        </div>
    </div>

    <div class="card" style="padding:18px; margin-bottom:16px;">
        <h2 style="margin:0 0 12px; font-size:15px;">Status Agihan &amp; Peguam</h2>
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px 24px;">
            <div><span style="color:var(--muted,#64748b); font-size:12px;">Status Agihan</span><br>{{ $statusAgihan }}</div>
            <div><span style="color:var(--muted,#64748b); font-size:12px;">Peguam Panel</span><br>{{ $f($kes->nama_pegawai_yang_dapat_kes) }}</div>
            <div><span style="color:var(--muted,#64748b); font-size:12px;">Tarikh Penugasan</span><br>{{ $f($kes->tarikh_penugasan_peguam_panel) }}</div>
            <div><span style="color:var(--muted,#64748b); font-size:12px;">Tarikh Tutup Fail</span><br>{{ $f($kes->tarikh_tutup_fail) }}</div>
        </div>
        @if (filled($kes->tarikh_tutup_fail))
            <div style="margin-top:12px;"><a href="{{ route('cetak.penutupan', $kes) }}" class="btn" target="_blank">Cetak Surat Penutupan</a></div>
        @endif
    </div>

    {{-- W14 certificate panel is injected here. --}}
    @includeIf('pembelaan-awam._perakuan', ['kes' => $kes])
@endsection
