<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Peguam Panel · iGuaman 2in1</title>
    @vite(['resources/css/system.css'])
    <style>
        .daftar-wrap { min-height: 100vh; display: flex; justify-content: center; padding: 48px 20px; }
        .daftar-card { width: 100%; max-width: 880px; }
        .daftar-head { margin-bottom: 26px; }
        .daftar-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 18px; }
        .daftar-grid .col-2 { grid-column: 1 / -1; }
        .daftar-sec { margin: 30px 0 12px; padding-bottom: 8px; border-bottom: 1px solid var(--line); }
        .daftar-subsec { margin: 18px 0 8px; font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--mute); }
        .field__hint { font-size: 11px; color: var(--mute); margin-top: 4px; }
        .req { color: var(--danger, #dc2626); }
        .bidang-group { border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; }
        .bidang-group__title { font-weight: 600; font-size: 13px; margin-bottom: 8px; }
        .bidang-opt { display: flex; gap: 8px; align-items: flex-start; padding: 3px 0; font-size: 13px; }
        .cso-block { border: 1px dashed var(--line); border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; }
        .radio-row { display: flex; gap: 18px; align-items: center; }
        .radio-row label { display: flex; gap: 6px; align-items: center; font-size: 13px; }
        @media (max-width: 640px) { .daftar-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="system">

<div class="daftar-wrap">
    <div class="daftar-card">

        <div class="daftar-head">
            <div class="wm" style="font-size: 22px;"><span class="i">i</span>Guaman<span class="dot"></span></div>
            <div class="meta" style="margin-top: 6px;">2IN1 · PERMOHONAN PEGUAM PANEL</div>
        </div>

        @if (session('daftar_selesai'))
            {{-- ============ SUCCESS STATE ============ --}}
            <div class="swap__screen">
                <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); align-items: flex-start; flex-direction: column; gap: 8px;">
                    <strong>Permohonan diterima.</strong>
                    <span>No. rujukan permohonan anda: <strong>#{{ session('daftar_ref') }}</strong></span>
                </div>
                <p class="vb-sub" style="margin-top: 14px;">
                    Permohonan anda untuk menyertai panel peguam telah direkodkan dan menunggu sokongan Pengarah serta keputusan Koordinator/Pentadbir. Anda akan dihubungi melalui emel yang diberikan.
                </p>
                <div style="margin-top: 22px; display: flex; gap: 12px;">
                    <a href="{{ route('peguam.daftar') }}" class="btn btn--ghost">Hantar permohonan lain</a>
                    <a href="{{ route('system.login') }}" class="btn btn--primary">Ke log masuk</a>
                </div>
            </div>
        @else
            {{-- ============ APPLICATION FORM ============ --}}
            @php
                $banks = ['Maybank','CIMB Bank','Public Bank','RHB Bank','Hong Leong Bank','AmBank','Bank Islam','Bank Rakyat','BSN','Affin Bank','Alliance Bank','OCBC Bank','HSBC','UOB','Standard Chartered','Agrobank','MBSB Bank'];
            @endphp
            <div class="swap__screen">
                <div class="eyebrow" style="margin-bottom: 6px;">Akses Luaran</div>
                <h1 class="vb-h1">Daftar sebagai Peguam Panel.<span class="dot"></span></h1>
                <p class="vb-sub">Lengkapkan borang di bawah untuk memohon menyertai panel Bantuan Guaman. Medan bertanda <span class="req">*</span> wajib diisi. Semua muat naik dokumen mesti dalam format <strong>PDF</strong> (maks 5MB).</p>

                @if ($errors->any())
                    <div class="formerr" style="margin-top: 14px; flex-direction: column; align-items: flex-start; gap: 4px;">
                        <strong>{{ $errors->count() }} ralat perlu dibetulkan:</strong>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('peguam.daftar.store') }}" class="va-form" style="margin-top: 18px;" enctype="multipart/form-data">
                    @csrf

                    {{-- Honeypot: hidden from humans, visible to bots. --}}
                    <div style="position: absolute; left: -9999px;" aria-hidden="true">
                        <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    {{-- ===== SECTION 1: BUTIRAN PEGUAM ===== --}}
                    <div class="daftar-sec"><div class="tap-card__eyebrow">1 · Butiran Peguam Panel</div></div>
                    <div class="daftar-grid">
                        <div class="field col-2">
                            <label class="field__label">Nama Peguam (seperti Kad Pengenalan) <span class="req">*</span></label>
                            <input type="text" name="namaPeguam" class="field__input" value="{{ old('namaPeguam') }}" maxlength="255" required autofocus style="text-transform:uppercase;">
                        </div>
                        <div class="field">
                            <label class="field__label">No. Kad Pengenalan (Baru) <span class="req">*</span></label>
                            <input type="text" name="kpBaru" class="field__input" value="{{ old('kpBaru') }}" placeholder="900101015555" maxlength="20" required>
                        </div>
                        <div class="field">
                            <label class="field__label">No. Kad Pengenalan (Lama)</label>
                            <input type="text" name="kpLama" class="field__input" value="{{ old('kpLama') }}" maxlength="20">
                        </div>
                        <div class="field">
                            <label class="field__label">Jantina <span class="req">*</span></label>
                            <select name="jantina" class="field__input" required>
                                <option value="" disabled {{ old('jantina') ? '' : 'selected' }}>Pilih…</option>
                                <option value="Lelaki" {{ old('jantina') === 'Lelaki' ? 'selected' : '' }}>Lelaki</option>
                                <option value="Perempuan" {{ old('jantina') === 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label">No. Telefon (Bimbit) <span class="req">*</span></label>
                            <input type="text" name="noTelBimbit" class="field__input" value="{{ old('noTelBimbit') }}" placeholder="0123456789" maxlength="20" required>
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Emel <span class="req">*</span></label>
                            <input type="email" name="emelPeguam" class="field__input" value="{{ old('emelPeguam') }}" placeholder="nama@firma.com" maxlength="255" required>
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Kelulusan Akademik <span class="req">*</span></label>
                            <textarea name="kelulusanAkademik" class="field__input" rows="2" maxlength="500" required style="text-transform:uppercase;" placeholder="cth. LLB (Hons), Universiti Malaya">{{ old('kelulusanAkademik') }}</textarea>
                        </div>
                        <div class="field">
                            <label class="field__label">Tarikh Diterima Masuk — Peguambela/Peguamcara <span class="req">*</span></label>
                            <input type="date" name="tarikhDiterimaMasuk" class="field__input" value="{{ old('tarikhDiterimaMasuk') }}" required>
                        </div>
                        <div class="field">
                            <label class="field__label">Tahun Pengalaman (Sivil) <span class="req">*</span></label>
                            <input type="number" name="tahunPengalaman" class="field__input" value="{{ old('tahunPengalaman') }}" min="0" max="99" required>
                        </div>
                        <div class="field">
                            <label class="field__label">Tarikh Diterima Masuk — Peguam Syarie</label>
                            <input type="date" name="tarikhDiterimaMasukSyarie" class="field__input" value="{{ old('tarikhDiterimaMasukSyarie') }}">
                        </div>
                        <div class="field">
                            <label class="field__label">Tahun Pengalaman (Syarie)</label>
                            <input type="number" name="tahunPengalamanSyarie" class="field__input" value="{{ old('tahunPengalamanSyarie') }}" min="0" max="99">
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Bilangan Kes Yang Telah Dikendalikan <span class="req">*</span></label>
                            <input type="number" name="bilanganKes" class="field__input" value="{{ old('bilanganKes') }}" min="0" max="99999" required>
                        </div>
                    </div>

                    {{-- ===== SECTION 2: PENGENDALIAN KES ===== --}}
                    <div class="daftar-sec"><div class="tap-card__eyebrow">2 · Pengendalian Kes</div></div>
                    <div class="daftar-grid">
                        <div class="field col-2">
                            <label class="field__label">Rumusan / Keterangan Ringkas Jenis &amp; Bilangan Kes Yang Telah Dikendalikan <span class="req">*</span></label>
                            <textarea name="keteranganKes" class="field__input" rows="4" maxlength="2000" required placeholder="cth. CIVIL, CRIMINAL, ACCIDENT, CONVEYANCING…">{{ old('keteranganKes') }}</textarea>
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Senarai Kes Yang Pernah Dikendalikan (PDF) <span class="req">*</span></label>
                            <input type="file" name="senaraiKesKendali" class="field__input" accept=".pdf" required>
                        </div>
                    </div>

                    {{-- Bidang Pengkhususan checkboxes (→ butiran_peguam_panel_6) --}}
                    <div class="daftar-subsec">Bidang Pilihan Pengkhususan Amalan Guaman <span class="req">*</span></div>
                    @foreach ($kategoriMap as $code => $label)
                        @if (($bidang[$code] ?? collect())->isNotEmpty())
                            <div class="bidang-group">
                                <div class="bidang-group__title">{{ $label }}</div>
                                @foreach ($bidang[$code] as $row)
                                    @php $val = $label.'::'.$row->deskripsi; @endphp
                                    <label class="bidang-opt">
                                        <input type="checkbox" name="selected_kes[]" value="{{ $val }}"
                                            {{ collect(old('selected_kes', []))->contains($val) ? 'checked' : '' }}>
                                        <span>{{ $row->deskripsi }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    @endforeach

                    {{-- ===== SECTION 3: KELAYAKAN PROFESION ===== --}}
                    <div class="daftar-sec"><div class="tap-card__eyebrow">3 · Butiran Kelayakan Profesion</div></div>

                    <div class="daftar-subsec">Sijil Amalan Guaman (CLP)</div>
                    <div class="daftar-grid">
                        <div class="field">
                            <label class="field__label">No. Sijil Amalan Guaman <span class="req">*</span></label>
                            <input type="text" name="clpNumber" class="field__input" value="{{ old('clpNumber') }}" maxlength="255" required style="text-transform:uppercase;">
                        </div>
                        <div class="field">
                            <label class="field__label">Sijil Amalan Guaman (PDF)</label>
                            <input type="file" name="clp" class="field__input" accept=".pdf">
                        </div>
                        <div class="field">
                            <label class="field__label">Tempoh Sah — Mula <span class="req">*</span></label>
                            <input type="date" name="clpMula" class="field__input" value="{{ old('clpMula') }}" required>
                        </div>
                        <div class="field">
                            <label class="field__label">Tempoh Sah — Hingga <span class="req">*</span></label>
                            <input type="date" name="clpAkhir" class="field__input" value="{{ old('clpAkhir') }}" required>
                        </div>
                    </div>

                    <div class="daftar-subsec">Kelulusan Latihan YBGK</div>
                    <div class="daftar-grid">
                        <div class="field col-2">
                            <div class="radio-row">
                                @foreach (['Ya','Tidak','Pengecualian'] as $opt)
                                    <label><input type="radio" name="ybgk_kelulusan" value="{{ $opt }}" {{ old('ybgk_kelulusan') === $opt ? 'checked' : '' }}> {{ $opt }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="field">
                            <label class="field__label">Tarikh Lulus (A)</label>
                            <input type="date" name="ybgk_tarikhLulus_A" class="field__input" value="{{ old('ybgk_tarikhLulus_A') }}">
                        </div>
                        <div class="field">
                            <label class="field__label">Tarikh Lulus (B)</label>
                            <input type="date" name="ybgk_tarikhLulus_B" class="field__input" value="{{ old('ybgk_tarikhLulus_B') }}">
                        </div>
                        <div class="field">
                            <label class="field__label">No. Daftar YBGK</label>
                            <input type="text" name="ybgk_daftar" class="field__input" value="{{ old('ybgk_daftar') }}" maxlength="255">
                        </div>
                        <div class="field">
                            <label class="field__label">Sijil YBGK (PDF)</label>
                            <input type="file" name="certkelulusanYBGK" class="field__input" accept=".pdf">
                        </div>
                    </div>

                    <div class="daftar-subsec">Sijil Peguam Syarie (CSO 1–5)</div>
                    @foreach (range(1, 5) as $i)
                        <div class="cso-block">
                            <div class="bidang-group__title">Sijil {{ $i }}</div>
                            <div class="daftar-grid">
                                <div class="field">
                                    <label class="field__label">No. Sijil Peguam Syarie ({{ $i }})</label>
                                    <input type="text" name="csoNumber{{ $i }}" class="field__input" value="{{ old('csoNumber'.$i) }}" maxlength="255" style="text-transform:uppercase;">
                                </div>
                                <div class="field">
                                    <label class="field__label">Negeri Tauliah</label>
                                    <select name="cso{{ $i }}Tauliah" class="field__input">
                                        <option value="">-- Sila Pilih --</option>
                                        @foreach ($negeriList as $negeri)
                                            <option value="{{ $negeri }}" {{ old('cso'.$i.'Tauliah') === $negeri ? 'selected' : '' }}>{{ $negeri }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="field__label">Tempoh Sah — Mula</label>
                                    <input type="date" name="cso{{ $i }}Mula" class="field__input" value="{{ old('cso'.$i.'Mula') }}">
                                </div>
                                <div class="field">
                                    <label class="field__label">Tempoh Sah — Hingga</label>
                                    <input type="date" name="cso{{ $i }}Akhir" class="field__input" value="{{ old('cso'.$i.'Akhir') }}">
                                </div>
                                <div class="field col-2">
                                    <label class="field__label">Sijil Guaman Syariah {{ $i }} (PDF)</label>
                                    <input type="file" name="cso{{ $i }}" class="field__input" accept=".pdf">
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="daftar-subsec">Penyelesaian Pertikaian Alternatif (ADR)</div>
                    <div class="daftar-grid">
                        <div class="field">
                            <label class="field__label">Penimbang Tara</label>
                            <div class="radio-row">
                                <label><input type="radio" name="adr_penimbangtara" value="Ya" {{ old('adr_penimbangtara') === 'Ya' ? 'checked' : '' }}> Ya</label>
                                <label><input type="radio" name="adr_penimbangtara" value="Tidak" {{ old('adr_penimbangtara') === 'Tidak' ? 'checked' : '' }}> Tidak</label>
                            </div>
                            <input type="file" name="certpenimbangtara" class="field__input" accept=".pdf" style="margin-top:8px;">
                            <div class="field__hint">Sijil Penimbang Tara (PDF)</div>
                        </div>
                        <div class="field">
                            <label class="field__label">Pengantara</label>
                            <div class="radio-row">
                                <label><input type="radio" name="adr_pengantara" value="Ya" {{ old('adr_pengantara') === 'Ya' ? 'checked' : '' }}> Ya</label>
                                <label><input type="radio" name="adr_pengantara" value="Tidak" {{ old('adr_pengantara') === 'Tidak' ? 'checked' : '' }}> Tidak</label>
                            </div>
                            <input type="file" name="certpengantara" class="field__input" accept=".pdf" style="margin-top:8px;">
                            <div class="field__hint">Sijil Pengantara (PDF)</div>
                        </div>
                    </div>

                    <div class="daftar-subsec">Sijil Ahli &amp; Akreditasi · eVendor</div>
                    <div class="daftar-grid">
                        <div class="field">
                            <label class="field__label">No. Sijil Ahli</label>
                            <input type="text" name="sijilAhli_nombor" class="field__input" value="{{ old('sijilAhli_nombor') }}" maxlength="255" style="text-transform:uppercase;">
                        </div>
                        <div class="field">
                            <label class="field__label">Nama Badan (Sijil Ahli)</label>
                            <input type="text" name="sijilAhli_namaBadan" class="field__input" value="{{ old('sijilAhli_namaBadan') }}" maxlength="255" style="text-transform:uppercase;">
                        </div>
                        <div class="field">
                            <label class="field__label">Sijil Ahli — Mula</label>
                            <input type="date" name="sijilAhli_mula" class="field__input" value="{{ old('sijilAhli_mula') }}">
                        </div>
                        <div class="field">
                            <label class="field__label">Sijil Ahli — Hingga</label>
                            <input type="date" name="sijilAhli_akhir" class="field__input" value="{{ old('sijilAhli_akhir') }}">
                        </div>
                        <div class="field">
                            <label class="field__label">No. Sijil Akreditasi</label>
                            <input type="text" name="sijilAkreditasi_nombor" class="field__input" value="{{ old('sijilAkreditasi_nombor') }}" maxlength="255" style="text-transform:uppercase;">
                        </div>
                        <div class="field">
                            <label class="field__label">Nama Badan (Akreditasi)</label>
                            <input type="text" name="sijilAkreditasi_namaBadan" class="field__input" value="{{ old('sijilAkreditasi_namaBadan') }}" maxlength="255" style="text-transform:uppercase;">
                        </div>
                        <div class="field">
                            <label class="field__label">Akreditasi — Mula</label>
                            <input type="date" name="sijilAkreditasi_mula" class="field__input" value="{{ old('sijilAkreditasi_mula') }}">
                        </div>
                        <div class="field">
                            <label class="field__label">Akreditasi — Hingga</label>
                            <input type="date" name="sijilAkreditasi_akhir" class="field__input" value="{{ old('sijilAkreditasi_akhir') }}">
                        </div>
                        <div class="field">
                            <label class="field__label">Pendaftaran e-Vendor</label>
                            <div class="radio-row">
                                <label><input type="radio" name="eVendor_daftar" value="Ya" {{ old('eVendor_daftar') === 'Ya' ? 'checked' : '' }}> Ya</label>
                                <label><input type="radio" name="eVendor_daftar" value="Tidak" {{ old('eVendor_daftar') === 'Tidak' ? 'checked' : '' }}> Tidak</label>
                            </div>
                        </div>
                        <div class="field">
                            <label class="field__label">No. ID e-Vendor</label>
                            <input type="text" name="eVendor_ID" class="field__input" value="{{ old('eVendor_ID') }}" maxlength="255" style="text-transform:uppercase;">
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Sijil e-Vendor (PDF)</label>
                            <input type="file" name="sijilEvendor" class="field__input" accept=".pdf">
                        </div>
                    </div>

                    {{-- ===== SECTION 5: FIRMA GUAMAN ===== --}}
                    <div class="daftar-sec"><div class="tap-card__eyebrow">4 · Maklumat Firma Guaman</div></div>
                    <div class="daftar-grid">
                        <div class="field col-2">
                            <label class="field__label">Nama Firma <span class="req">*</span></label>
                            <input type="text" name="namaFirma" class="field__input" value="{{ old('namaFirma') }}" maxlength="255" required style="text-transform:uppercase;">
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Profil Firma / Rakan Kongsi (PDF)</label>
                            <input type="file" name="profilFirma" class="field__input" accept=".pdf">
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Alamat Firma</label>
                            <input type="text" name="alamatFirma1" class="field__input" value="{{ old('alamatFirma1') }}" maxlength="255" placeholder="Alamat baris 1" style="text-transform:uppercase; margin-bottom:8px;">
                            <input type="text" name="alamatFirma2" class="field__input" value="{{ old('alamatFirma2') }}" maxlength="255" placeholder="Alamat baris 2" style="text-transform:uppercase; margin-bottom:8px;">
                            <input type="text" name="alamatFirma3" class="field__input" value="{{ old('alamatFirma3') }}" maxlength="255" placeholder="Alamat baris 3" style="text-transform:uppercase;">
                        </div>
                        <div class="field">
                            <label class="field__label">Poskod</label>
                            <input type="text" name="poskodFirma" class="field__input" value="{{ old('poskodFirma') }}" maxlength="10">
                        </div>
                        <div class="field">
                            <label class="field__label">Bandar</label>
                            <input type="text" name="bandarFirma" class="field__input" value="{{ old('bandarFirma') }}" maxlength="255" style="text-transform:uppercase;">
                        </div>
                        <div class="field">
                            <label class="field__label">Negeri</label>
                            <select name="negeriFirma" class="field__input">
                                <option value="">-- Sila Pilih --</option>
                                @foreach ($negeriList as $negeri)
                                    <option value="{{ $negeri }}" {{ old('negeriFirma') === $negeri ? 'selected' : '' }}>{{ $negeri }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label">No. Telefon</label>
                            <input type="text" name="noTelFirma" class="field__input" value="{{ old('noTelFirma') }}" maxlength="20">
                        </div>
                        <div class="field">
                            <label class="field__label">No. Faksimili</label>
                            <input type="text" name="noFaksFirma" class="field__input" value="{{ old('noFaksFirma') }}" maxlength="20">
                        </div>
                    </div>

                    <div class="daftar-subsec">Insurans Tanggung Rugi Firma (Professional Indemnity)</div>
                    <div class="daftar-grid">
                        <div class="field">
                            <label class="field__label">Nama Syarikat Insurans</label>
                            <input type="text" name="namaInsurans" class="field__input" value="{{ old('namaInsurans') }}" maxlength="255" style="text-transform:uppercase;">
                        </div>
                        <div class="field">
                            <label class="field__label">No. Polisi</label>
                            <input type="text" name="noPolisi" class="field__input" value="{{ old('noPolisi') }}" maxlength="255">
                        </div>
                        <div class="field">
                            <label class="field__label">Amaun Perlindungan (RM)</label>
                            <input type="text" name="amaunPerlindungan" class="field__input" value="{{ old('amaunPerlindungan') }}" maxlength="255">
                        </div>
                        <div class="field">
                            <label class="field__label">Sijil Insurans (PDF)</label>
                            <input type="file" name="insuransTR" class="field__input" accept=".pdf">
                        </div>
                        <div class="field">
                            <label class="field__label">Tempoh Sah Polisi — Mula</label>
                            <input type="date" name="polisiMula" class="field__input" value="{{ old('polisiMula') }}">
                        </div>
                        <div class="field">
                            <label class="field__label">Tempoh Sah Polisi — Hingga</label>
                            <input type="date" name="polisiAkhir" class="field__input" value="{{ old('polisiAkhir') }}">
                        </div>
                    </div>

                    {{-- ===== SECTION 6: AKAUN PEMBAYARAN ===== --}}
                    <div class="daftar-sec"><div class="tap-card__eyebrow">5 · Maklumat Akaun Pembayaran</div></div>
                    <div class="daftar-grid">
                        <div class="field">
                            <label class="field__label">Nama Bank <span class="req">*</span></label>
                            <select name="namaBank" class="field__input" required>
                                <option value="" disabled {{ old('namaBank') ? '' : 'selected' }}>-- Sila Pilih --</option>
                                @foreach ($banks as $bank)
                                    <option value="{{ $bank }}" {{ old('namaBank') === $bank ? 'selected' : '' }}>{{ $bank }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label">Nombor Akaun <span class="req">*</span></label>
                            <input type="text" name="noAkaunBank" class="field__input" value="{{ old('noAkaunBank') }}" maxlength="255" required>
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Maklumat / Penyata Bank (PDF)</label>
                            <input type="file" name="penyataBank" class="field__input" accept=".pdf">
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Alamat Bank</label>
                            <input type="text" name="alamatBank1" class="field__input" value="{{ old('alamatBank1') }}" maxlength="255" placeholder="Alamat baris 1" style="text-transform:uppercase; margin-bottom:8px;">
                            <input type="text" name="alamatBank2" class="field__input" value="{{ old('alamatBank2') }}" maxlength="255" placeholder="Alamat baris 2" style="text-transform:uppercase; margin-bottom:8px;">
                            <input type="text" name="alamatBank3" class="field__input" value="{{ old('alamatBank3') }}" maxlength="255" placeholder="Alamat baris 3" style="text-transform:uppercase;">
                        </div>
                        <div class="field">
                            <label class="field__label">Poskod <span class="req">*</span></label>
                            <input type="text" name="poskodBank" class="field__input" value="{{ old('poskodBank') }}" maxlength="10" required>
                        </div>
                        <div class="field">
                            <label class="field__label">Bandar <span class="req">*</span></label>
                            <input type="text" name="bandarBank" class="field__input" value="{{ old('bandarBank') }}" maxlength="255" required style="text-transform:uppercase;">
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Negeri <span class="req">*</span></label>
                            <select name="negeriBank" class="field__input" required>
                                <option value="" disabled {{ old('negeriBank') ? '' : 'selected' }}>-- Sila Pilih --</option>
                                @foreach ($negeriList as $negeri)
                                    <option value="{{ $negeri }}" {{ old('negeriBank') === $negeri ? 'selected' : '' }}>{{ $negeri }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- ===== SECTION 7: SENARAI SEMAK (core docs) ===== --}}
                    <div class="daftar-sec"><div class="tap-card__eyebrow">6 · Senarai Semak Dokumen</div></div>
                    <div class="daftar-grid">
                        <div class="field">
                            <label class="field__label">Salinan Kad Pengenalan (PDF) <span class="req">*</span></label>
                            <input type="file" name="kadPengenalan" class="field__input" accept=".pdf" required>
                        </div>
                        <div class="field">
                            <label class="field__label">Sijil Akademik 1 (PDF)</label>
                            <input type="file" name="sijilAkademik1" class="field__input" accept=".pdf">
                        </div>
                        <div class="field">
                            <label class="field__label">Sijil Akademik 2 (PDF)</label>
                            <input type="file" name="sijilAkademik2" class="field__input" accept=".pdf">
                        </div>
                        <div class="field">
                            <label class="field__label">Sijil Akademik 3 (PDF)</label>
                            <input type="file" name="sijilAkademik3" class="field__input" accept=".pdf">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 28px;">
                        <a href="{{ route('system.login') }}" style="color: var(--mute); text-decoration: none; font-size: 12px;">← Kembali ke log masuk</a>
                        <button type="submit" class="btn btn--primary">
                            Hantar Permohonan
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        @endif

    </div>
</div>

</body>
</html>
