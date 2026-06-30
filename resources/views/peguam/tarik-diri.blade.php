@extends('layouts.peguam')

@section('title', 'Tarik Diri Mewakili OYD')

@section('content')
<style>
    .td-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
    .td-grid .col-2 { grid-column:1/-1; }
    .td-note { background:rgba(26,111,168,.08); border:1px solid var(--line); border-radius:10px; padding:14px 16px; font-size:13px; margin-bottom:18px; }
    .req { color:var(--danger,#dc2626); }
    @media (max-width:640px){ .td-grid { grid-template-columns:1fr; } }
</style>

<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Tarik Diri Mewakili OYD<span class="dot"></span></h1>
        <p class="tap-head__sub">Kes #{{ $kes->id }} · {{ $kes->no_fail ?: '—' }}</p>
    </div>
    <a href="{{ route('peguam.kes.show', $kes) }}" class="btn btn--ghost">← Kes</a>
</div>

@if ($errors->any())
    <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
@endif

<div class="td-note">
    <strong>Perhatian — Seksyen 24, Akta Bantuan Guaman 1971.</strong>
    Permohonan tarik diri akan disemak oleh PPUU, Pengarah, dan diluluskan oleh Ketua Pengarah.
    Peguam dikehendaki meneruskan tanggungjawab sehingga kelulusan diperoleh.
</div>

<form method="POST" action="{{ route('peguam.tarikdiri.store', $kes) }}" enctype="multipart/form-data" class="tap-card">
    @csrf
    <div class="td-grid">
        <div class="ag-row col-2" style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line);">
            <span style="color:var(--mute);font-size:13px;">OYD</span>
            <strong style="font-size:13px;">{{ $kes->nama ?: '—' }} ({{ $kes->nokp ?: '—' }})</strong>
        </div>
        <div class="field col-2">
            <label class="field__label">Sebab Tarik Diri <span class="req">*</span></label>
            <select name="pilihanTarikDiri" class="field__input" required>
                <option value="" disabled {{ old('pilihanTarikDiri') ? '' : 'selected' }}>Pilih sebab…</option>
                @foreach ($reasons as $r)
                    <option value="{{ $r }}" {{ old('pilihanTarikDiri') === $r ? 'selected' : '' }}>{{ $r }}</option>
                @endforeach
            </select>
        </div>
        <div class="field col-2">
            <label class="field__label">Penjelasan / Justifikasi <span class="req">*</span></label>
            <textarea name="alasan" class="field__input" rows="4" maxlength="600" required>{{ old('alasan') }}</textarea>
        </div>
        <div class="field">
            <label class="field__label">Tarikh Bicara Kes Seterusnya</label>
            <input type="date" name="tarikhNextBicaraKes" class="field__input" value="{{ old('tarikhNextBicaraKes') }}">
        </div>
        <div class="field">
            <label class="field__label">Surat Akuan Tarik Diri (PDF)</label>
            <input type="file" name="akuanTarikDiri" class="field__input" accept=".pdf">
        </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;">
        <a href="{{ route('peguam.kes.show', $kes) }}" class="btn btn--ghost">Batal</a>
        <button type="submit" class="btn btn--primary">Hantar Permohonan Tarik Diri</button>
    </div>
</form>
@endsection
