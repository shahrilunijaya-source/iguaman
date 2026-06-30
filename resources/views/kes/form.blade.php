@extends('layouts.staff')

@section('title', $mode === 'create' ? 'Permohonan Baharu' : 'Kemaskini Kes')

@php
    $val = function (string $field, ?string $fmt = null) use ($kes) {
        if ($fmt === 'date') {
            return old($field, optional($kes->$field)->format('Y-m-d'));
        }
        return old($field, $kes->$field);
    };
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('kes.store') : route('kes.update', $kes);

    // Peringkat 1–5 intake wizard. Each field maps to a step so a server-side
    // validation error can reopen the right peringkat on reload.
    $steps = ['Pemohon', 'Permohonan', 'Pendaftaran', 'Keputusan', 'Penutupan'];
    $stepOf = [
        'nama' => 0, 'nokp' => 0, 'umur' => 0, 'jantina' => 0, 'agama' => 0, 'bangsa' => 0, 'etnik' => 0, 'oku' => 0, 'nama_penjaga' => 0, 'nokp_penjaga' => 0,
        'cawangan' => 1, 'tarikh_khidmat_nasihat' => 1, 'tarikh_permohonan' => 1, 'kategori_kes' => 1, 'jenis_kes' => 1, 'jenis_kategori' => 1, 'jenis_jenayah' => 1, 'taraf' => 1,
        'no_fail' => 2, 'no_sistem' => 2, 'nama_pegawai' => 2, 'tarikh_daftar' => 2,
        'keputusan' => 3, 'diterima' => 3, 'kelulusan' => 3, 'keputusan_menteri' => 3, 'tarikh_perakuan' => 3, 'tarikh_pemakluman' => 3, 'sumbangan' => 3, 'nilai_sumbangan' => 3,
        'status' => 4, 'tarikh_selesai' => 4, 'sebab_selesai' => 4, 'tarikh_tutup_fail' => 4, 'sebab_tutup_fail' => 4,
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
    </style>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Permohonan Baharu' : 'Kemaskini Kes' }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar permohonan bantuan guaman baharu.' : ($kes->nama.' · '.($kes->no_fail ?: '#'.$kes->id)) }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ $isCreate ? route('kes.index') : route('kes.show', $kes) }}" class="tap-head__btn">Batal</a>
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

    <form method="POST" action="{{ $action }}" id="wizForm" data-error-step="{{ $errorStep ?? '' }}" novalidate>
        @csrf
        @unless ($isCreate) @method('PUT') @endunless

        {{-- ===== Peringkat 1 · Pemohon ===== --}}
        <div class="wz-step" data-step="0">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Peringkat 1 · Maklumat Pemohon</div>
                <div class="wiz-grid">
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Nama Pemohon *</label>
                        <input class="wiz-field__input" name="nama" value="{{ $val('nama') }}" required>
                        @error('nama') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">No. KP</label>
                        <input class="wiz-field__input" name="nokp" id="nokpInput" value="{{ $val('nokp') }}" autocomplete="off">
                        @error('nokp') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                        <div id="nokpDup" style="display:none;margin-top:6px;font-size:12px;padding:8px 10px;border-radius:8px;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#b45309;"></div>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Umur</label>
                        <input type="number" class="wiz-field__input" name="umur" value="{{ $val('umur') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jantina</label>
                        <select class="wiz-field__select" name="jantina">
                            <option value="">—</option>
                            @foreach (['Lelaki', 'Perempuan'] as $opt)
                                <option value="{{ $opt }}" @selected($val('jantina') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Agama</label>
                        <input class="wiz-field__input" name="agama" value="{{ $val('agama') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Bangsa</label>
                        <input class="wiz-field__input" name="bangsa" value="{{ $val('bangsa') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Etnik</label>
                        <input class="wiz-field__input" name="etnik" value="{{ $val('etnik') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">OKU</label>
                        <select class="wiz-field__select" name="oku">
                            <option value="">—</option>
                            @foreach (['Ya', 'Tidak'] as $opt)
                                <option value="{{ $opt }}" @selected($val('oku') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Nama Penjaga</label>
                        <input class="wiz-field__input" name="nama_penjaga" value="{{ $val('nama_penjaga') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">No. KP Penjaga</label>
                        <input class="wiz-field__input" name="nokp_penjaga" value="{{ $val('nokp_penjaga') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Peringkat 2 · Permohonan ===== --}}
        <div class="wz-step" data-step="1">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Peringkat 2 · Permohonan</div>
                <div class="wiz-grid">
                    <div class="wiz-field">
                        <label class="wiz-field__label">Cawangan *</label>
                        <select class="wiz-field__select" name="cawangan" required>
                            <option value="">— Pilih —</option>
                            @foreach ($cawanganList as $c)
                                <option value="{{ $c }}" @selected($val('cawangan') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                        @error('cawangan') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Khidmat Nasihat</label>
                        <input type="date" class="wiz-field__input" name="tarikh_khidmat_nasihat" value="{{ $val('tarikh_khidmat_nasihat', 'date') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Permohonan</label>
                        <input type="date" class="wiz-field__input" name="tarikh_permohonan" value="{{ $val('tarikh_permohonan', 'date') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Kategori Kes</label>
                        <select class="wiz-field__select" name="kategori_kes">
                            <option value="">—</option>
                            @foreach ($kategoriList as $k)
                                <option value="{{ $k }}" @selected($val('kategori_kes') === $k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jenis Kes</label>
                        <select class="wiz-field__select" name="jenis_kes">
                            <option value="">—</option>
                            @foreach ($jenisList as $j)
                                <option value="{{ $j }}" @selected($val('jenis_kes') === $j)>{{ $j }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jenis Kategori</label>
                        <input class="wiz-field__input" name="jenis_kategori" value="{{ $val('jenis_kategori') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jenis Jenayah</label>
                        <input class="wiz-field__input" name="jenis_jenayah" value="{{ $val('jenis_jenayah') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Taraf</label>
                        <input class="wiz-field__input" name="taraf" value="{{ $val('taraf') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Peringkat 3 · Pendaftaran ===== --}}
        <div class="wz-step" data-step="2">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Peringkat 3 · Pendaftaran</div>
                <div class="wiz-grid">
                    <div class="wiz-field">
                        <label class="wiz-field__label">No. Fail</label>
                        <input class="wiz-field__input" name="no_fail" value="{{ $val('no_fail') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">No. Sistem</label>
                        <input class="wiz-field__input" name="no_sistem" value="{{ $val('no_sistem') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Daftar</label>
                        <input type="date" class="wiz-field__input" name="tarikh_daftar" value="{{ $val('tarikh_daftar', 'date') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Pegawai</label>
                        <input class="wiz-field__input" name="nama_pegawai" value="{{ $val('nama_pegawai') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Peringkat 4 · Keputusan ===== --}}
        <div class="wz-step" data-step="3">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Peringkat 4 · Keputusan</div>
                <div class="wiz-grid">
                    <div class="wiz-field">
                        <label class="wiz-field__label">Keputusan</label>
                        <input class="wiz-field__input" name="keputusan" value="{{ $val('keputusan') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Diterima</label>
                        <input class="wiz-field__input" name="diterima" value="{{ $val('diterima') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Kelulusan</label>
                        <input class="wiz-field__input" name="kelulusan" value="{{ $val('kelulusan') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Keputusan Menteri</label>
                        <input class="wiz-field__input" name="keputusan_menteri" value="{{ $val('keputusan_menteri') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Perakuan</label>
                        <input type="date" class="wiz-field__input" name="tarikh_perakuan" value="{{ $val('tarikh_perakuan', 'date') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Pemakluman</label>
                        <input type="date" class="wiz-field__input" name="tarikh_pemakluman" value="{{ $val('tarikh_pemakluman', 'date') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Sumbangan</label>
                        <input class="wiz-field__input" name="sumbangan" value="{{ $val('sumbangan') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Nilai Sumbangan (RM)</label>
                        <input type="number" class="wiz-field__input" name="nilai_sumbangan" value="{{ $val('nilai_sumbangan') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Peringkat 5 · Penutupan ===== --}}
        <div class="wz-step" data-step="4">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Peringkat 5 · Status &amp; Penutupan</div>
                <div class="wiz-grid">
                    <div class="wiz-field">
                        <label class="wiz-field__label">Status</label>
                        <input class="wiz-field__input" name="status" value="{{ $val('status') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Selesai</label>
                        <input type="date" class="wiz-field__input" name="tarikh_selesai" value="{{ $val('tarikh_selesai', 'date') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Sebab Selesai</label>
                        <input class="wiz-field__input" name="sebab_selesai" value="{{ $val('sebab_selesai') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Tutup Fail</label>
                        <input type="date" class="wiz-field__input" name="tarikh_tutup_fail" value="{{ $val('tarikh_tutup_fail', 'date') }}">
                    </div>
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Sebab Tutup Fail</label>
                        <textarea class="wiz-field__textarea" name="sebab_tutup_fail">{{ $val('sebab_tutup_fail') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Wizard nav ===== --}}
        <div class="wz-nav">
            <button type="button" class="btn btn--ghost" id="wzPrev">← Sebelum</button>
            <span class="wz-progress">Peringkat <span id="wzCur">1</span> / {{ count($steps) }}</span>
            <span>
                <button type="button" class="btn btn--primary" id="wzNext">Seterusnya →</button>
                <button type="submit" class="btn btn--primary" id="wzSubmit" style="display:none;">{{ $isCreate ? 'Daftar Permohonan' : 'Simpan Perubahan' }}</button>
            </span>
        </div>
    </form>

    <script>
        (function () {
            const form = document.getElementById('wizForm');
            const steps = Array.from(form.querySelectorAll('.wz-step'));
            const pills = Array.from(document.querySelectorAll('.wz-pill'));
            const prevBtn = document.getElementById('wzPrev');
            const nextBtn = document.getElementById('wzNext');
            const submitBtn = document.getElementById('wzSubmit');
            const curLabel = document.getElementById('wzCur');
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
                    if (!el.checkValidity()) return el;
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
                const target = +p.dataset.go;
                if (target <= cur) { show(target); return; }
                if (!firstInvalid(steps[cur])) show(target); else firstInvalid(steps[cur]).reportValidity();
            }));

            // Controlled submit: validate everything, jump to the first offending step.
            form.addEventListener('submit', (e) => {
                for (let i = 0; i < steps.length; i++) {
                    const bad = firstInvalid(steps[i]);
                    if (bad) { e.preventDefault(); show(i); bad.reportValidity(); return; }
                }
            });

            // Duplicate-IC guard (legacy check_nokp): warn if this IC already has applications.
            const nokpInput = document.getElementById('nokpInput');
            const nokpDup = document.getElementById('nokpDup');
            if (nokpInput && nokpDup) {
                nokpInput.addEventListener('blur', async () => {
                    const ic = nokpInput.value.trim();
                    if (ic.length < 6) { nokpDup.style.display = 'none'; return; }
                    try {
                        const res = await fetch('{{ route('kes.semak-nokp') }}?nokp=' + encodeURIComponent(ic), { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        if (data.exists) {
                            const rows = data.records.map(r => '• #' + r.id + ' — ' + r.nama + ' (' + r.no_fail + ', ' + r.status + ')').join('<br>');
                            nokpDup.innerHTML = '<strong>Amaran:</strong> No. KP ini mempunyai ' + data.records.length + ' permohonan terdahulu:<br>' + rows;
                            nokpDup.style.display = 'block';
                        } else {
                            nokpDup.style.display = 'none';
                        }
                    } catch (e) { nokpDup.style.display = 'none'; }
                });
            }

            // Reopen the peringkat that carries a server-side validation error.
            const errStep = form.dataset.errorStep;
            show(errStep !== '' && errStep != null ? +errStep : 0);
        })();
    </script>
@endsection
