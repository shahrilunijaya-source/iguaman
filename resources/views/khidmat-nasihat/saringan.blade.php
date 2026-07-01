@extends('layouts.staff')

@section('title', 'Saringan Kelayakan Khidmat Nasihat')

@section('content')
    <style>
        .sg-modal-bg { position:fixed; inset:0; background:rgba(13,46,72,0.45); display:none; align-items:center; justify-content:center; z-index:50; padding:20px; }
        .sg-modal-bg.is-open { display:flex; }
        .sg-modal { background:#fff; border-radius:var(--r-lg); max-width:640px; width:100%; max-height:90vh; overflow:auto; box-shadow:0 20px 60px rgba(0,0,0,0.25); }
        .sg-modal__head { padding:20px 24px; border-bottom:1px solid var(--line); }
        .sg-modal__step { font-size:11px; font-weight:700; letter-spacing:.08em; color:var(--teal); text-transform:uppercase; }
        .sg-modal__title { font-size:18px; font-weight:800; color:var(--pine-deep); margin:4px 0 0; }
        .sg-modal__body { padding:22px 24px; }
        .sg-modal__foot { padding:16px 24px; border-top:1px solid var(--line); display:flex; gap:10px; justify-content:flex-end; }
        .sg-q { font-size:14px; color:var(--ink); line-height:1.6; margin:0 0 12px; }
        .sg-radio { display:flex; gap:18px; margin:6px 0 18px; }
        .sg-radio label { display:flex; align-items:center; gap:7px; font-size:14px; cursor:pointer; }
        .sg-list { font-size:13px; color:var(--mute); line-height:1.7; margin:6px 0 16px; padding-left:20px; }
        .sg-card { border:1px solid var(--line); border-radius:var(--r-lg); padding:18px; background:rgba(26,111,168,0.04); margin-bottom:14px; }
        .sg-fee { font-size:13px; color:var(--mute); }
        .sg-fee strong { color:var(--pine-deep); }
        .sg-err { color:var(--danger); font-size:12px; margin-top:-10px; margin-bottom:12px; display:none; }
        .sg-err.is-on { display:block; }
    </style>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Saringan Kelayakan<span class="dot"></span></h1>
            <p class="tap-head__sub">Lengkapkan 3 peringkat saringan sebelum mendaftar permohonan Khidmat Nasihat.</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('khidmat.index') }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if (session('saringan_gagal'))
        <div class="formerr" style="margin-bottom:16px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            {{ session('saringan_gagal') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="tap-card">
        <div class="tap-card__eyebrow">Saringan Permohonan</div>
        <p style="font-size:13px; color:var(--mute); line-height:1.6; margin:0 0 16px;">
            Permohonan Khidmat Nasihat tertakluk kepada syarat kelayakan dan terma & syarat yang ditetapkan.
            Sila mulakan saringan untuk meneruskan.
        </p>
        <button type="button" class="btn btn--primary" id="sgStart">Mula Saringan</button>
    </div>

    <form method="POST" action="{{ route('khidmat.saringan.semak') }}" id="sgForm">
        @csrf

        {{-- ===== Peringkat 1 · Jenis + Pengisytiharan Pendapatan ===== --}}
        <div class="sg-modal-bg" data-modal="1">
            <div class="sg-modal">
                <div class="sg-modal__head">
                    <div class="sg-modal__step">Peringkat 1 / 3 · Saringan</div>
                    <h2 class="sg-modal__title">Jenis Khidmat & Pengisytiharan Pendapatan</h2>
                </div>
                <div class="sg-modal__body">
                    <p class="sg-q">Pilih jenis khidmat nasihat yang dipohon:</p>
                    <select class="wiz-field__select" id="sgJenis" name="saringan_jenis" style="margin-bottom:18px;">
                        <option value="">- Pilih -</option>
                        <option value="sivil_syariah">Sivil / Syariah</option>
                        <option value="pendamping_jenayah">Pendamping Guaman / Jenayah</option>
                    </select>
                    <div class="sg-err" id="sgJenisErr">Sila pilih jenis khidmat.</div>

                    <div id="sgIncomeBlock">
                        <p class="sg-q">Saya mengesahkan bahawa jumlah sumber pendapatan tahunan saya
                            <strong>tidak melebihi RM50,000.00</strong>.</p>
                        <div class="sg-radio">
                            <label><input type="radio" name="pendapatan_bawah_had" value="Ya"> Ya</label>
                            <label><input type="radio" name="pendapatan_bawah_had" value="Tidak"> Tidak</label>
                        </div>
                        <div class="sg-err" id="sgIncomeErr">Sila jawab pengisytiharan pendapatan.</div>
                        <div class="sg-card" id="sgSumbanganNote" style="display:none;">
                            <div class="sg-fee">Pendapatan melebihi RM50,000 - <strong>Laluan Sumbangan</strong>:
                                FI Khidmat Nasihat RM10.00 + Sumbangan RM250.00 = <strong>RM260.00</strong>.</div>
                        </div>
                    </div>
                    <p class="sg-q" id="sgPendampingNote" style="display:none; color:var(--mute);">
                        Bagi Pendamping Guaman / Jenayah, tiada had terhadap jumlah pendapatan tahunan.
                    </p>
                </div>
                <div class="sg-modal__foot">
                    <button type="button" class="btn btn--ghost" data-cancel>Batal</button>
                    <button type="button" class="btn btn--primary" data-next="2">Seterusnya</button>
                </div>
            </div>
        </div>

        {{-- ===== Peringkat 2 · Soalan Kelayakan ===== --}}
        <div class="sg-modal-bg" data-modal="2">
            <div class="sg-modal">
                <div class="sg-modal__head">
                    <div class="sg-modal__step">Peringkat 2 / 3 · Kelayakan</div>
                    <h2 class="sg-modal__title">Soalan Kelayakan</h2>
                </div>
                <div class="sg-modal__body">
                    <p class="sg-q">Saya <strong>tidak pernah</strong> menerima nasihat guaman bagi khidmat nasihat
                        yang ingin dipohon daripada mana-mana pihak.</p>
                    <div class="sg-radio">
                        <label><input type="radio" name="tiada_nasihat_terdahulu" value="Ya"> Ya</label>
                        <label><input type="radio" name="tiada_nasihat_terdahulu" value="Tidak"> Tidak</label>
                    </div>

                    <p class="sg-q">Nasihat guaman yang diperlukan <strong>tidak melibatkan</strong> perkara berikut:</p>
                    <ol class="sg-list">
                        <li>Pertikaian melibatkan pihak ketiga yang bercanggah kepentingan.</li>
                        <li>Perkara berkaitan tafsiran Perlembagaan Persekutuan.</li>
                        <li>Perkara yang menyentuh Akta Hasutan 1948.</li>
                        <li>Perkara melibatkan undang-undang antarabangsa.</li>
                        <li>Perkara yang sedang dalam prosiding mahkamah pihak lawan.</li>
                    </ol>
                    <div class="sg-radio">
                        <label><input type="radio" name="tiada_perkara_dikecualikan" value="Ya"> Ya</label>
                        <label><input type="radio" name="tiada_perkara_dikecualikan" value="Tidak"> Tidak</label>
                    </div>
                    <div class="sg-err" id="sgEligErr">Sila jawab kedua-dua soalan kelayakan.</div>
                </div>
                <div class="sg-modal__foot">
                    <button type="button" class="btn btn--ghost" data-back="1">Kembali</button>
                    <button type="button" class="btn btn--primary" data-next="3">Seterusnya</button>
                </div>
            </div>
        </div>

        {{-- ===== Peringkat 3 · Terma & Syarat ===== --}}
        <div class="sg-modal-bg" data-modal="3">
            <div class="sg-modal">
                <div class="sg-modal__head">
                    <div class="sg-modal__step">Peringkat 3 / 3 · Terma & Syarat</div>
                    <h2 class="sg-modal__title">Terma & Syarat</h2>
                </div>
                <div class="sg-modal__body">
                    <ol class="sg-list">
                        <li>Permohonan adalah berdasarkan <em>first-come-first-served</em>.</li>
                        <li>Permohonan perlu dibuat sekurang-kurangnya 3 hari bekerja lebih awal.</li>
                        <li>Bayaran RM10.00 (tunai) dikenakan bagi setiap permohonan (kecuali yang dikecualikan).</li>
                        <li>Pemohon perlu hadir 15 minit lebih awal daripada masa temu janji.</li>
                        <li>Setiap sesi adalah selama 30 minit.</li>
                    </ol>
                    <label style="display:flex; align-items:center; gap:10px; font-size:14px;">
                        <input type="checkbox" name="terma" id="sgTerma" value="1">
                        Saya telah membaca dan bersetuju dengan Terma dan Syarat yang ditetapkan.
                    </label>
                    <div class="sg-err" id="sgTermaErr">Sila bersetuju dengan Terma dan Syarat.</div>
                </div>
                <div class="sg-modal__foot">
                    <button type="button" class="btn btn--ghost" data-back="2">Kembali</button>
                    <button type="submit" class="btn btn--primary" id="sgSubmit">Mohon Khidmat Nasihat</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        (function () {
            const modals = Array.from(document.querySelectorAll('.sg-modal-bg'));
            const byNum = (n) => modals.find((m) => m.dataset.modal === String(n));
            function open(n) { modals.forEach((m) => m.classList.remove('is-open')); byNum(n).classList.add('is-open'); }
            function closeAll() { modals.forEach((m) => m.classList.remove('is-open')); }

            document.getElementById('sgStart').addEventListener('click', () => open(1));
            document.querySelectorAll('[data-cancel]').forEach((b) => b.addEventListener('click', closeAll));
            document.querySelectorAll('[data-back]').forEach((b) => b.addEventListener('click', () => open(b.dataset.back)));

            const jenisSel = document.getElementById('sgJenis');
            const incomeBlock = document.getElementById('sgIncomeBlock');
            const pendampingNote = document.getElementById('sgPendampingNote');
            const sumbanganNote = document.getElementById('sgSumbanganNote');
            function syncJenis() {
                const isSivil = jenisSel.value === 'sivil_syariah';
                const isPendamping = jenisSel.value === 'pendamping_jenayah';
                incomeBlock.style.display = isSivil ? '' : 'none';
                pendampingNote.style.display = isPendamping ? '' : 'none';
            }
            jenisSel.addEventListener('change', syncJenis);
            document.querySelectorAll('input[name="pendapatan_bawah_had"]').forEach((r) => r.addEventListener('change', () => {
                sumbanganNote.style.display = (r.value === 'Tidak' && r.checked) ? '' : 'none';
            }));
            syncJenis();

            const val = (name) => { const el = document.querySelector(`input[name="${name}"]:checked`); return el ? el.value : ''; };
            const err = (id, on) => document.getElementById(id).classList.toggle('is-on', on);

            // Peringkat 1 -> 2
            byNum(1).querySelector('[data-next="2"]').addEventListener('click', () => {
                if (!jenisSel.value) { err('sgJenisErr', true); return; }
                err('sgJenisErr', false);
                if (jenisSel.value === 'sivil_syariah' && !val('pendapatan_bawah_had')) { err('sgIncomeErr', true); return; }
                err('sgIncomeErr', false);
                open(2);
            });
            // Peringkat 2 -> 3
            byNum(2).querySelector('[data-next="3"]').addEventListener('click', () => {
                if (!val('tiada_nasihat_terdahulu') || !val('tiada_perkara_dikecualikan')) { err('sgEligErr', true); return; }
                err('sgEligErr', false);
                open(3);
            });
            // Submit guard: terms must be accepted (server re-validates + enforces eligibility).
            document.getElementById('sgForm').addEventListener('submit', (e) => {
                if (!document.getElementById('sgTerma').checked) { e.preventDefault(); err('sgTermaErr', true); open(3); }
            });
        })();
    </script>
@endsection
