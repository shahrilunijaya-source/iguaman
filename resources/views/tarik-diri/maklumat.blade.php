@extends('layouts.staff')

@section('title', 'Tarik Diri · Kes #'.$kes->id)

@section('content')
<style>
    .td-row { display:flex; justify-content:space-between; gap:12px; padding:6px 0; border-bottom:1px solid var(--line); }
    .td-row .k { color:var(--mute); font-size:13px; } .td-row .v { font-weight:600; font-size:13px; text-align:right; }
    .td-badge { display:inline-block; padding:4px 12px; border-radius:999px; background:rgba(220,38,38,.1); color:#dc2626; font-weight:600; font-size:12px; }
    .td-sec { margin:20px 0 10px; font-weight:600; padding-bottom:6px; border-bottom:1px solid var(--line); }
    .radio-row { display:flex; gap:18px; align-items:center; } .radio-row label { display:flex; gap:6px; align-items:center; font-size:13px; }
    .req { color:var(--danger,#dc2626); }
</style>

<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Semakan Tarik Diri - Kes #{{ $kes->id }}<span class="dot"></span></h1>
        <p class="tap-head__sub">No. Fail: {{ $kes->no_fail ?: '-' }}</p>
    </div>
    <a href="{{ route('tarikdiri.senarai') }}" class="btn btn--ghost">← Senarai</a>
</div>

@if (session('status'))
    <div class="formerr" style="color:var(--success);background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.18);margin-bottom:16px;">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
@endif

<div class="tap-card">
    <span class="td-badge">{{ $statusLabel }}</span>
    <div style="margin-top:12px;">
        <div class="td-row"><div class="k">OYD</div><div class="v">{{ $kes->nama ?: '-' }} ({{ $kes->nokp ?: '-' }})</div></div>
        <div class="td-row"><div class="k">Peguam</div><div class="v">{{ $kes->nama_pegawai_yang_dapat_kes ?: '-' }}</div></div>
        @if ($rec)
            <div class="td-row"><div class="k">Sebab Tarik Diri</div><div class="v">{{ $rec->pilihanTarikDiri ?: '-' }}</div></div>
            <div class="td-row"><div class="k">Justifikasi</div><div class="v">{{ $rec->alasan ?: '-' }}</div></div>
            <div class="td-row"><div class="k">Tarikh Bicara Seterusnya</div><div class="v">{{ optional($rec->tarikhNextBicaraKes)->format('d/m/Y') ?: '-' }}</div></div>
            @if ($rec->ulasanPPUU)<div class="td-row"><div class="k">Ulasan PPUU</div><div class="v">{{ $rec->ulasanPPUU }}</div></div>@endif
            @if ($rec->ulasanPengarah)<div class="td-row"><div class="k">Ulasan Pengarah</div><div class="v">{{ $rec->ulasanPengarah }}</div></div>@endif
        @endif
    </div>
</div>

@if ($stage === 'ppuu')
    <div class="tap-card" style="margin-top:18px;">
        <div class="td-sec" style="margin-top:0;">Semakan PPUU</div>
        <form method="POST" action="{{ route('tarikdiri.ppuu', $kes) }}">
            @csrf
            <div class="field col-2">
                <label class="field__label">Ulasan PPUU <span class="req">*</span></label>
                <textarea name="ulasan" class="field__input" rows="3" maxlength="350" required></textarea>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:12px;">Hantar ke Pengarah</button>
        </form>
    </div>
@elseif ($stage === 'pengarah')
    <div class="tap-card" style="margin-top:18px;">
        <div class="td-sec" style="margin-top:0;">Semakan Pengarah</div>
        <form method="POST" action="{{ route('tarikdiri.pengarah', $kes) }}">
            @csrf
            <div class="field col-2">
                <label class="field__label">Ulasan Pengarah <span class="req">*</span></label>
                <textarea name="ulasan" class="field__input" rows="3" maxlength="350" required></textarea>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:12px;">Hantar ke Ketua Pengarah</button>
        </form>
    </div>
@elseif ($stage === 'kp')
    <div class="tap-card" style="margin-top:18px;">
        <div class="td-sec" style="margin-top:0;">Keputusan Ketua Pengarah</div>
        <form method="POST" action="{{ route('tarikdiri.kp', $kes) }}">
            @csrf
            <div class="field col-2">
                <label class="field__label">Keputusan <span class="req">*</span></label>
                <div class="radio-row">
                    <label><input type="radio" name="keputusan" value="lulus" checked> Diluluskan → Agih Semula</label>
                    <label><input type="radio" name="keputusan" value="tolak"> Tidak Diluluskan → Peguam Teruskan</label>
                </div>
            </div>
            <div class="field col-2" style="margin-top:10px;">
                <label class="field__label">Ulasan (wajib jika tidak diluluskan)</label>
                <textarea name="ulasan" class="field__input" rows="3" maxlength="350"></textarea>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:12px;">Rekod Keputusan</button>
        </form>
    </div>
@else
    <div class="tap-card" style="margin-top:18px;">
        <div class="dash-empty__sub" style="padding:8px 0;">Tiada tindakan tarik diri untuk peranan anda pada status semasa.</div>
    </div>
@endif
@endsection
