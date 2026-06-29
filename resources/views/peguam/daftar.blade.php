<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Peguam Panel · iGuaman 2in1</title>
    @vite(['resources/css/system.css'])
    <style>
        .daftar-wrap { min-height: 100vh; display: flex; justify-content: center; padding: 48px 20px; }
        .daftar-card { width: 100%; max-width: 760px; }
        .daftar-head { margin-bottom: 26px; }
        .daftar-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 18px; }
        .daftar-grid .col-2 { grid-column: 1 / -1; }
        .daftar-sec { margin: 26px 0 10px; padding-bottom: 8px; border-bottom: 1px solid var(--line); }
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
            <div class="swap__screen">
                <div class="eyebrow" style="margin-bottom: 6px;">Akses Luaran</div>
                <h1 class="vb-h1">Daftar sebagai Peguam Panel.<span class="dot"></span></h1>
                <p class="vb-sub">Lengkapkan borang di bawah untuk memohon menyertai panel Bantuan Guaman. Medan bertanda * wajib diisi.</p>

                @if ($errors->any())
                    <div class="formerr" style="margin-top: 14px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('peguam.daftar.store') }}" class="va-form" style="margin-top: 18px;">
                    @csrf

                    {{-- Honeypot: hidden from humans, visible to bots. --}}
                    <div style="position: absolute; left: -9999px;" aria-hidden="true">
                        <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <div class="daftar-sec"><div class="tap-card__eyebrow">Maklumat Peguam</div></div>
                    <div class="daftar-grid">
                        <div class="field col-2">
                            <label class="field__label">Nama Penuh *</label>
                            <input type="text" name="namaPeguam" class="field__input" value="{{ old('namaPeguam') }}" maxlength="255" required autofocus>
                        </div>
                        <div class="field">
                            <label class="field__label">No. KP Baru *</label>
                            <input type="text" name="kpBaru" class="field__input" value="{{ old('kpBaru') }}" placeholder="900101015555" maxlength="20" required>
                        </div>
                        <div class="field">
                            <label class="field__label">No. KP Lama</label>
                            <input type="text" name="kpLama" class="field__input" value="{{ old('kpLama') }}" maxlength="20">
                        </div>
                        <div class="field">
                            <label class="field__label">Jantina *</label>
                            <select name="jantina" class="field__input" required>
                                <option value="" disabled {{ old('jantina') ? '' : 'selected' }}>Pilih…</option>
                                <option value="Lelaki" {{ old('jantina') === 'Lelaki' ? 'selected' : '' }}>Lelaki</option>
                                <option value="Perempuan" {{ old('jantina') === 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label">No. Telefon Bimbit *</label>
                            <input type="text" name="noTelBimbit" class="field__input" value="{{ old('noTelBimbit') }}" placeholder="0123456789" maxlength="20" required>
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Emel *</label>
                            <input type="email" name="emelPeguam" class="field__input" value="{{ old('emelPeguam') }}" placeholder="nama@firma.com" maxlength="255" required>
                        </div>
                    </div>

                    <div class="daftar-sec"><div class="tap-card__eyebrow">Kelulusan &amp; Pengalaman</div></div>
                    <div class="daftar-grid">
                        <div class="field col-2">
                            <label class="field__label">Kelulusan Akademik *</label>
                            <input type="text" name="kelulusanAkademik" class="field__input" value="{{ old('kelulusanAkademik') }}" placeholder="cth. LLB (Hons), UM" maxlength="500" required>
                        </div>
                        <div class="field">
                            <label class="field__label">Tarikh Diterima Masuk (Sivil)</label>
                            <input type="date" name="tarikhDiterimaMasuk" class="field__input" value="{{ old('tarikhDiterimaMasuk') }}">
                        </div>
                        <div class="field">
                            <label class="field__label">Tarikh Diterima Masuk (Syarie)</label>
                            <input type="date" name="tarikhDiterimaMasukSyarie" class="field__input" value="{{ old('tarikhDiterimaMasukSyarie') }}">
                        </div>
                        <div class="field">
                            <label class="field__label">Tahun Pengalaman (Sivil) *</label>
                            <input type="number" name="tahunPengalaman" class="field__input" value="{{ old('tahunPengalaman') }}" min="0" max="99" required>
                        </div>
                        <div class="field">
                            <label class="field__label">Tahun Pengalaman (Syarie)</label>
                            <input type="number" name="tahunPengalamanSyarie" class="field__input" value="{{ old('tahunPengalamanSyarie') }}" min="0" max="99">
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Bilangan Kes Dikendalikan *</label>
                            <input type="number" name="bilanganKes" class="field__input" value="{{ old('bilanganKes') }}" min="0" max="99999" required>
                        </div>
                        <div class="field col-2">
                            <label class="field__label">Keterangan Kes *</label>
                            <textarea name="keteranganKes" class="field__input" rows="4" maxlength="2000" required placeholder="Ringkasan pengalaman / jenis kes yang pernah dikendalikan.">{{ old('keteranganKes') }}</textarea>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 24px;">
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
