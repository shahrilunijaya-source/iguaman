@extends('layouts.peguam')

@section('title', 'Kemaskini Profil')

@section('content')
@php
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d') : '';
    $hasDoc = fn ($t) => in_array($t, $docs, true);
@endphp
<style>
    .pe-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; }
    .pe-grid .col-2 { grid-column: 1 / -1; }
    .pe-sec { margin: 26px 0 10px; padding-bottom: 6px; border-bottom: 1px solid var(--line); font-weight: 600; }
    .pe-sub { margin: 16px 0 6px; font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--mute); }
    .pe-cso { border: 1px dashed var(--line); border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; }
    .pe-doc { font-size: 11px; color: var(--success, #10b981); margin-top: 4px; }
    .req { color: var(--danger, #dc2626); }
    .radio-row { display: flex; gap: 18px; align-items: center; }
    .radio-row label { display: flex; gap: 6px; align-items: center; font-size: 13px; }
    @media (max-width: 640px) { .pe-grid { grid-template-columns: 1fr; } }
</style>

<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Kemaskini Profil<span class="dot"></span></h1>
        <p class="tap-head__sub">Maklumat panel, kelayakan, firma &amp; akaun</p>
    </div>
    <a href="{{ route('peguam.profil') }}" class="btn btn--ghost">← Kembali</a>
</div>

@if ($errors->any())
    <div class="formerr" style="margin-bottom: 16px; flex-direction: column; align-items: flex-start; gap: 4px;">
        <strong>{{ $errors->count() }} ralat perlu dibetulkan:</strong>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

<form method="POST" action="{{ route('peguam.profil.update') }}" enctype="multipart/form-data" class="tap-card">
    @csrf

    <div class="pe-sec">1 · Butiran Peguam</div>
    <p class="tap-head__sub" style="margin:-4px 0 10px;">Nama, No. KP &amp; jantina tidak boleh diubah. Hubungi pentadbir jika perlu pindaan.</p>
    <div class="pe-grid">
        <div class="field">
            <label class="field__label">No. Telefon (Bimbit) <span class="req">*</span></label>
            <input type="text" name="noTelBimbit" class="field__input" value="{{ old('noTelBimbit', $p2->noTelBimbit) }}" maxlength="20" required>
        </div>
        <div class="field">
            <label class="field__label">Emel <span class="req">*</span></label>
            <input type="email" name="emelPeguam" class="field__input" value="{{ old('emelPeguam', $p2->emelPeguam) }}" maxlength="255" required>
        </div>
        <div class="field col-2">
            <label class="field__label">Kelulusan Akademik <span class="req">*</span></label>
            <textarea name="kelulusanAkademik" class="field__input" rows="2" maxlength="500" required style="text-transform:uppercase;">{{ old('kelulusanAkademik', $p2->kelulusanAkademik) }}</textarea>
        </div>
        <div class="field">
            <label class="field__label">Tarikh Diterima Masuk (Sivil) <span class="req">*</span></label>
            <input type="date" name="tarikhDiterimaMasuk" class="field__input" value="{{ old('tarikhDiterimaMasuk', $d($p2->tarikhDiterimaMasuk)) }}" required>
        </div>
        <div class="field">
            <label class="field__label">Tarikh Diterima Masuk (Syarie)</label>
            <input type="date" name="tarikhDiterimaMasukSyarie" class="field__input" value="{{ old('tarikhDiterimaMasukSyarie', $d($p2->tarikhDiterimaMasukSyarie)) }}">
        </div>
        <div class="field">
            <label class="field__label">Tahun Pengalaman (Sivil) <span class="req">*</span></label>
            <input type="number" name="tahunPengalaman" class="field__input" value="{{ old('tahunPengalaman', $p2->tahunPengalaman) }}" min="0" max="99" required>
        </div>
        <div class="field">
            <label class="field__label">Tahun Pengalaman (Syarie)</label>
            <input type="number" name="tahunPengalamanSyarie" class="field__input" value="{{ old('tahunPengalamanSyarie', $p2->tahunPengalamanSyarie) }}" min="0" max="99">
        </div>
        <div class="field">
            <label class="field__label">Bilangan Kes Dikendalikan <span class="req">*</span></label>
            <input type="number" name="bilanganKes" class="field__input" value="{{ old('bilanganKes', $p2->bilanganKes) }}" min="0" max="99999" required>
        </div>
        <div class="field col-2">
            <label class="field__label">Keterangan / Rumusan Kes <span class="req">*</span></label>
            <textarea name="keteranganKes" class="field__input" rows="3" maxlength="2000" required>{{ old('keteranganKes', $p2->keteranganKes) }}</textarea>
        </div>
    </div>

    <div class="pe-sec">2 · Kelayakan Profesion</div>
    <div class="pe-sub">Sijil Amalan Guaman (CLP)</div>
    <div class="pe-grid">
        <div class="field">
            <label class="field__label">No. Sijil Amalan Guaman <span class="req">*</span></label>
            <input type="text" name="clpNumber" class="field__input" value="{{ old('clpNumber', $p3->clpNumber) }}" maxlength="255" required style="text-transform:uppercase;">
            @if ($hasDoc('clp'))<div class="pe-doc">✓ Sijil dimuat naik</div>@endif
        </div>
        <div class="field">
            <label class="field__label">Ganti Sijil CLP (PDF)</label>
            <input type="file" name="clp" class="field__input" accept=".pdf">
        </div>
        <div class="field">
            <label class="field__label">Tempoh Sah - Mula <span class="req">*</span></label>
            <input type="date" name="clpMula" class="field__input" value="{{ old('clpMula', $d($p3->clpMula)) }}" required>
        </div>
        <div class="field">
            <label class="field__label">Tempoh Sah - Hingga <span class="req">*</span></label>
            <input type="date" name="clpAkhir" class="field__input" value="{{ old('clpAkhir', $d($p3->clpAkhir)) }}" required>
        </div>
    </div>

    <div class="pe-sub">Kelulusan Latihan YBGK</div>
    <div class="pe-grid">
        <div class="field col-2">
            <div class="radio-row">
                @foreach (['Ya','Tidak','Pengecualian'] as $opt)
                    <label><input type="radio" name="ybgk_kelulusan" value="{{ $opt }}" {{ old('ybgk_kelulusan', $p3->ybgk_kelulusan) === $opt ? 'checked' : '' }}> {{ $opt }}</label>
                @endforeach
            </div>
        </div>
        <div class="field">
            <label class="field__label">Tarikh Lulus (A)</label>
            <input type="date" name="ybgk_tarikhLulus_A" class="field__input" value="{{ old('ybgk_tarikhLulus_A', $d($p3->ybgk_tarikhLulus_A)) }}">
        </div>
        <div class="field">
            <label class="field__label">Tarikh Lulus (B)</label>
            <input type="date" name="ybgk_tarikhLulus_B" class="field__input" value="{{ old('ybgk_tarikhLulus_B', $d($p3->ybgk_tarikhLulus_B)) }}">
        </div>
        <div class="field">
            <label class="field__label">No. Daftar YBGK</label>
            <input type="text" name="ybgk_daftar" class="field__input" value="{{ old('ybgk_daftar', $p3->ybgk_daftar) }}" maxlength="255">
        </div>
        <div class="field">
            <label class="field__label">Ganti Sijil YBGK (PDF)</label>
            <input type="file" name="certkelulusanYBGK" class="field__input" accept=".pdf">
            @if ($hasDoc('certkelulusanYBGK'))<div class="pe-doc">✓ Sijil dimuat naik</div>@endif
        </div>
    </div>

    <div class="pe-sub">Sijil Peguam Syarie (CSO 1–5)</div>
    @foreach (range(1, 5) as $i)
        <div class="pe-cso">
            <strong style="font-size:13px;">Sijil {{ $i }}</strong>
            <div class="pe-grid" style="margin-top:8px;">
                <div class="field">
                    <label class="field__label">No. Sijil ({{ $i }})</label>
                    <input type="text" name="csoNumber{{ $i }}" class="field__input" value="{{ old('csoNumber'.$i, $p3->{'csoNumber'.$i}) }}" maxlength="255" style="text-transform:uppercase;">
                </div>
                <div class="field">
                    <label class="field__label">Negeri Tauliah</label>
                    <select name="cso{{ $i }}Tauliah" class="field__input">
                        <option value="">-- Sila Pilih --</option>
                        @foreach ($negeriList as $negeri)
                            <option value="{{ $negeri }}" {{ old('cso'.$i.'Tauliah', $p3->{'cso'.$i.'Tauliah'}) === $negeri ? 'selected' : '' }}>{{ $negeri }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field__label">Tempoh - Mula</label>
                    <input type="date" name="cso{{ $i }}Mula" class="field__input" value="{{ old('cso'.$i.'Mula', $d($p3->{'cso'.$i.'Mula'})) }}">
                </div>
                <div class="field">
                    <label class="field__label">Tempoh - Hingga</label>
                    <input type="date" name="cso{{ $i }}Akhir" class="field__input" value="{{ old('cso'.$i.'Akhir', $d($p3->{'cso'.$i.'Akhir'})) }}">
                </div>
                <div class="field col-2">
                    <label class="field__label">Ganti Sijil Guaman Syariah {{ $i }} (PDF)</label>
                    <input type="file" name="cso{{ $i }}" class="field__input" accept=".pdf">
                    @if ($hasDoc('cso'.$i))<div class="pe-doc">✓ Sijil dimuat naik</div>@endif
                </div>
            </div>
        </div>
    @endforeach

    <div class="pe-sub">ADR · Sijil Ahli &amp; Akreditasi · e-Vendor</div>
    <div class="pe-grid">
        <div class="field">
            <label class="field__label">Penimbang Tara</label>
            <div class="radio-row">
                <label><input type="radio" name="adr_penimbangtara" value="Ya" {{ old('adr_penimbangtara', $p3->adr_penimbangtara) === 'Ya' ? 'checked' : '' }}> Ya</label>
                <label><input type="radio" name="adr_penimbangtara" value="Tidak" {{ old('adr_penimbangtara', $p3->adr_penimbangtara) === 'Tidak' ? 'checked' : '' }}> Tidak</label>
            </div>
        </div>
        <div class="field">
            <label class="field__label">Pengantara</label>
            <div class="radio-row">
                <label><input type="radio" name="adr_pengantara" value="Ya" {{ old('adr_pengantara', $p3->adr_pengantara) === 'Ya' ? 'checked' : '' }}> Ya</label>
                <label><input type="radio" name="adr_pengantara" value="Tidak" {{ old('adr_pengantara', $p3->adr_pengantara) === 'Tidak' ? 'checked' : '' }}> Tidak</label>
            </div>
        </div>
        <div class="field">
            <label class="field__label">No. Sijil Ahli</label>
            <input type="text" name="sijilAhli_nombor" class="field__input" value="{{ old('sijilAhli_nombor', $p3->sijilAhli_nombor) }}" maxlength="255" style="text-transform:uppercase;">
        </div>
        <div class="field">
            <label class="field__label">Nama Badan (Sijil Ahli)</label>
            <input type="text" name="sijilAhli_namaBadan" class="field__input" value="{{ old('sijilAhli_namaBadan', $p3->sijilAhli_namaBadan) }}" maxlength="255" style="text-transform:uppercase;">
        </div>
        <div class="field">
            <label class="field__label">No. Sijil Akreditasi</label>
            <input type="text" name="sijilAkreditasi_nombor" class="field__input" value="{{ old('sijilAkreditasi_nombor', $p3->sijilAkreditasi_nombor) }}" maxlength="255" style="text-transform:uppercase;">
        </div>
        <div class="field">
            <label class="field__label">Nama Badan (Akreditasi)</label>
            <input type="text" name="sijilAkreditasi_namaBadan" class="field__input" value="{{ old('sijilAkreditasi_namaBadan', $p3->sijilAkreditasi_namaBadan) }}" maxlength="255" style="text-transform:uppercase;">
        </div>
        <div class="field">
            <label class="field__label">Pendaftaran e-Vendor</label>
            <div class="radio-row">
                <label><input type="radio" name="eVendor_daftar" value="Ya" {{ old('eVendor_daftar', $p3->eVendor_daftar) === 'Ya' ? 'checked' : '' }}> Ya</label>
                <label><input type="radio" name="eVendor_daftar" value="Tidak" {{ old('eVendor_daftar', $p3->eVendor_daftar) === 'Tidak' ? 'checked' : '' }}> Tidak</label>
            </div>
        </div>
        <div class="field">
            <label class="field__label">No. ID e-Vendor</label>
            <input type="text" name="eVendor_ID" class="field__input" value="{{ old('eVendor_ID', $p3->eVendor_ID) }}" maxlength="255" style="text-transform:uppercase;">
        </div>
    </div>

    <div class="pe-sec">3 · Maklumat Firma Guaman</div>
    <div class="pe-grid">
        <div class="field col-2">
            <label class="field__label">Nama Firma <span class="req">*</span></label>
            <input type="text" name="namaFirma" class="field__input" value="{{ old('namaFirma', $p4->namaFirma) }}" maxlength="255" required style="text-transform:uppercase;">
            @if ($hasDoc('profilFirma'))<div class="pe-doc">✓ Profil firma dimuat naik</div>@endif
        </div>
        <div class="field col-2">
            <label class="field__label">Ganti Profil Firma (PDF)</label>
            <input type="file" name="profilFirma" class="field__input" accept=".pdf">
        </div>
        <div class="field col-2">
            <label class="field__label">Alamat Firma</label>
            <input type="text" name="alamatFirma1" class="field__input" value="{{ old('alamatFirma1', $p4->alamatFirma1) }}" maxlength="255" placeholder="Baris 1" style="text-transform:uppercase; margin-bottom:8px;">
            <input type="text" name="alamatFirma2" class="field__input" value="{{ old('alamatFirma2', $p4->alamatFirma2) }}" maxlength="255" placeholder="Baris 2" style="text-transform:uppercase; margin-bottom:8px;">
            <input type="text" name="alamatFirma3" class="field__input" value="{{ old('alamatFirma3', $p4->alamatFirma3) }}" maxlength="255" placeholder="Baris 3" style="text-transform:uppercase;">
        </div>
        <div class="field">
            <label class="field__label">Poskod</label>
            <input type="text" name="poskodFirma" class="field__input" value="{{ old('poskodFirma', $p4->poskodFirma) }}" maxlength="10">
        </div>
        <div class="field">
            <label class="field__label">Bandar</label>
            <input type="text" name="bandarFirma" class="field__input" value="{{ old('bandarFirma', $p4->bandarFirma) }}" maxlength="255" style="text-transform:uppercase;">
        </div>
        <div class="field">
            <label class="field__label">Negeri</label>
            <select name="negeriFirma" class="field__input">
                <option value="">-- Sila Pilih --</option>
                @foreach ($negeriList as $negeri)
                    <option value="{{ $negeri }}" {{ old('negeriFirma', $p4->negeriFirma) === $negeri ? 'selected' : '' }}>{{ $negeri }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label class="field__label">No. Telefon</label>
            <input type="text" name="noTelFirma" class="field__input" value="{{ old('noTelFirma', $p4->noTelFirma) }}" maxlength="20">
        </div>
        <div class="field">
            <label class="field__label">No. Faksimili</label>
            <input type="text" name="noFaksFirma" class="field__input" value="{{ old('noFaksFirma', $p4->noFaksFirma) }}" maxlength="20">
        </div>
    </div>

    <div class="pe-sub">Insurans Tanggung Rugi (Professional Indemnity)</div>
    <div class="pe-grid">
        <div class="field">
            <label class="field__label">Nama Syarikat Insurans</label>
            <input type="text" name="namaInsurans" class="field__input" value="{{ old('namaInsurans', $p4->namaInsurans) }}" maxlength="255" style="text-transform:uppercase;">
        </div>
        <div class="field">
            <label class="field__label">No. Polisi</label>
            <input type="text" name="noPolisi" class="field__input" value="{{ old('noPolisi', $p4->noPolisi) }}" maxlength="255">
        </div>
        <div class="field">
            <label class="field__label">Amaun Perlindungan (RM)</label>
            <input type="text" name="amaunPerlindungan" class="field__input" value="{{ old('amaunPerlindungan', $p4->amaunPerlindungan) }}" maxlength="255">
        </div>
        <div class="field">
            <label class="field__label">Ganti Sijil Insurans (PDF)</label>
            <input type="file" name="insuransTR" class="field__input" accept=".pdf">
            @if ($hasDoc('insuransTR'))<div class="pe-doc">✓ Sijil dimuat naik</div>@endif
        </div>
        <div class="field">
            <label class="field__label">Tempoh Polisi - Mula</label>
            <input type="date" name="polisiMula" class="field__input" value="{{ old('polisiMula', $d($p4->polisiMula)) }}">
        </div>
        <div class="field">
            <label class="field__label">Tempoh Polisi - Hingga</label>
            <input type="date" name="polisiAkhir" class="field__input" value="{{ old('polisiAkhir', $d($p4->polisiAkhir)) }}">
        </div>
    </div>

    <div class="pe-sec">4 · Maklumat Akaun Pembayaran</div>
    <div class="pe-grid">
        <div class="field">
            <label class="field__label">Nama Bank <span class="req">*</span></label>
            <select name="namaBank" class="field__input" required>
                <option value="" disabled {{ old('namaBank', $p5->namaBank) ? '' : 'selected' }}>-- Sila Pilih --</option>
                @foreach ($banks as $bank)
                    <option value="{{ $bank }}" {{ old('namaBank', $p5->namaBank) === $bank ? 'selected' : '' }}>{{ $bank }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label class="field__label">Nombor Akaun <span class="req">*</span></label>
            <input type="text" name="noAkaunBank" class="field__input" value="{{ old('noAkaunBank', $p5->noAkaunBank) }}" maxlength="255" required>
        </div>
        <div class="field col-2">
            <label class="field__label">Ganti Penyata Bank (PDF)</label>
            <input type="file" name="penyataBank" class="field__input" accept=".pdf">
            @if ($hasDoc('penyataBank'))<div class="pe-doc">✓ Penyata dimuat naik</div>@endif
        </div>
        <div class="field col-2">
            <label class="field__label">Alamat Bank</label>
            <input type="text" name="alamatBank1" class="field__input" value="{{ old('alamatBank1', $p5->alamatBank1) }}" maxlength="255" placeholder="Baris 1" style="text-transform:uppercase; margin-bottom:8px;">
            <input type="text" name="alamatBank2" class="field__input" value="{{ old('alamatBank2', $p5->alamatBank2) }}" maxlength="255" placeholder="Baris 2" style="text-transform:uppercase; margin-bottom:8px;">
            <input type="text" name="alamatBank3" class="field__input" value="{{ old('alamatBank3', $p5->alamatBank3) }}" maxlength="255" placeholder="Baris 3" style="text-transform:uppercase;">
        </div>
        <div class="field">
            <label class="field__label">Poskod <span class="req">*</span></label>
            <input type="text" name="poskodBank" class="field__input" value="{{ old('poskodBank', $p5->poskodBank) }}" maxlength="10" required>
        </div>
        <div class="field">
            <label class="field__label">Bandar <span class="req">*</span></label>
            <input type="text" name="bandarBank" class="field__input" value="{{ old('bandarBank', $p5->bandarBank) }}" maxlength="255" required style="text-transform:uppercase;">
        </div>
        <div class="field col-2">
            <label class="field__label">Negeri <span class="req">*</span></label>
            <select name="negeriBank" class="field__input" required>
                <option value="" disabled {{ old('negeriBank', $p5->negeriBank) ? '' : 'selected' }}>-- Sila Pilih --</option>
                @foreach ($negeriList as $negeri)
                    <option value="{{ $negeri }}" {{ old('negeriBank', $p5->negeriBank) === $negeri ? 'selected' : '' }}>{{ $negeri }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="pe-sub">Dokumen Lain</div>
    <div class="pe-grid">
        <div class="field">
            <label class="field__label">Ganti Salinan Kad Pengenalan (PDF)</label>
            <input type="file" name="kadPengenalan" class="field__input" accept=".pdf">
            @if ($hasDoc('kadPengenalan'))<div class="pe-doc">✓ Dimuat naik</div>@endif
        </div>
        <div class="field">
            <label class="field__label">Ganti Senarai Kes (PDF)</label>
            <input type="file" name="senaraiKesKendali" class="field__input" accept=".pdf">
            @if ($hasDoc('senaraiKesKendali'))<div class="pe-doc">✓ Dimuat naik</div>@endif
        </div>
        @foreach ([1,2,3] as $i)
            <div class="field">
                <label class="field__label">Ganti Sijil Akademik {{ $i }} (PDF)</label>
                <input type="file" name="sijilAkademik{{ $i }}" class="field__input" accept=".pdf">
                @if ($hasDoc('sijilAkademik'.$i))<div class="pe-doc">✓ Dimuat naik</div>@endif
            </div>
        @endforeach
    </div>

    <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">
        <a href="{{ route('peguam.profil') }}" class="btn btn--ghost">Batal</a>
        <button type="submit" class="btn btn--primary">Simpan Kemaskini</button>
    </div>
</form>
@endsection
