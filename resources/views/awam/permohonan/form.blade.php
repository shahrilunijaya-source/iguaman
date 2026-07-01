@extends('layouts.awam')

@section('title', 'Permohonan Khidmat Nasihat')

@php
    $steps = ['Maklumat', 'Slot Janji Temu', 'Perakuan'];
    $stepOf = [
        'nama_mangsa' => 0, 'id_pengenalan_mangsa' => 0, 'jantina_mangsa' => 0,
        'umur_mangsa' => 0, 'bangsa' => 0, 'agama' => 0, 'tarikh_lahir_mangsa' => 0,
        'alamat_surat1' => 0, 'alamat_surat2' => 0, 'alamat_surat3' => 0, 'poskod' => 0,
        'cawangan_id' => 0, 'id_kategori' => 0, 'id_negeri' => 0, 'jenis_kes' => 0, 'ulasan_permohonan' => 0,
        'tarikh_temu_janji' => 1, 'masa_temu_janji' => 1,
        'perakuan' => 2,
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
        .kn-slot-grid { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
        .kn-chip { padding:8px 14px; border:1px solid var(--line); border-radius:10px; background:#fff; cursor:pointer; font-size:13px; transition:all .12s; }
        .kn-chip.is-on { border-color:var(--teal); background:rgba(26,111,168,0.08); color:var(--pine-deep); font-weight:700; }
        .kn-hint { font-size:12px; color:var(--mute); margin-top:8px; }
        .wiz-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .wiz-field { display:flex; flex-direction:column; gap:4px; }
        .wiz-field--span-2 { grid-column:1/-1; }
        .wiz-field__label { font-size:12px; color:var(--mute); font-weight:500; }
        .wiz-field__input, .wiz-field__select, .wiz-field__textarea { border:1px solid var(--line); border-radius:var(--r); padding:8px 10px; font-size:13px; width:100%; background:#fff; }
        .wiz-field__textarea { resize:vertical; min-height:80px; }
        .wiz-field__hint { font-size:11px; color:var(--mute); }
        @media (max-width:600px) { .wiz-grid { grid-template-columns:1fr; } .wiz-field--span-2 { grid-column:1; } }
    </style>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Permohonan Khidmat Nasihat</h1>
            <p class="tap-head__sub">Isi maklumat di bawah untuk mendaftar permohonan.</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('awam.dashboard') }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px; background:#fff0f0; border:1px solid #fca5a5; border-radius:8px; padding:12px 16px; color:#dc2626; font-size:13px;">
            Sila betulkan {{ $errors->count() }} ralat di bawah.
        </div>
    @endif

    {{-- Stepper --}}
    <div class="wz-steps">
        @foreach ($steps as $i => $label)
            <div class="wz-pill" data-go="{{ $i }}">
                <span class="wz-pill__no">{{ $i + 1 }}</span>
                <span>{{ $label }}</span>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('awam.permohonan.store') }}" id="knWiz"
          data-error-step="{{ $errorStep ?? '' }}"
          data-tarikh-url="{{ route('slot.tarikh') }}"
          data-masa-url="{{ route('slot.masa') }}"
          novalidate>
        @csrf
        <input type="hidden" name="aksi" id="knAksi" value="draf">

        {{-- Step 1: Maklumat --}}
        <div class="wz-step" data-step="0">
            <div class="tap-card" style="margin-bottom:18px;">
                <div class="tap-card__eyebrow">Maklumat Pemohon</div>
                <div class="wiz-grid">
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Nama Penuh *</label>
                        <input class="wiz-field__input" name="nama_mangsa" value="{{ old('nama_mangsa', auth()->user()->name) }}" required>
                        @error('nama_mangsa') <div class="wiz-field__hint" style="color:#dc2626;">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">No. Kad Pengenalan</label>
                        <input class="wiz-field__input" name="id_pengenalan_mangsa" value="{{ old('id_pengenalan_mangsa', auth()->user()->nokp) }}">
                        @error('id_pengenalan_mangsa') <div class="wiz-field__hint" style="color:#dc2626;">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jantina</label>
                        <select class="wiz-field__select" name="jantina_mangsa">
                            <option value="">-</option>
                            @foreach (['Lelaki', 'Perempuan'] as $opt)
                                <option value="{{ $opt }}" @selected(old('jantina_mangsa') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Lahir</label>
                        <input type="date" class="wiz-field__input" name="tarikh_lahir_mangsa" value="{{ old('tarikh_lahir_mangsa') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Bangsa</label>
                        <input class="wiz-field__input" name="bangsa" value="{{ old('bangsa') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Agama</label>
                        <input class="wiz-field__input" name="agama" value="{{ old('agama') }}">
                    </div>
                </div>
            </div>

            <div class="tap-card" style="margin-bottom:18px;">
                <div class="tap-card__eyebrow">Alamat Surat-menyurat</div>
                <div class="wiz-grid">
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Alamat 1</label>
                        <input class="wiz-field__input" name="alamat_surat1" value="{{ old('alamat_surat1') }}">
                    </div>
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Alamat 2</label>
                        <input class="wiz-field__input" name="alamat_surat2" value="{{ old('alamat_surat2') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Alamat 3</label>
                        <input class="wiz-field__input" name="alamat_surat3" value="{{ old('alamat_surat3') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Poskod</label>
                        <input class="wiz-field__input" name="poskod" value="{{ old('poskod') }}" maxlength="10">
                    </div>
                </div>
            </div>

            <div class="tap-card">
                <div class="tap-card__eyebrow">Cawangan &amp; Kategori Kes</div>
                <div class="wiz-grid">
                    <div class="wiz-field">
                        <label class="wiz-field__label">Cawangan JBG *</label>
                        <select class="wiz-field__select" name="cawangan_id" id="knCawangan"
                                data-negeri-map='@json($cawanganList->pluck('negeri_id', 'id'))' required>
                            <option value="">- Pilih -</option>
                            @foreach ($cawanganList as $c)
                                <option value="{{ $c->id }}" @selected(old('cawangan_id') == $c->id)>{{ $c->nama }}</option>
                            @endforeach
                        </select>
                        @error('cawangan_id') <div class="wiz-field__hint" style="color:#dc2626;">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Negeri</label>
                        <select class="wiz-field__select" name="id_negeri" id="knNegeri">
                            <option value="">-</option>
                            @foreach ($negeriList as $id => $nama)
                                <option value="{{ $id }}" @selected(old('id_negeri') == $id)>{{ $nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Kategori Kes</label>
                        <select class="wiz-field__select" name="id_kategori" id="knKategori">
                            <option value="">- Pilih -</option>
                            @foreach ($kategoriList as $k)
                                <option value="{{ $k->id }}" @selected(old('id_kategori') == $k->id)>{{ $k->jenis_kategori }}</option>
                            @endforeach
                        </select>
                        @error('id_kategori') <div class="wiz-field__hint" style="color:#dc2626;">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Jumlah Pendapatan (RM)</label>
                        <input type="number" step="0.01" min="0" class="wiz-field__input" name="jumlah_pendapatan" value="{{ old('jumlah_pendapatan') }}">
                        <div class="wiz-field__hint">Pendapatan melebihi RM50,000 (Sivil/Syariah) &rarr; Bayaran RM260.</div>
                    </div>
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Perihal Kes</label>
                        <textarea class="wiz-field__textarea" name="ulasan_permohonan">{{ old('ulasan_permohonan') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 2: Slot Janji Temu --}}
        <div class="wz-step" data-step="1">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Slot Janji Temu</div>
                <div class="wiz-grid">
                    <div class="wiz-field wiz-field--span-2">
                        <div class="kn-hint" id="knSlotBranchHint">Pilih cawangan di Langkah 1 untuk memuatkan tarikh tersedia.</div>
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Temu Janji *</label>
                        <select class="wiz-field__select" name="tarikh_temu_janji" id="knTarikh" data-selected="{{ old('tarikh_temu_janji') }}">
                            <option value="">- Pilih cawangan dahulu -</option>
                        </select>
                        @error('tarikh_temu_janji') <div class="wiz-field__hint" style="color:#dc2626;">{{ $message }}</div> @enderror
                    </div>
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Masa Temu Janji *</label>
                        <input type="hidden" name="masa_temu_janji" id="knMasa" value="{{ old('masa_temu_janji') }}" data-preselect="{{ old('masa_temu_janji') }}">
                        <div class="kn-slot-grid" id="knMasaGrid">
                            <span class="kn-hint">Pilih tarikh dahulu.</span>
                        </div>
                        @error('masa_temu_janji') <div class="wiz-field__hint" style="color:#dc2626;">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 3: Perakuan --}}
        <div class="wz-step" data-step="2">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Perakuan</div>
                <p style="font-size:13px; color:var(--mute); line-height:1.6; margin:0 0 14px;">
                    Saya mengesahkan bahawa segala maklumat yang diberikan adalah benar dan lengkap.
                    Permohonan ini akan ditetapkan ke status <strong>BAHARU</strong> setelah dihantar.
                </p>
                <label style="display:flex; align-items:center; gap:10px; font-size:14px; cursor:pointer;">
                    <input type="checkbox" name="perakuan" id="knPerakuan" value="1" @checked(old('perakuan'))>
                    Saya membuat perakuan ini dan bersetuju dengan syarat-syarat permohonan.
                </label>
                @error('perakuan') <div class="wiz-field__hint" style="color:#dc2626; margin-top:8px;">{{ $message }}</div> @enderror
            </div>
        </div>

        {{-- Wizard nav --}}
        <div class="wz-nav">
            <button type="button" class="btn btn--ghost" id="wzPrev">&#8592; Sebelum</button>
            <span class="wz-progress">Langkah <span id="wzCur">1</span> / {{ count($steps) }}</span>
            <span style="display:flex; gap:10px;">
                <button type="submit" class="btn btn--ghost" id="wzDraf" data-aksi="draf">Simpan Draf</button>
                <button type="button" class="btn btn--primary" id="wzNext">Seterusnya &#8594;</button>
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

            drafBtn.addEventListener('click', () => { aksi.value = 'draf'; form.noValidate = true; });
            submitBtn.addEventListener('click', () => { aksi.value = 'hantar'; form.noValidate = false; });

            form.addEventListener('submit', (e) => {
                if (aksi.value === 'draf') return;
                for (let i = 0; i < steps.length; i++) {
                    const bad = firstInvalid(steps[i]);
                    if (bad) { e.preventDefault(); show(i); bad.reportValidity(); return; }
                }
                if (!document.getElementById('knMasa').value) {
                    e.preventDefault(); show(1);
                    alert('Sila pilih masa temu janji.');
                }
            });

            // Slot date/time via JSON endpoints
            const cawangan = document.getElementById('knCawangan');
            const negeri = document.getElementById('knNegeri');
            const negeriMap = JSON.parse(cawangan.dataset.negeriMap || '{}');
            const tarikhSel = document.getElementById('knTarikh');
            const masaGrid = document.getElementById('knMasaGrid');
            const masaInput = document.getElementById('knMasa');
            const branchHint = document.getElementById('knSlotBranchHint');
            const tarikhUrl = form.dataset.tarikhUrl;
            const masaUrl = form.dataset.masaUrl;

            cawangan.addEventListener('change', () => {
                const n = negeriMap[cawangan.value];
                if (n && !negeri.value) negeri.value = String(n);
                loadDates();
            });

            async function loadDates() {
                masaGrid.innerHTML = '<span class="kn-hint">Pilih tarikh dahulu.</span>';
                masaInput.value = '';
                if (!cawangan.value) {
                    tarikhSel.innerHTML = '<option value="">- Pilih cawangan dahulu -</option>';
                    branchHint.textContent = 'Pilih cawangan di Langkah 1 untuk memuatkan tarikh tersedia.';
                    return;
                }
                tarikhSel.innerHTML = '<option value="">Memuatkan…</option>';
                branchHint.textContent = 'Memuatkan tarikh tersedia…';
                try {
                    const res = await fetch(tarikhUrl + '?cawangan_id=' + encodeURIComponent(cawangan.value), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    const dates = data.dates || [];
                    tarikhSel.innerHTML = '<option value="">- Pilih tarikh -</option>';
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

            const errStep = form.dataset.errorStep;
            show(errStep !== '' && errStep != null ? +errStep : 0);
        })();
    </script>
@endsection
