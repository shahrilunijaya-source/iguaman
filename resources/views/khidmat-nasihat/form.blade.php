@extends('layouts.staff')

@section('title', $mode === 'create' ? 'Permohonan Khidmat Nasihat Baharu' : 'Kemaskini Draf')

@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('khidmat.store') : route('khidmat.update', $khidmat);
    $val = fn (string $f, $d = null) => old($f, $khidmat->$f ?? $d);

    // Field -> wizard step, so a server-side validation error reopens the right step.
    $steps = ['Maklumat', 'Bayaran', 'Slot Janji Temu', 'Perakuan'];
    $stepOf = [
        'jenis_permohonan' => 0, 'jenis_wakil' => 0, 'no_pengenalan_wakil' => 0, 'jawatan_wakil' => 0,
        'nama_diwakili' => 0, 'id_pengenalan_diwakili' => 0, 'jenis_mahkamah_pihak' => 0, 'id_mahkamah' => 0,
        'nama_mangsa' => 0, 'id_pengenalan_mangsa' => 0, 'jenis_pengenalan_mangsa' => 0, 'jantina_mangsa' => 0,
        'umur_mangsa' => 0, 'bangsa' => 0, 'agama' => 0, 'tarikh_lahir_mangsa' => 0, 'nama_wakil' => 0,
        'alamat_surat1' => 0, 'alamat_surat2' => 0, 'alamat_surat3' => 0, 'poskod' => 0,
        'cawangan_id' => 0, 'id_kategori' => 0, 'id_kategori_kes' => 0, 'id_subkategori' => 0, 'id_negeri' => 0, 'jenis_kes' => 0,
        'jumlah_pendapatan' => 1, 'is_percuma' => 1,
        'tarikh_temu_janji' => 2, 'masa_temu_janji' => 2,
        'perakuan' => 3,
    ];
    $errorStep = null;
    foreach (array_keys($errors->messages()) as $k) {
        if (isset($stepOf[$k])) { $errorStep = $stepOf[$k]; break; }
    }
@endphp

@section('content')
    <style>
        .wz-steps { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
        .wz-pill { display:flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid var(--line);
                   border-radius:999px; background:#fff; cursor:pointer; font-size:12px; color:var(--mute); transition:all .15s; }
        .wz-pill__no { width:20px; height:20px; border-radius:50%; background:var(--line); color:#fff;
                       display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; }
        .wz-pill.is-active { border-color:var(--teal); color:var(--pine-deep); box-shadow:0 0 0 3px rgba(26,111,168,0.12); }
        .wz-pill.is-active .wz-pill__no { background:var(--teal); }
        .wz-pill.is-done { color:var(--pine-deep); }
        .wz-pill.is-done .wz-pill__no { background:var(--pine-deep); }
        .wz-step { display:none; }
        .wz-step.is-active { display:block; }
        .wz-nav { display:flex; gap:10px; justify-content:space-between; align-items:center; margin-top:18px; }
        .wz-progress { font-size:12px; color:var(--mute); }
        .kn-fee { font-size:26px; font-weight:800; color:var(--pine-deep); }
        .kn-fee__path { font-size:12px; color:var(--mute); margin-top:4px; }
        .kn-slot-grid { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
        .kn-chip { padding:8px 14px; border:1px solid var(--line); border-radius:10px; background:#fff; cursor:pointer; font-size:13px; transition:all .12s; }
        .kn-chip.is-on { border-color:var(--teal); background:rgba(26,111,168,0.08); color:var(--pine-deep); font-weight:700; }
        .kn-hint { font-size:12px; color:var(--mute); margin-top:8px; }
    </style>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Permohonan Baharu' : 'Kemaskini Draf' }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar permohonan Khidmat Nasihat bagi pihak pemohon.' : ($khidmat->nama_mangsa.' · '.($khidmat->no_permohonan ?: 'Draf')) }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ $isCreate ? route('khidmat.index') : route('khidmat.show', $khidmat) }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            Sila betulkan {{ $errors->count() }} ralat di bawah.
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

    <form method="POST" action="{{ $action }}" id="knWiz" data-error-step="{{ $errorStep ?? '' }}"
          data-tarikh-url="{{ route('slot.tarikh') }}" data-masa-url="{{ route('slot.masa') }}"
          enctype="multipart/form-data" novalidate>
        @csrf
        @unless ($isCreate) @method('PUT') @endunless
        <input type="hidden" name="aksi" id="knAksi" value="draf">

        {{-- Slice 3: screening outcome carried from the saringan gate (read-only). --}}
        <input type="hidden" name="saringan_jenis" value="{{ $val('saringan_jenis') }}">
        <input type="hidden" name="saringan_lulus" value="{{ $val('saringan_lulus') ? 1 : 0 }}">
        <input type="hidden" name="is_laluan_sumbangan" id="knSumbangan" value="{{ $val('is_laluan_sumbangan') ? 1 : 0 }}">

        {{-- ===== Step 1 · Maklumat ===== --}}
        <div class="wz-step" data-step="0">
            {{-- Slice 3: jenis permohonan + Sebagai-Wakil context (penjara / JKM / mahkamah). --}}
            <div class="tap-card" style="margin-bottom:18px;">
                <div class="tap-card__eyebrow">Jenis Permohonan</div>
                <div class="wiz-grid">
                    <div class="wiz-field">
                        <label class="wiz-field__label">Permohonan Untuk *</label>
                        <select class="wiz-field__select" name="jenis_permohonan" id="knJenisPermohonan">
                            @foreach (['DIRI_SENDIRI' => 'Diri Sendiri', 'SEBAGAI_WAKIL' => 'Sebagai Wakil'] as $v => $label)
                                <option value="{{ $v }}" @selected($val('jenis_permohonan', 'DIRI_SENDIRI') === $v)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wiz-field" id="knWakilCtxField" style="display:none;">
                        <label class="wiz-field__label">Konteks Wakil *</label>
                        <select class="wiz-field__select" name="jenis_wakil" id="knJenisWakil">
                            <option value="">— Pilih —</option>
                            @foreach (['PENJARA' => 'Penjara', 'JKM' => 'JKM', 'MAHKAMAH' => 'Mahkamah'] as $v => $label)
                                <option value="{{ $v }}" @selected($val('jenis_wakil') === $v)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('jenis_wakil') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                        <div class="wiz-field__hint" id="knWakilFreeHint" style="display:none; color:var(--success);">Penjara / JKM — bayaran dikecualikan (RM0).</div>
                    </div>
                </div>
            </div>

            {{-- Wakil identity + represented party (only for SEBAGAI_WAKIL). --}}
            <div class="tap-card" style="margin-bottom:18px; display:none;" id="knWakilCard">
                <div class="tap-card__eyebrow">Maklumat Wakil &amp; Orang Yang Diwakili</div>
                <div class="wiz-grid">
                    <div class="wiz-field">
                        <label class="wiz-field__label">No. Pengenalan Wakil</label>
                        <input class="wiz-field__input" name="no_pengenalan_wakil" value="{{ $val('no_pengenalan_wakil') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jawatan Wakil</label>
                        <input class="wiz-field__input" name="jawatan_wakil" value="{{ $val('jawatan_wakil') }}" placeholder="Pegawai Penjara / Pegawai JKM">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Nama Orang Yang Diwakili</label>
                        <input class="wiz-field__input" name="nama_diwakili" value="{{ $val('nama_diwakili') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">No. Pengenalan Diwakili</label>
                        <input class="wiz-field__input" name="id_pengenalan_diwakili" value="{{ $val('id_pengenalan_diwakili') }}">
                    </div>
                </div>
            </div>

            {{-- Mahkamah (court) section — only for the MAHKAMAH wakil context. --}}
            <div class="tap-card" style="margin-bottom:18px; display:none;" id="knMahkamahCard">
                <div class="tap-card__eyebrow">Maklumat Mahkamah</div>
                <div class="wiz-grid">
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jenis Mahkamah *</label>
                        <select class="wiz-field__select" name="jenis_mahkamah_pihak" id="knJenisMahkamah">
                            <option value="">— Pilih —</option>
                            @foreach (['SIVIL' => 'Sivil', 'SYARIAH' => 'Syariah'] as $v => $label)
                                <option value="{{ $v }}" @selected($val('jenis_mahkamah_pihak') === $v)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('jenis_mahkamah_pihak') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Mahkamah *</label>
                        <select class="wiz-field__select" name="id_mahkamah" id="knMahkamah" data-selected="{{ $val('id_mahkamah') }}">
                            <option value="">— Pilih jenis mahkamah dahulu —</option>
                        </select>
                        @error('id_mahkamah') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="tap-card" style="margin-bottom:18px;">
                <div class="tap-card__eyebrow">Maklumat Mangsa / Pemohon</div>
                <div class="wiz-grid">
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Nama Mangsa *</label>
                        <input class="wiz-field__input" name="nama_mangsa" value="{{ $val('nama_mangsa') }}" required>
                        @error('nama_mangsa') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">No. Pengenalan</label>
                        <input class="wiz-field__input" name="id_pengenalan_mangsa" value="{{ $val('id_pengenalan_mangsa') }}">
                        @error('id_pengenalan_mangsa') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jenis Pengenalan</label>
                        <input class="wiz-field__input" name="jenis_pengenalan_mangsa" value="{{ $val('jenis_pengenalan_mangsa') }}" placeholder="KP / Pasport">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jantina</label>
                        <select class="wiz-field__select" name="jantina_mangsa">
                            <option value="">—</option>
                            @foreach (['Lelaki', 'Perempuan'] as $opt)
                                <option value="{{ $opt }}" @selected($val('jantina_mangsa') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Umur</label>
                        <input class="wiz-field__input" name="umur_mangsa" value="{{ $val('umur_mangsa') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Bangsa</label>
                        <input class="wiz-field__input" name="bangsa" value="{{ $val('bangsa') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Agama</label>
                        <input class="wiz-field__input" name="agama" value="{{ $val('agama') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Lahir</label>
                        <input type="date" class="wiz-field__input" name="tarikh_lahir_mangsa" value="{{ old('tarikh_lahir_mangsa', optional($khidmat->tarikh_lahir_mangsa)->format('Y-m-d')) }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Nama Wakil</label>
                        <input class="wiz-field__input" name="nama_wakil" value="{{ $val('nama_wakil') }}">
                    </div>
                </div>
            </div>

            <div class="tap-card" style="margin-bottom:18px;">
                <div class="tap-card__eyebrow">Alamat Surat-menyurat</div>
                <div class="wiz-grid">
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Alamat 1</label>
                        <input class="wiz-field__input" name="alamat_surat1" value="{{ $val('alamat_surat1') }}">
                    </div>
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Alamat 2</label>
                        <input class="wiz-field__input" name="alamat_surat2" value="{{ $val('alamat_surat2') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Alamat 3</label>
                        <input class="wiz-field__input" name="alamat_surat3" value="{{ $val('alamat_surat3') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Poskod</label>
                        <input class="wiz-field__input" name="poskod" value="{{ $val('poskod') }}" maxlength="10">
                    </div>
                </div>
            </div>

            <div class="tap-card">
                <div class="tap-card__eyebrow">Cawangan &amp; Kategori</div>
                <div class="wiz-grid">
                    <div class="wiz-field">
                        <label class="wiz-field__label">Cawangan (JBG) *</label>
                        <select class="wiz-field__select" name="cawangan_id" id="knCawangan" data-negeri-map='@json($cawanganList->pluck('negeri_id', 'id'))' required>
                            <option value="">— Pilih —</option>
                            @foreach ($cawanganList as $c)
                                <option value="{{ $c->id }}" @selected((string) $val('cawangan_id') === (string) $c->id)>{{ $c->nama }}</option>
                            @endforeach
                        </select>
                        @error('cawangan_id') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Negeri</label>
                        <select class="wiz-field__select" name="id_negeri" id="knNegeri">
                            <option value="">—</option>
                            @foreach ($negeriList as $id => $nama)
                                <option value="{{ $id }}" @selected((string) $val('id_negeri') === (string) $id)>{{ $nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Kategori (Jenis Khidmat) *</label>
                        <select class="wiz-field__select" name="id_kategori" id="knKategori" required>
                            <option value="">— Pilih —</option>
                            @foreach ($kategoriList as $k)
                                <option value="{{ $k->id }}" data-jenis="{{ $k->jenis_kategori }}" @selected((string) $val('id_kategori') === (string) $k->id)>{{ $k->jenis_kategori }}</option>
                            @endforeach
                        </select>
                        @error('id_kategori') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Kategori Kes</label>
                        <select class="wiz-field__select" name="id_kategori_kes" id="knKategoriKes" data-selected="{{ $val('id_kategori_kes') }}">
                            <option value="">—</option>
                        </select>
                        <div class="wiz-field__hint">Pilih kategori dahulu.</div>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Subkategori</label>
                        <select class="wiz-field__select" name="id_subkategori" id="knSubkategori" data-selected="{{ $val('id_subkategori') }}">
                            <option value="">—</option>
                        </select>
                        <div class="wiz-field__hint">Pilih kategori kes dahulu.</div>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jenis Kes (catatan)</label>
                        <input class="wiz-field__input" name="jenis_kes" value="{{ $val('jenis_kes') }}">
                    </div>
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Ulasan Permohonan</label>
                        <textarea class="wiz-field__textarea" name="ulasan_permohonan">{{ $val('ulasan_permohonan') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Step 2 · Bayaran ===== --}}
        <div class="wz-step" data-step="1">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Bayaran</div>
                <div class="wiz-grid">
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jumlah Pendapatan (RM)</label>
                        <input type="number" step="0.01" min="0" class="wiz-field__input" name="jumlah_pendapatan" id="knPendapatan" value="{{ $val('jumlah_pendapatan') }}">
                        <div class="wiz-field__hint">Pendapatan melebihi RM50,000 (Sivil/Syariah) → Laluan Sumbangan (RM260).</div>
                        @error('jumlah_pendapatan') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Pengecualian Bayaran</label>
                        <label style="display:flex; align-items:center; gap:8px; margin-top:8px; font-size:13px;">
                            <input type="checkbox" name="is_percuma" id="knPercuma" value="1" @checked($val('is_percuma'))>
                            Percuma (dikecualikan sepenuhnya)
                        </label>
                    </div>
                    {{-- W1: fee-waiver proof — only meaningful when Percuma is ticked. --}}
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Bukti Pengecualian Bayaran (jika percuma)</label>
                        <input type="file" class="wiz-field__input" name="lampiran_waiver" id="knWaiver"
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <div class="wiz-field__hint">Muat naik dokumen sokongan pengecualian (PDF/imej/Word, maks 25 MB).</div>
                        @if ($khidmat->lampiranWaiver)
                            <div class="wiz-field__hint">Sedia ada: {{ $khidmat->lampiranWaiver->nama }}</div>
                        @endif
                        @error('lampiran_waiver') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field wiz-field--span-2">
                        <div style="border:1px dashed var(--line); border-radius:var(--r-lg); padding:18px; background:rgba(26,111,168,0.04);">
                            <div class="tap-card__eyebrow" style="margin-bottom:8px;">Jumlah Bayaran Dikira</div>
                            <div class="kn-fee" id="knFee">RM 10.00</div>
                            <div class="kn-fee__path" id="knFeePath">Kadar asas</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Step 3 · Slot Janji Temu ===== --}}
        <div class="wz-step" data-step="2">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Slot Janji Temu</div>
                <div class="wiz-grid">
                    <div class="wiz-field wiz-field--span-2">
                        <div class="kn-hint" id="knSlotBranchHint">Pilih cawangan di Langkah 1 untuk memuatkan tarikh tersedia.</div>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Temu Janji</label>
                        <select class="wiz-field__select" name="tarikh_temu_janji" id="knTarikh" data-selected="{{ $val('tarikh_temu_janji') }}">
                            <option value="">— Pilih cawangan dahulu —</option>
                        </select>
                        @error('tarikh_temu_janji') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Masa Temu Janji</label>
                        <input type="hidden" name="masa_temu_janji" id="knMasa" value="{{ $val('masa_temu_janji') }}" data-preselect="{{ $val('masa_temu_janji') }}">
                        <div class="kn-slot-grid" id="knMasaGrid">
                            <span class="kn-hint">Pilih tarikh dahulu.</span>
                        </div>
                        @error('masa_temu_janji') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Step 4 · Perakuan ===== --}}
        <div class="wz-step" data-step="3">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Perakuan</div>
                <p style="font-size:13px; color:var(--mute); line-height:1.6; margin:0 0 14px;">
                    Saya mengesahkan bahawa segala maklumat yang diberikan adalah benar dan lengkap. Permohonan ini
                    akan ditetapkan ke status <strong>BAHARU</strong> setelah dihantar.
                </p>
                <label style="display:flex; align-items:center; gap:10px; font-size:14px;">
                    <input type="checkbox" name="perakuan" id="knPerakuan" value="1" @checked($val('perakuan'))>
                    Saya membuat perakuan ini.
                </label>
                @error('perakuan') <div class="wiz-field__hint" style="color:var(--danger); margin-top:8px;">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- ===== Wizard nav ===== --}}
        <div class="wz-nav">
            <button type="button" class="btn btn--ghost" id="wzPrev">← Sebelum</button>
            <span class="wz-progress">Langkah <span id="wzCur">1</span> / {{ count($steps) }}</span>
            <span style="display:flex; gap:10px;">
                <button type="submit" class="btn btn--ghost" id="wzDraf" data-aksi="draf">Simpan Draf</button>
                <button type="button" class="btn btn--primary" id="wzNext">Seterusnya →</button>
                <button type="submit" class="btn btn--primary" id="wzSubmit" data-aksi="hantar" style="display:none;">Hantar Permohonan</button>
            </span>
        </div>
    </form>

    <script>
        (function () {
            const form = document.getElementById('knWiz');
            const steps = Array.from(form.querySelectorAll('.wz-step'));
            const pills = Array.from(document.querySelectorAll('.wz-pill'));
            const prevBtn = document.getElementById('wzPrev');
            const nextBtn = document.getElementById('wzNext');
            const submitBtn = document.getElementById('wzSubmit');
            const drafBtn = document.getElementById('wzDraf');
            const curLabel = document.getElementById('wzCur');
            const aksi = document.getElementById('knAksi');
            const last = steps.length - 1;
            let cur = 0;

            function show(i) {
                cur = i;
                steps.forEach((s, k) => s.classList.toggle('is-active', k === i));
                pills.forEach((p, k) => {
                    p.classList.toggle('is-active', k === i);
                    p.classList.toggle('is-done', k < i);
                });
                prevBtn.style.visibility = i === 0 ? 'hidden' : 'visible';
                nextBtn.style.display = i === last ? 'none' : '';
                submitBtn.style.display = i === last ? '' : 'none';
                curLabel.textContent = i + 1;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            function firstInvalid(stepEl) {
                for (const el of stepEl.querySelectorAll('input, select, textarea')) {
                    if (el.offsetParent !== null && !el.checkValidity()) return el;
                }
                return null;
            }
            nextBtn.addEventListener('click', () => {
                const bad = firstInvalid(steps[cur]);
                if (bad) { bad.reportValidity(); return; }
                show(Math.min(cur + 1, last));
            });
            prevBtn.addEventListener('click', () => show(Math.max(cur - 1, 0)));
            pills.forEach((p) => p.addEventListener('click', () => {
                const t = +p.dataset.go;
                if (t <= cur) { show(t); return; }
                const bad = firstInvalid(steps[cur]);
                if (bad) bad.reportValidity(); else show(t);
            }));

            // Draf vs Hantar: set aksi, and for draf skip HTML5 required checks.
            drafBtn.addEventListener('click', () => { aksi.value = 'draf'; form.noValidate = true; });
            submitBtn.addEventListener('click', () => { aksi.value = 'hantar'; form.noValidate = false; });

            form.addEventListener('submit', (e) => {
                if (aksi.value === 'draf') return; // draft: server validates leniently
                for (let i = 0; i < steps.length; i++) {
                    const bad = firstInvalid(steps[i]);
                    if (bad) { e.preventDefault(); show(i); bad.reportValidity(); return; }
                }
                if (!document.getElementById('knMasa').value) {
                    e.preventDefault(); show(2);
                    alert('Sila pilih masa temu janji.');
                }
            });

            // ----- Live payment preview (mirrors App\Support\KhidmatBayaran) -----
            const feeEl = document.getElementById('knFee');
            const feePathEl = document.getElementById('knFeePath');
            const kategoriSel = document.getElementById('knKategori');
            const pendapatanEl = document.getElementById('knPendapatan');
            const percumaEl = document.getElementById('knPercuma');
            const jenisPermohonanEl = document.getElementById('knJenisPermohonan');
            const jenisWakilEl = document.getElementById('knJenisWakil');
            function fmt(n) { return 'RM ' + Number(n).toFixed(2); }
            function recalcFee() {
                const opt = kategoriSel.options[kategoriSel.selectedIndex];
                const jenis = (opt && opt.dataset.jenis ? opt.dataset.jenis : '').toUpperCase();
                const income = parseFloat(pendapatanEl.value) || 0;
                const isWakil = jenisPermohonanEl.value === 'SEBAGAI_WAKIL';
                const wakilCtx = isWakil ? jenisWakilEl.value : '';
                let fee = 10, path = 'Kadar asas';
                if (percumaEl.checked) { fee = 0; path = 'Percuma (pengecualian penuh)'; }
                else if (wakilCtx === 'PENJARA' || wakilCtx === 'JKM') { fee = 0; path = 'Wakil ' + wakilCtx + ' — tiada bayaran'; }
                else if (jenis === 'PENDAMPING JENAYAH' || jenis === 'PENDAMPING GUAMAN') { fee = 0; path = 'Pendamping — tiada bayaran'; }
                else if ((jenis === 'SIVIL' || jenis === 'SYARIAH') && income > 50000) { fee = 260; path = 'Laluan Sumbangan (pendapatan > RM50,000)'; }
                feeEl.textContent = fmt(fee);
                feePathEl.textContent = path;
            }
            [kategoriSel, pendapatanEl, percumaEl].forEach((el) => { el.addEventListener('input', recalcFee); el.addEventListener('change', recalcFee); });
            recalcFee();

            // ----- Sebagai-Wakil branching (penjara / JKM / mahkamah) -----
            const wakilCtxField = document.getElementById('knWakilCtxField');
            const wakilCard = document.getElementById('knWakilCard');
            const mahkamahCard = document.getElementById('knMahkamahCard');
            const wakilFreeHint = document.getElementById('knWakilFreeHint');
            const jenisMahkamahEl = document.getElementById('knJenisMahkamah');
            const mahkamahSel = document.getElementById('knMahkamah');
            const MAHKAMAH = { SIVIL: @json($mahkamahSivilList), SYARIAH: @json($mahkamahSyariahList) };

            function syncWakil() {
                const isWakil = jenisPermohonanEl.value === 'SEBAGAI_WAKIL';
                wakilCtxField.style.display = isWakil ? '' : 'none';
                wakilCard.style.display = isWakil ? '' : 'none';
                const ctx = isWakil ? jenisWakilEl.value : '';
                mahkamahCard.style.display = ctx === 'MAHKAMAH' ? '' : 'none';
                wakilFreeHint.style.display = (ctx === 'PENJARA' || ctx === 'JKM') ? '' : 'none';
                jenisWakilEl.required = isWakil;
                jenisMahkamahEl.required = ctx === 'MAHKAMAH';
                mahkamahSel.required = ctx === 'MAHKAMAH';
                recalcFee();
            }
            function loadMahkamah() {
                const rows = MAHKAMAH[jenisMahkamahEl.value] || [];
                mahkamahSel.innerHTML = '<option value="">' + (rows.length ? '— Pilih mahkamah —' : '— Pilih jenis mahkamah dahulu —') + '</option>';
                rows.forEach((r) => {
                    const o = document.createElement('option');
                    o.value = r.id; o.textContent = r.nama_mahkamah;
                    if (String(r.id) === String(mahkamahSel.dataset.selected)) o.selected = true;
                    mahkamahSel.appendChild(o);
                });
            }
            jenisPermohonanEl.addEventListener('change', syncWakil);
            jenisWakilEl.addEventListener('change', syncWakil);
            jenisMahkamahEl.addEventListener('change', loadMahkamah);
            if (jenisMahkamahEl.value) loadMahkamah();
            syncWakil();

            // ----- Cawangan -> auto-fill negeri -----
            const cawangan = document.getElementById('knCawangan');
            const negeri = document.getElementById('knNegeri');
            const negeriMap = JSON.parse(cawangan.dataset.negeriMap || '{}');
            cawangan.addEventListener('change', () => {
                const n = negeriMap[cawangan.value];
                if (n && !negeri.value) negeri.value = String(n);
                loadDates();
            });

            // ----- Category tree cascade (kategori -> kes -> subkategori) -----
            // Server-rendered tree (avoids depending on the gated selenggara CRUD endpoints).
            const TREE = @json($kategoriTree);
            const kategoriKes = document.getElementById('knKategoriKes');
            const subkategori = document.getElementById('knSubkategori');
            function fill(sel, rows, selected) {
                sel.innerHTML = '<option value="">—</option>';
                (rows || []).forEach((r) => {
                    const o = document.createElement('option');
                    o.value = r.id; o.textContent = r.nama;
                    if (String(r.id) === String(selected)) o.selected = true;
                    sel.appendChild(o);
                });
            }
            function loadKes() {
                const node = TREE[kategoriSel.value];
                fill(kategoriKes, node ? node.kes : [], kategoriKes.dataset.selected);
                loadSub();
            }
            function loadSub() {
                const node = TREE[kategoriSel.value];
                const kes = node ? (node.kes || []).find((k) => String(k.id) === String(kategoriKes.value)) : null;
                fill(subkategori, kes ? kes.sub : [], subkategori.dataset.selected);
            }
            kategoriSel.addEventListener('change', loadKes);
            kategoriKes.addEventListener('change', loadSub);
            if (kategoriSel.value) loadKes();

            // ----- Slot date/time via batch-10 JSON endpoints -----
            const tarikhSel = document.getElementById('knTarikh');
            const masaGrid = document.getElementById('knMasaGrid');
            const masaInput = document.getElementById('knMasa');
            const branchHint = document.getElementById('knSlotBranchHint');
            const tarikhUrl = form.dataset.tarikhUrl;
            const masaUrl = form.dataset.masaUrl;

            async function loadDates() {
                masaGrid.innerHTML = '<span class="kn-hint">Pilih tarikh dahulu.</span>';
                masaInput.value = '';
                if (!cawangan.value) {
                    tarikhSel.innerHTML = '<option value="">— Pilih cawangan dahulu —</option>';
                    branchHint.textContent = 'Pilih cawangan di Langkah 1 untuk memuatkan tarikh tersedia.';
                    return;
                }
                tarikhSel.innerHTML = '<option value="">Memuatkan…</option>';
                branchHint.textContent = 'Memuatkan tarikh tersedia…';
                try {
                    const res = await fetch(tarikhUrl + '?cawangan_id=' + encodeURIComponent(cawangan.value), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    const dates = data.dates || [];
                    tarikhSel.innerHTML = '<option value="">— Pilih tarikh —</option>';
                    dates.forEach((d) => {
                        const o = document.createElement('option');
                        o.value = d; o.textContent = d;
                        if (d === tarikhSel.dataset.selected) o.selected = true;
                        tarikhSel.appendChild(o);
                    });
                    branchHint.textContent = dates.length ? (dates.length + ' tarikh tersedia.') : 'Tiada tarikh tersedia untuk cawangan ini.';
                    if (tarikhSel.value) loadTimes();
                } catch (e) {
                    tarikhSel.innerHTML = '<option value="">Ralat memuatkan tarikh</option>';
                }
            }
            async function loadTimes() {
                masaGrid.innerHTML = '';
                masaInput.value = '';
                if (!tarikhSel.value || !cawangan.value) { masaGrid.innerHTML = '<span class="kn-hint">Pilih tarikh dahulu.</span>'; return; }
                try {
                    const res = await fetch(masaUrl + '?cawangan_id=' + encodeURIComponent(cawangan.value) + '&tarikh=' + encodeURIComponent(tarikhSel.value), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    const times = data.times || [];
                    if (!times.length) { masaGrid.innerHTML = '<span class="kn-hint">Tiada masa tersedia.</span>'; return; }
                    times.forEach((t) => {
                        const chip = document.createElement('span');
                        chip.className = 'kn-chip'; chip.textContent = t; chip.dataset.masa = t;
                        if (t === masaInput.dataset.preselect) { chip.classList.add('is-on'); masaInput.value = t; }
                        chip.addEventListener('click', () => {
                            masaGrid.querySelectorAll('.kn-chip').forEach((c) => c.classList.remove('is-on'));
                            chip.classList.add('is-on'); masaInput.value = t;
                        });
                        masaGrid.appendChild(chip);
                    });
                } catch (e) { masaGrid.innerHTML = '<span class="kn-hint">Ralat memuatkan masa.</span>'; }
            }
            tarikhSel.addEventListener('change', loadTimes);
            if (cawangan.value) loadDates();

            // Reopen the step that carries a server-side validation error.
            const errStep = form.dataset.errorStep;
            show(errStep !== '' && errStep != null ? +errStep : 0);
        })();
    </script>
@endsection
