<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log Masuk · iGuaman 2in1</title>
    @vite(['resources/css/system.css'])
</head>
<body class="system">

<div class="vb-page">

    {{-- ============ LEFT — editorial ============ --}}
    <div class="vb-left">
        <div>
            <div class="vb-left__top">
                <div class="wm"><span class="i">i</span>Guaman<span class="dot"></span></div>
                <div class="meta">2IN1 · BANTUAN GUAMAN</div>
            </div>
        </div>

        <div>
            <h2 class="vb-left__hero">
                <span class="line">Dua sistem,</span>
                <span class="line"><span class="accent">satu</span> ruang.</span>
                <span class="line">Rekod kes &amp;</span>
                <span class="line">panel peguam.<span class="dot dot--lg"></span></span>
            </h2>
            <p class="vb-left__lede">
                Ruang kerja iGuaman 2in1 — rekod kes, pengantaraan, mahkamah dan panel peguam dalam satu sistem.
            </p>

            <div class="vb-left__decisive">
                <div class="vb-decisive"><span class="word">Direkod</span>.</div>
                <div class="vb-decisive"><span class="word">Diagih</span>.</div>
                <div class="vb-decisive"><span class="word">Diselesaikan</span>.</div>
            </div>
        </div>

        <div class="vb-left__foot">
            <span class="stamp">iGuaman · 2in1</span>
            <span>Sesi disulitkan TLS 1.3</span>
        </div>
    </div>

    {{-- ============ RIGHT — White form ============ --}}
    <div class="vb-right">
        <div class="vb-right__inner">
            <div class="swap__screen">
                <div class="vb-right__head">
                    <div>
                        <div class="eyebrow" style="margin-bottom: 6px;">Akses Pengguna</div>
                        <h1 class="vb-h1">Log masuk.<span class="dot"></span></h1>
                    </div>
                </div>
                <p class="vb-sub">Masukkan emel dan kata laluan anda untuk meneruskan.</p>

                @if (session('status'))
                    <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18);">
                        {{ session('status') }}
                    </div>
                @endif

                <form class="va-form" method="POST" action="{{ route('system.login.attempt') }}">
                    @csrf

                    <div class="field">
                        <label class="field__label">Emel</label>
                        <input
                            type="email"
                            name="email"
                            class="field__input"
                            placeholder="nama@jbg.gov.my"
                            value="{{ old('email') }}"
                            autofocus
                            required>
                    </div>

                    <div class="field">
                        <label class="field__label">Kata Laluan</label>
                        <div class="field__row">
                            <input
                                type="password"
                                name="password"
                                id="passwordField"
                                class="field__input"
                                placeholder="••••••••"
                                required>
                            <button type="button" class="field__eye" onclick="
                                const f = document.getElementById('passwordField');
                                f.type = f.type === 'text' ? 'password' : 'text';
                            ">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="va-form__row">
                        <label style="color: var(--mute); display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="remember" style="margin: 0;">
                            <span>Ingat saya</span>
                        </label>
                        <a href="{{ route('password.request') }}">Lupa kata laluan?</a>
                    </div>

                    @if ($errors->any())
                        <div class="formerr">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <button type="submit" class="btn btn--primary btn--block">
                        Log Masuk
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                    </button>
                </form>

                <div style="margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                    <button type="button" class="demo-trigger" id="demoTrigger">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Lihat akaun demo
                    </button>
                    <a href="{{ route('home') }}" style="color: var(--mute); text-decoration: none; font-size: 11px;">← Laman awam</a>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- ============ DEMO USERS MODAL ============ --}}
<div class="demo-modal" id="demoModal" role="dialog" aria-modal="true" aria-labelledby="demoModalTitle">
    <div class="demo-modal__card">
        <button type="button" class="demo-modal__close" id="demoModalClose" aria-label="Tutup">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <div class="demo-modal__head">
            <div class="demo-modal__eyebrow">Akaun Demo · Prototaip</div>
            <h2 class="demo-modal__h" id="demoModalTitle">2 kawasan akses · 6 akaun ujian<span class="dot"></span></h2>
            <p class="demo-modal__sub">
                iGuaman menyokong kawasan kakitangan (rekod kes, pengantaraan, mahkamah, statistik) dan kawasan peguam panel. Klik mana-mana akaun di bawah untuk mengisi borang log masuk secara automatik.
            </p>
        </div>

        <div class="demo-modal__body">
            <div class="demo-tiers">

                {{-- ==== PEGAWAI ==== --}}
                <div class="demo-tier demo-tier--officer">
                    <div class="demo-tier__head">
                        <div class="demo-tier__badge">P1</div>
                        <div>
                            <div class="demo-tier__title">Pegawai</div>
                            <div class="demo-tier__role">Officer · L1</div>
                        </div>
                    </div>

                    <div class="demo-tier__caps">
                        <div class="demo-tier__caps-label">Kebenaran</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Lihat &amp; cari rekod kes</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Daftar permohonan baru</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Kemaskini pengantaraan</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Kemaskini kes mahkamah</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Jana statistik &amp; eksport</div>
                    </div>

                    <div class="demo-tier__accounts">
                        <div class="demo-tier__accounts-label">Akaun ujian (2)</div>

                        <button type="button" class="demo-tier__btn js-demo-login" data-email="demo@example.com">
                            <div class="demo-tier__btn-body">
                                <div class="demo-tier__btn-name">Demo Admin <span style="color: var(--teal); font-size: 10px; margin-left: 4px; font-weight: 700; letter-spacing: 0.08em;">UTAMA</span></div>
                                <div class="demo-tier__btn-email">demo@example.com</div>
                            </div>
                            <svg class="demo-tier__btn-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </button>

                        <button type="button" class="demo-tier__btn js-demo-login" data-email="pegawai@test.local">
                            <div class="demo-tier__btn-body">
                                <div class="demo-tier__btn-name">Test Pegawai</div>
                                <div class="demo-tier__btn-email">pegawai@test.local</div>
                            </div>
                            <svg class="demo-tier__btn-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>

                {{-- ==== KOORDINATOR / PENGARAH ==== --}}
                <div class="demo-tier demo-tier--supervisor">
                    <div class="demo-tier__head">
                        <div class="demo-tier__badge">P2</div>
                        <div>
                            <div class="demo-tier__title">Koordinator / Pengarah</div>
                            <div class="demo-tier__role">Penyelia · L2</div>
                        </div>
                    </div>

                    <div class="demo-tier__caps">
                        <div class="demo-tier__caps-label">Tambahan kepada Pegawai</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Pantau beban kes unit</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Agih / tugaskan kes</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Semak prestasi pengantaraan</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Akses laporan unit</div>
                    </div>

                    <div class="demo-tier__accounts">
                        <div class="demo-tier__accounts-label">Akaun ujian (2)</div>

                        <button type="button" class="demo-tier__btn js-demo-login" data-email="koordinator@test.local">
                            <div class="demo-tier__btn-body">
                                <div class="demo-tier__btn-name">Test Koordinator</div>
                                <div class="demo-tier__btn-email">koordinator@test.local</div>
                            </div>
                            <svg class="demo-tier__btn-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </button>

                        <button type="button" class="demo-tier__btn js-demo-login" data-email="pengarah@test.local">
                            <div class="demo-tier__btn-body">
                                <div class="demo-tier__btn-name">Test Pengarah</div>
                                <div class="demo-tier__btn-email">pengarah@test.local</div>
                            </div>
                            <svg class="demo-tier__btn-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>

                {{-- ==== PENTADBIR ==== --}}
                <div class="demo-tier demo-tier--admin">
                    <div class="demo-tier__head">
                        <div class="demo-tier__badge">P3</div>
                        <div>
                            <div class="demo-tier__title">Pentadbir</div>
                            <div class="demo-tier__role">Admin · L3</div>
                        </div>
                    </div>

                    <div class="demo-tier__caps">
                        <div class="demo-tier__caps-label">Tambahan kepada Penyelia</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Pengurusan pengguna</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Peranan &amp; kebenaran</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Tetapan sistem &amp; API</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Audit log penuh (kekal)</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Sandaran &amp; pemulihan</div>
                    </div>

                    <div class="demo-tier__accounts">
                        <div class="demo-tier__accounts-label">Akaun ujian (1)</div>

                        <button type="button" class="demo-tier__btn js-demo-login" data-email="admin@test.local">
                            <div class="demo-tier__btn-body">
                                <div class="demo-tier__btn-name">Test Admin</div>
                                <div class="demo-tier__btn-email">admin@test.local</div>
                            </div>
                            <svg class="demo-tier__btn-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>

                {{-- ==== PEGUAM PANEL (kawasan luaran) ==== --}}
                <div class="demo-tier demo-tier--officer" style="grid-column: 1 / -1;">
                    <div class="demo-tier__head">
                        <div class="demo-tier__badge" style="background: var(--pine-deep); color: #fff;">PG</div>
                        <div>
                            <div class="demo-tier__title">Peguam Panel</div>
                            <div class="demo-tier__role">Lawyer · Akses Luaran</div>
                        </div>
                    </div>

                    <div class="demo-tier__caps">
                        <div class="demo-tier__caps-label">Kawasan berasingan (bukan kakitangan)</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Dashboard peguam</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Lihat kes ditugaskan (bila dipautkan)</div>
                        <div class="demo-tier__cap"><span class="demo-tier__cap-tick">✓</span> Papar profil panel</div>
                    </div>

                    <div class="demo-tier__accounts">
                        <div class="demo-tier__accounts-label">Akaun ujian (1)</div>

                        <button type="button" class="demo-tier__btn js-demo-login" data-email="peguam@test.local">
                            <div class="demo-tier__btn-body">
                                <div class="demo-tier__btn-name">Test Peguam</div>
                                <div class="demo-tier__btn-email">peguam@test.local</div>
                            </div>
                            <svg class="demo-tier__btn-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <div class="demo-modal__foot">
            <span class="demo-modal__foot-pwd">
                Kata laluan untuk semua akaun: <code>password</code>
            </span>
            <span style="font-size: 11px; color: var(--mute-2); letter-spacing: 0.04em;">
                Hanya untuk prototaip · Pengeluaran akan guna MFA + SSO
            </span>
        </div>
    </div>
</div>

<script>
    const demoTrigger = document.getElementById('demoTrigger');
    const demoModal = document.getElementById('demoModal');
    const demoModalClose = document.getElementById('demoModalClose');

    demoTrigger?.addEventListener('click', () => {
        demoModal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    });

    function closeDemoModal() {
        demoModal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    demoModalClose?.addEventListener('click', closeDemoModal);
    demoModal?.addEventListener('click', (e) => {
        if (e.target === demoModal) closeDemoModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && demoModal.classList.contains('is-open')) closeDemoModal();
    });

    // Click an account → fill email + password, then close.
    document.querySelectorAll('.js-demo-login').forEach(btn => {
        btn.addEventListener('click', () => {
            const email = btn.dataset.email;
            const emailField = document.querySelector('input[name="email"]');
            const passwordField = document.querySelector('input[name="password"]');
            if (emailField) emailField.value = email;
            if (passwordField) passwordField.value = 'password';
            closeDemoModal();
            document.querySelector('button[type="submit"].btn--primary')?.focus();
        });
    });
</script>

</body>
</html>
