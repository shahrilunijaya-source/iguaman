<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Peguam Panel · Sistem Integrated Bantuan Guaman</title>
    @vite(['resources/css/system.css'])
    <style>
        /* ---- Page chrome (consistent with split-screen login) ---- */
        .daftar-topbar {
            position: sticky; top: 0; z-index: 30;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            padding: 14px 24px; background: var(--paper-elevated);
            border-bottom: 1px solid var(--line);
        }
        .daftar-topbar .wm { font-size: 18px; font-weight: 700; letter-spacing: -0.01em; color: var(--pine); }
        .daftar-topbar .wm .i { color: var(--teal); }
        .daftar-topbar__meta { font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--mute); border-left: 1px solid var(--line); padding-left: 12px; }
        .daftar-topbar__left { display: flex; align-items: center; gap: 12px; }
        .daftar-topbar__back { font-size: 12px; color: var(--mute); text-decoration: none; }
        .daftar-topbar__back:hover { color: var(--pine); }

        .daftar-wrap { display: flex; justify-content: center; padding: 28px 20px 0; }
        .daftar-card { width: 100%; max-width: 820px; padding-bottom: 40px; }
        .daftar-head { margin-bottom: 18px; }

        /* ---- Intro / "what you'll need" panel (echoes login pine accent) ---- */
        .daftar-intro {
            background: var(--pine-deep); color: #fff;
            border-radius: var(--r-lg); padding: 18px 22px; margin: 18px 0 22px;
            position: relative; overflow: hidden;
        }
        .daftar-intro::before {
            content: ''; position: absolute; inset: 0; pointer-events: none;
            background: radial-gradient(600px 300px at 100% 0%, rgba(var(--brand-rgb),0.18), transparent 60%);
        }
        .daftar-intro > * { position: relative; }
        .daftar-intro__eyebrow { font-size: 10px; letter-spacing: .16em; text-transform: uppercase; color: var(--teal); font-weight: 700; margin-bottom: 6px; }
        .daftar-intro__h { font-size: 15px; font-weight: 600; margin: 0 0 10px; line-height: 1.5; }
        .daftar-intro__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 22px; }
        .daftar-intro__item { display: flex; gap: 8px; align-items: flex-start; font-size: 12px; line-height: 1.5; color: rgba(255,255,255,0.78); }
        .daftar-intro__item svg { flex-shrink: 0; margin-top: 2px; color: var(--teal); }
        .daftar-intro__note { margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.12); font-size: 11.5px; color: rgba(255,255,255,0.6); }

        /* ---- Step pills (mirrors awam/permohonan wizard) ---- */
        .wz-steps { display: flex; gap: 8px; margin-bottom: 22px; flex-wrap: wrap; }
        .wz-pill { display: flex; align-items: center; gap: 8px; padding: 8px 14px; border: 1px solid var(--line);
                   border-radius: 999px; background: #fff; cursor: pointer; font-size: 12px; font-weight: 500; color: var(--mute); transition: all .15s; }
        .wz-pill:hover { border-color: var(--line-2); }
        .wz-pill__no { width: 20px; height: 20px; border-radius: 50%; background: var(--line-2); color: #fff;
                       display: grid; place-items: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
        .wz-pill.is-active { border-color: var(--teal); color: var(--pine-deep); box-shadow: 0 0 0 3px var(--teal-soft); }
        .wz-pill.is-active .wz-pill__no { background: var(--teal); }
        .wz-pill.is-done { color: var(--pine-deep); }
        .wz-pill.is-done .wz-pill__no { background: var(--pine-deep); }

        .wz-step { display: none; animation: fadeUp 280ms cubic-bezier(.2,.7,.2,1); }
        .wz-step.is-active { display: block; }

        .tap-card { margin-bottom: 16px; }
        .daftar-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 18px; }
        .daftar-grid .col-2 { grid-column: 1 / -1; }
        .daftar-subsec { margin: 4px 0 8px; font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--mute); }
        .req { color: var(--danger, #dc2626); }
        .bidang-group { border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; }
        .bidang-group__title { font-weight: 600; font-size: 13px; margin-bottom: 8px; }
        .bidang-opt { display: flex; gap: 8px; align-items: flex-start; padding: 4px 0; font-size: 13px; }
        .cso-block { border: 1px dashed var(--line); border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; }
        .cso-block.is-collapsed { display: none; }
        .cso-add {
            display: inline-flex; align-items: center; gap: 8px; margin-bottom: 4px;
            padding: 9px 14px; border: 1px dashed var(--line-2); border-radius: 10px;
            background: var(--paper-2); color: var(--pine); font-size: 12.5px; font-weight: 600; cursor: pointer;
            transition: border-color .15s, background .15s;
        }
        .cso-add:hover { border-color: var(--teal); color: var(--teal-deep); }
        .cso-add svg { width: 14px; height: 14px; }
        .radio-row { display: flex; gap: 18px; align-items: center; }
        .radio-row label { display: flex; gap: 6px; align-items: center; font-size: 13px; }
        .file-input { padding: 9px 14px !important; height: auto !important; }

        /* ---- Wizard nav ---- */
        .wz-nav {
            position: sticky; bottom: 0; margin-top: 8px;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 14px 0; background: linear-gradient(180deg, rgba(250,250,247,0), var(--paper) 38%);
        }
        .wz-progress { font-size: 12px; color: var(--mute); font-variant-numeric: tabular-nums; }
        .wz-progress strong { color: var(--pine); font-weight: 700; }
        .wz-nav__right { display: flex; gap: 10px; }

        @media (max-width: 640px) {
            .daftar-grid, .daftar-intro__grid { grid-template-columns: 1fr; }
            .daftar-topbar__meta { display: none; }
            .wz-pill span:not(.wz-pill__no) { display: none; }
        }
    </style>
</head>
<body class="system">

<div class="daftar-topbar">
    <div class="daftar-topbar__left">
        <div class="wm"><span class="i">i</span>Guaman<span class="dot"></span></div>
        <span class="daftar-topbar__meta">INTEGRATED · PERMOHONAN PEGUAM PANEL</span>
    </div>
    <a href="{{ route('system.login') }}" class="daftar-topbar__back">← Log masuk</a>
</div>

<div class="daftar-wrap">
    <div class="daftar-card">

        @if (session('daftar_selesai'))
            {{-- ============ SUCCESS STATE ============ --}}
            <div class="swap__screen" style="padding-top: 40px;">
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
            {{-- ============ APPLICATION WIZARD ============ --}}
            @php
                $banks = ['Maybank','CIMB Bank','Public Bank','RHB Bank','Hong Leong Bank','AmBank','Bank Islam','Bank Rakyat','BSN','Affin Bank','Alliance Bank','OCBC Bank','HSBC','UOB','Standard Chartered','Agrobank','MBSB Bank'];

                $steps = ['Butiran Peguam', 'Pengalaman & Bidang', 'Kelayakan Profesion', 'Firma & Bayaran', 'Dokumen'];

                // field name → step index (for jump-to-error on validation failure)
                $stepOf = [
                    'namaPeguam' => 0, 'kpBaru' => 0, 'kpLama' => 0, 'jantina' => 0, 'noTelBimbit' => 0,
                    'emelPeguam' => 0, 'kelulusanAkademik' => 0, 'tarikhDiterimaMasuk' => 0, 'tahunPengalaman' => 0,
                    'tarikhDiterimaMasukSyarie' => 0, 'tahunPengalamanSyarie' => 0, 'bilanganKes' => 0,

                    'keteranganKes' => 1, 'senaraiKesKendali' => 1, 'selected_kes' => 1,

                    'clpNumber' => 2, 'clp' => 2, 'clpMula' => 2, 'clpAkhir' => 2,
                    'ybgk_kelulusan' => 2, 'ybgk_tarikhLulus_A' => 2, 'ybgk_tarikhLulus_B' => 2, 'ybgk_daftar' => 2, 'certkelulusanYBGK' => 2,
                    'adr_penimbangtara' => 2, 'adr_pengantara' => 2, 'certpenimbangtara' => 2, 'certpengantara' => 2,
                    'sijilAhli_nombor' => 2, 'sijilAhli_namaBadan' => 2, 'sijilAhli_mula' => 2, 'sijilAhli_akhir' => 2,
                    'sijilAkreditasi_nombor' => 2, 'sijilAkreditasi_namaBadan' => 2, 'sijilAkreditasi_mula' => 2, 'sijilAkreditasi_akhir' => 2,
                    'eVendor_daftar' => 2, 'eVendor_ID' => 2, 'sijilEvendor' => 2,

                    'namaFirma' => 3, 'profilFirma' => 3, 'alamatFirma1' => 3, 'alamatFirma2' => 3, 'alamatFirma3' => 3,
                    'poskodFirma' => 3, 'bandarFirma' => 3, 'negeriFirma' => 3, 'noTelFirma' => 3, 'noFaksFirma' => 3,
                    'namaInsurans' => 3, 'noPolisi' => 3, 'amaunPerlindungan' => 3, 'insuransTR' => 3, 'polisiMula' => 3, 'polisiAkhir' => 3,
                    'namaBank' => 3, 'noAkaunBank' => 3, 'penyataBank' => 3, 'alamatBank1' => 3, 'alamatBank2' => 3, 'alamatBank3' => 3,
                    'poskodBank' => 3, 'bandarBank' => 3, 'negeriBank' => 3,

                    'kadPengenalan' => 4, 'sijilAkademik1' => 4, 'sijilAkademik2' => 4, 'sijilAkademik3' => 4,
                ];
                foreach (range(1, 5) as $i) {
                    $stepOf['csoNumber'.$i] = 2; $stepOf['cso'.$i.'Tauliah'] = 2;
                    $stepOf['cso'.$i.'Mula'] = 2; $stepOf['cso'.$i.'Akhir'] = 2; $stepOf['cso'.$i] = 2;
                }
                $errorStep = null;
                foreach (array_keys($errors->messages()) as $k) {
                    $base = explode('.', $k)[0];
                    if (isset($stepOf[$base])) { $errorStep = $stepOf[$base]; break; }
                }
            @endphp

            <div class="swap__screen daftar-head">
                <div class="eyebrow" style="margin-bottom: 6px;">Akses Luaran</div>
                <h1 class="vb-h1" style="font-size: 26px;">Daftar sebagai Peguam Panel.<span class="dot"></span></h1>
                <p class="vb-sub" style="margin-bottom: 0;">Lengkapkan borang langkah demi langkah untuk memohon menyertai panel Bantuan Guaman.</p>
            </div>

            {{-- ===== Intro: what you'll need ===== --}}
            <div class="daftar-intro">
                <div class="daftar-intro__eyebrow">Sebelum anda mula</div>
                <h2 class="daftar-intro__h">Sediakan dokumen berikut dalam format PDF (maks 5MB setiap satu).</h2>
                <div class="daftar-intro__grid">
                    <div class="daftar-intro__item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>Salinan Kad Pengenalan <strong style="color:#fff;">(wajib)</strong></span>
                    </div>
                    <div class="daftar-intro__item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>Senarai kes yang pernah dikendalikan <strong style="color:#fff;">(wajib)</strong></span>
                    </div>
                    <div class="daftar-intro__item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>Sijil CLP / YBGK / Peguam Syarie (jika ada)</span>
                    </div>
                    <div class="daftar-intro__item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>Insurans firma &amp; penyata bank (jika ada)</span>
                    </div>
                </div>
                <p class="daftar-intro__note">Medan bertanda <span class="req" style="color: var(--orange);">*</span> wajib diisi. Anda boleh bergerak antara langkah bila-bila masa.</p>
            </div>

            @if ($errors->any())
                <div class="formerr" style="margin-bottom: 16px; flex-direction: column; align-items: flex-start; gap: 4px;">
                    <strong>{{ $errors->count() }} ralat perlu dibetulkan:</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            {{-- ===== Stepper ===== --}}
            <div class="wz-steps">
                @foreach ($steps as $i => $label)
                    <div class="wz-pill" data-go="{{ $i }}">
                        <span class="wz-pill__no">{{ $i + 1 }}</span>
                        <span>{{ $label }}</span>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('peguam.daftar.store') }}" class="va-form" enctype="multipart/form-data"
                  id="daftarWiz" data-error-step="{{ $errorStep ?? '' }}" novalidate>
                @csrf

                {{-- Honeypot: hidden from humans, visible to bots. --}}
                <div style="position: absolute; left: -9999px;" aria-hidden="true">
                    <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                {{-- ============ STEP 1 — BUTIRAN PEGUAM ============ --}}
                <div class="wz-step" data-step="0">
                    <div class="tap-card">
                        <div class="tap-card__eyebrow">1 · Butiran Peguam Panel</div>
                        <div class="daftar-grid">
                            <div class="field col-2">
                                <label class="field__label">Nama Peguam (seperti Kad Pengenalan) <span class="req">*</span></label>
                                <input type="text" name="namaPeguam" class="field__input" value="{{ old('namaPeguam') }}" maxlength="255" required style="text-transform:uppercase;">
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
                                <textarea name="kelulusanAkademik" class="field__input" rows="2" maxlength="500" required style="text-transform:uppercase; height:auto; padding:10px 14px;" placeholder="cth. LLB (Hons), Universiti Malaya">{{ old('kelulusanAkademik') }}</textarea>
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
                    </div>
                </div>

                {{-- ============ STEP 2 — PENGENDALIAN KES & BIDANG ============ --}}
                <div class="wz-step" data-step="1">
                    <div class="tap-card">
                        <div class="tap-card__eyebrow">2 · Pengendalian Kes</div>
                        <div class="daftar-grid">
                            <div class="field col-2">
                                <label class="field__label">Rumusan / Keterangan Ringkas Jenis &amp; Bilangan Kes Yang Telah Dikendalikan <span class="req">*</span></label>
                                <textarea name="keteranganKes" class="field__input" rows="4" maxlength="2000" required style="height:auto; padding:10px 14px;" placeholder="cth. CIVIL, CRIMINAL, ACCIDENT, CONVEYANCING…">{{ old('keteranganKes') }}</textarea>
                            </div>
                            <div class="field col-2">
                                <label class="field__label">Senarai Kes Yang Pernah Dikendalikan (PDF) <span class="req">*</span></label>
                                <input type="file" name="senaraiKesKendali" class="field__input file-input" accept=".pdf" required>
                            </div>
                        </div>
                    </div>

                    <div class="tap-card">
                        <div class="tap-card__eyebrow">Bidang Pilihan Pengkhususan Amalan Guaman <span class="req">*</span></div>
                        <p class="field__hint" style="margin: -2px 0 12px;">Pilih sekurang-kurangnya satu bidang amalan yang anda kendalikan.</p>
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
                    </div>
                </div>

                {{-- ============ STEP 3 — KELAYAKAN PROFESION ============ --}}
                <div class="wz-step" data-step="2">
                    <div class="tap-card">
                        <div class="tap-card__eyebrow">3 · Sijil Amalan Guaman (CLP)</div>
                        <div class="daftar-grid">
                            <div class="field">
                                <label class="field__label">No. Sijil Amalan Guaman <span class="req">*</span></label>
                                <input type="text" name="clpNumber" class="field__input" value="{{ old('clpNumber') }}" maxlength="255" required style="text-transform:uppercase;">
                            </div>
                            <div class="field">
                                <label class="field__label">Sijil Amalan Guaman (PDF)</label>
                                <input type="file" name="clp" class="field__input file-input" accept=".pdf">
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
                    </div>

                    <div class="tap-card">
                        <div class="tap-card__eyebrow">Kelulusan Latihan YBGK</div>
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
                                <input type="file" name="certkelulusanYBGK" class="field__input file-input" accept=".pdf">
                            </div>
                        </div>
                    </div>

                    <div class="tap-card">
                        <div class="tap-card__eyebrow">Sijil Peguam Syarie (CSO 1–5)</div>
                        @foreach (range(1, 5) as $i)
                            @php
                                $csoHasOld = old('csoNumber'.$i) || old('cso'.$i.'Tauliah') || old('cso'.$i.'Mula') || old('cso'.$i.'Akhir');
                            @endphp
                            <div class="cso-block {{ $i > 1 && ! $csoHasOld ? 'is-collapsed' : '' }}" data-cso-block="{{ $i }}">
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
                                        <input type="file" name="cso{{ $i }}" class="field__input file-input" accept=".pdf">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <button type="button" class="cso-add" id="csoAdd">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                            Tambah sijil peguam syarie
                        </button>
                    </div>

                    <div class="tap-card">
                        <div class="tap-card__eyebrow">Penyelesaian Pertikaian Alternatif (ADR)</div>
                        <div class="daftar-grid">
                            <div class="field">
                                <label class="field__label">Penimbang Tara</label>
                                <div class="radio-row">
                                    <label><input type="radio" name="adr_penimbangtara" value="Ya" {{ old('adr_penimbangtara') === 'Ya' ? 'checked' : '' }}> Ya</label>
                                    <label><input type="radio" name="adr_penimbangtara" value="Tidak" {{ old('adr_penimbangtara') === 'Tidak' ? 'checked' : '' }}> Tidak</label>
                                </div>
                                <input type="file" name="certpenimbangtara" class="field__input file-input" accept=".pdf" style="margin-top:8px;">
                                <div class="field__hint">Sijil Penimbang Tara (PDF)</div>
                            </div>
                            <div class="field">
                                <label class="field__label">Pengantara</label>
                                <div class="radio-row">
                                    <label><input type="radio" name="adr_pengantara" value="Ya" {{ old('adr_pengantara') === 'Ya' ? 'checked' : '' }}> Ya</label>
                                    <label><input type="radio" name="adr_pengantara" value="Tidak" {{ old('adr_pengantara') === 'Tidak' ? 'checked' : '' }}> Tidak</label>
                                </div>
                                <input type="file" name="certpengantara" class="field__input file-input" accept=".pdf" style="margin-top:8px;">
                                <div class="field__hint">Sijil Pengantara (PDF)</div>
                            </div>
                        </div>
                    </div>

                    <div class="tap-card">
                        <div class="tap-card__eyebrow">Sijil Ahli &amp; Akreditasi · eVendor</div>
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
                                <input type="file" name="sijilEvendor" class="field__input file-input" accept=".pdf">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ STEP 4 — FIRMA & BAYARAN ============ --}}
                <div class="wz-step" data-step="3">
                    <div class="tap-card">
                        <div class="tap-card__eyebrow">4 · Maklumat Firma Guaman</div>
                        <div class="daftar-grid">
                            <div class="field col-2">
                                <label class="field__label">Nama Firma <span class="req">*</span></label>
                                <input type="text" name="namaFirma" class="field__input" value="{{ old('namaFirma') }}" maxlength="255" required style="text-transform:uppercase;">
                            </div>
                            <div class="field col-2">
                                <label class="field__label">Profil Firma / Rakan Kongsi (PDF)</label>
                                <input type="file" name="profilFirma" class="field__input file-input" accept=".pdf">
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
                    </div>

                    <div class="tap-card">
                        <div class="tap-card__eyebrow">Insurans Tanggung Rugi Firma (Professional Indemnity)</div>
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
                                <input type="file" name="insuransTR" class="field__input file-input" accept=".pdf">
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
                    </div>

                    <div class="tap-card">
                        <div class="tap-card__eyebrow">5 · Maklumat Akaun Pembayaran</div>
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
                                <input type="file" name="penyataBank" class="field__input file-input" accept=".pdf">
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
                    </div>
                </div>

                {{-- ============ STEP 5 — DOKUMEN ============ --}}
                <div class="wz-step" data-step="4">
                    <div class="tap-card">
                        <div class="tap-card__eyebrow">6 · Senarai Semak Dokumen</div>
                        <div class="daftar-grid">
                            <div class="field">
                                <label class="field__label">Salinan Kad Pengenalan (PDF) <span class="req">*</span></label>
                                <input type="file" name="kadPengenalan" class="field__input file-input" accept=".pdf" required>
                            </div>
                            <div class="field">
                                <label class="field__label">Sijil Akademik 1 (PDF)</label>
                                <input type="file" name="sijilAkademik1" class="field__input file-input" accept=".pdf">
                            </div>
                            <div class="field">
                                <label class="field__label">Sijil Akademik 2 (PDF)</label>
                                <input type="file" name="sijilAkademik2" class="field__input file-input" accept=".pdf">
                            </div>
                            <div class="field">
                                <label class="field__label">Sijil Akademik 3 (PDF)</label>
                                <input type="file" name="sijilAkademik3" class="field__input file-input" accept=".pdf">
                            </div>
                        </div>
                        <p class="field__hint" style="margin-top: 14px;">Semak semula maklumat anda sebelum menghantar. Permohonan akan dihantar untuk sokongan Pengarah &amp; keputusan Koordinator/Pentadbir.</p>
                    </div>
                </div>

                {{-- ===== Wizard nav ===== --}}
                <div class="wz-nav">
                    <button type="button" class="btn btn--ghost" id="wzPrev" style="visibility:hidden;">← Sebelum</button>
                    <span class="wz-progress">Langkah <strong id="wzCur">1</strong> / {{ count($steps) }}</span>
                    <span class="wz-nav__right">
                        <button type="button" class="btn btn--primary" id="wzNext">Seterusnya →</button>
                        <button type="submit" class="btn btn--primary" id="wzSubmit" style="display:none;">
                            Hantar Permohonan
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </button>
                    </span>
                </div>
            </form>
        @endif

    </div>
</div>

@unless (session('daftar_selesai'))
<script>
    (function () {
        var form = document.getElementById('daftarWiz');
        if (!form) return;

        var steps = Array.prototype.slice.call(form.querySelectorAll('.wz-step'));
        var pills = Array.prototype.slice.call(document.querySelectorAll('.wz-pill'));
        var prevBtn = document.getElementById('wzPrev');
        var nextBtn = document.getElementById('wzNext');
        var submitBtn = document.getElementById('wzSubmit');
        var curLabel = document.getElementById('wzCur');
        var last = steps.length - 1;
        var BIDANG_STEP = 1;
        var cur = 0;

        function show(i) {
            cur = i;
            steps.forEach(function (s, k) { s.classList.toggle('is-active', k === i); });
            pills.forEach(function (p, k) {
                p.classList.toggle('is-active', k === i);
                p.classList.toggle('is-done', k < i);
            });
            prevBtn.style.visibility = i === 0 ? 'hidden' : 'visible';
            nextBtn.style.display = i === last ? 'none' : '';
            submitBtn.style.display = i === last ? '' : 'none';
            curLabel.textContent = i + 1;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // First VISIBLE required field in a step that fails native validity
        // (used for per-step Next gating — only the active step is visible).
        function firstInvalid(stepEl) {
            var els = stepEl.querySelectorAll('input, select, textarea');
            for (var j = 0; j < els.length; j++) {
                if (els[j].offsetParent !== null && !els[j].checkValidity()) return els[j];
            }
            return null;
        }

        // First invalid required field REGARDLESS of visibility (used for the
        // final pre-submit sweep across all steps). checkValidity() still
        // evaluates display:none fields, so this catches a required field left
        // blank on any step before the form ever POSTs.
        function firstInvalidDeep(stepEl) {
            var els = stepEl.querySelectorAll('input, select, textarea');
            for (var j = 0; j < els.length; j++) {
                if (!els[j].checkValidity()) return els[j];
            }
            return null;
        }

        // At least one Bidang Pengkhususan checkbox must be ticked.
        function bidangMissing() {
            return form.querySelectorAll('input[name="selected_kes[]"]:checked').length === 0;
        }

        function validateStep(i) {
            var bad = firstInvalid(steps[i]);
            if (bad) { bad.reportValidity(); return false; }
            if (i === BIDANG_STEP && bidangMissing()) {
                alert('Sila pilih sekurang-kurangnya satu Bidang Pilihan Pengkhususan Amalan Guaman.');
                return false;
            }
            return true;
        }

        nextBtn.addEventListener('click', function () {
            if (!validateStep(cur)) return;
            show(Math.min(cur + 1, last));
        });
        prevBtn.addEventListener('click', function () { show(Math.max(cur - 1, 0)); });

        // Pills: jump back freely; jump forward only if the current step is valid.
        pills.forEach(function (p) {
            p.addEventListener('click', function () {
                var t = +p.dataset.go;
                if (t <= cur) { show(t); return; }
                if (validateStep(cur)) show(t);
            });
        });

        // Final pre-submit sweep: confirm EVERY required field across ALL steps
        // is filled before the form POSTs. Cuts server bounces — important here
        // because a server-side validation failure clears all file inputs and
        // forces the applicant to re-attach every PDF.
        form.addEventListener('submit', function (e) {
            for (var i = 0; i < steps.length; i++) {
                var bad = firstInvalidDeep(steps[i]);
                if (bad) {
                    e.preventDefault();
                    show(i);                 // reveal the step so the field is focusable
                    bad.reportValidity();    // native bubble + scroll to the field
                    return;
                }
            }
            if (bidangMissing()) {
                e.preventDefault(); show(BIDANG_STEP);
                alert('Sila pilih sekurang-kurangnya satu Bidang Pilihan Pengkhususan Amalan Guaman.');
            }
        });

        // Progressive disclosure for Sijil Peguam Syarie 2–5.
        var addBtn = document.getElementById('csoAdd');
        if (addBtn) {
            var nextHidden = function () { return document.querySelector('.cso-block.is-collapsed'); };
            var syncAdd = function () { if (!nextHidden()) addBtn.style.display = 'none'; };
            addBtn.addEventListener('click', function () {
                var blk = nextHidden();
                if (blk) { blk.classList.remove('is-collapsed'); var f = blk.querySelector('input, select'); if (f) f.focus(); }
                syncAdd();
            });
            syncAdd();
        }

        // On server validation failure, open the step holding the first error.
        var errStep = form.dataset.errorStep;
        show(errStep !== '' && errStep != null ? +errStep : 0);
    })();
</script>
@endunless

</body>
</html>
