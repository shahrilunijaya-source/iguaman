<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal Khidmat Nasihat Jabatan Bantuan Guaman Malaysia — mohon nasihat guaman percuma, tempah janji temu dan semak kelayakan secara dalam talian.">
    <title>Portal Khidmat Nasihat — Jabatan Bantuan Guaman Malaysia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Self-contained: inline brand styles so the landing renders regardless of Vite build state.
         Brand tokens mirror resources/css/theme.css — teal #00B8A9 / pine #003D3A / orange #FF6B35. --}}
    <style>
        :root {
            --teal: #00B8A9; --teal-600: #009B8E; --teal-700: #007D72; --teal-50: #E6F8F6;
            --pine: #003D3A; --pine-700: #002F2C; --pine-900: #001E1C;
            --orange: #FF6B35; --orange-50: #FFF1EB;
            --paper: #FAFAF7; --ink: #0A0E13; --mute: #5B6660; --line: #E5E3DC;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: var(--ink); background: var(--paper);
            -webkit-font-smoothing: antialiased; line-height: 1.6;
        }
        a { color: inherit; text-decoration: none; }
        .wrap { width: 100%; max-width: 1120px; margin-inline: auto; padding-inline: 24px; }

        /* ---- top bar ---- */
        .topbar {
            position: sticky; top: 0; z-index: 40;
            background: rgba(250, 250, 247, 0.82); backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
        }
        .topbar .wrap { display: flex; align-items: center; justify-content: space-between; height: 68px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 17px; color: var(--pine); }
        .brand-mark {
            width: 34px; height: 34px; border-radius: 9px; display: grid; place-items: center;
            background: var(--pine); color: #fff; font-weight: 800; font-size: 13px; letter-spacing: .02em;
        }
        .dot { width: 7px; height: 7px; border-radius: 999px; background: var(--teal); display: inline-block; }
        .topnav { display: flex; align-items: center; gap: 28px; }
        .topnav a.navlink { font-size: 14px; font-weight: 500; color: var(--mute); transition: color .15s; }
        .topnav a.navlink:hover { color: var(--pine); }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px;
            padding: 10px 18px; border-radius: 10px; cursor: pointer; transition: transform .15s, box-shadow .15s, background .15s;
            border: 1px solid transparent; white-space: nowrap;
        }
        .btn-primary { background: var(--teal); color: #fff; box-shadow: 0 6px 18px -6px rgba(0,184,169,.6); }
        .btn-primary:hover { background: var(--teal-600); transform: translateY(-1px); box-shadow: 0 10px 26px -6px rgba(0,184,169,.7); }
        .btn-pine { background: var(--pine); color: #fff; }
        .btn-pine:hover { background: var(--pine-700); transform: translateY(-1px); }
        .btn-ghost { background: #fff; color: var(--pine); border-color: var(--line); }
        .btn-ghost:hover { border-color: var(--teal); color: var(--teal-700); }
        .btn-orange { background: var(--orange); color: #fff; }
        .btn-orange:hover { background: #E85A28; transform: translateY(-1px); }
        .btn-lg { padding: 14px 26px; font-size: 15px; border-radius: 12px; }
        .logout-form { display: inline-flex; align-items: center; margin: 0; }
        .logout-btn { border: none; cursor: pointer; font-family: inherit; }
        .f-logout { background: none; border: none; padding: 0; color: inherit; font: inherit; cursor: pointer; }
        .f-logout:hover { text-decoration: underline; }

        /* ---- hero ---- */
        .hero { position: relative; overflow: hidden; padding: 84px 0 8px; }
        .hero::before {
            content: ""; position: absolute; inset: 0; z-index: -1;
            background:
                radial-gradient(60% 50% at 85% 0%, rgba(0,184,169,.10), transparent 60%),
                radial-gradient(50% 40% at 0% 100%, rgba(255,107,53,.07), transparent 55%);
        }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 9px; font-size: 12.5px; font-weight: 600;
            letter-spacing: .06em; text-transform: uppercase; color: var(--teal-700);
            background: var(--teal-50); padding: 7px 14px; border-radius: 999px; margin-bottom: 24px;
        }
        .hero h1 {
            font-size: clamp(2.4rem, 1.4rem + 4.4vw, 4.2rem); line-height: 1.04; font-weight: 800;
            letter-spacing: -0.03em; color: var(--pine); max-width: 16ch;
        }
        .hero h1 em { font-style: normal; color: var(--teal); }
        .hero p.lead { margin-top: 22px; font-size: clamp(1rem, .95rem + .4vw, 1.18rem); color: var(--mute); max-width: 52ch; }
        .hero-cta { margin-top: 36px; display: flex; flex-wrap: wrap; gap: 14px; }
        .hero-note { margin-top: 18px; font-size: 13px; color: var(--mute); }
        .hero-note a { color: var(--teal-700); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; }

        /* ---- stats strip ---- */
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: var(--line);
            border: 1px solid var(--line); border-radius: 16px; overflow: hidden; margin-top: 64px; }
        .stat { background: #fff; padding: 26px 24px; }
        .stat b { display: block; font-size: 26px; font-weight: 800; color: var(--pine); letter-spacing: -.02em; }
        .stat span { font-size: 13.5px; color: var(--mute); }

        /* ---- section heading ---- */
        section.block { padding: 80px 0; }
        .block-head { max-width: 52ch; margin-bottom: 44px; }
        .block-head .kicker { font-size: 13px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--orange); }
        .block-head h2 { margin-top: 10px; font-size: clamp(1.7rem, 1.2rem + 1.8vw, 2.4rem); font-weight: 700; letter-spacing: -.02em; color: var(--pine); }
        .block-head p { margin-top: 12px; color: var(--mute); }

        /* ---- feature cards ---- */
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .card {
            background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 30px 26px;
            transition: transform .18s, box-shadow .18s, border-color .18s;
        }
        .card:hover { transform: translateY(-4px); box-shadow: 0 18px 40px -22px rgba(0,61,58,.4); border-color: rgba(0,184,169,.4); }
        .card .ico { width: 48px; height: 48px; border-radius: 13px; display: grid; place-items: center;
            background: var(--teal-50); color: var(--teal-700); margin-bottom: 18px; }
        .card .ico svg { width: 24px; height: 24px; }
        .card h3 { font-size: 18px; font-weight: 700; color: var(--pine); }
        .card p { margin-top: 8px; font-size: 14.5px; color: var(--mute); }

        /* ---- steps ---- */
        .steps { background: var(--pine); border-radius: 24px; padding: 56px 48px; color: #fff; }
        .steps .block-head h2 { color: #fff; }
        .steps .block-head .kicker { color: var(--teal); }
        .step-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 28px; margin-top: 8px; }
        .step .n { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center;
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16); color: var(--teal); font-weight: 700; margin-bottom: 16px; }
        .step h4 { font-size: 16px; font-weight: 600; }
        .step p { margin-top: 6px; font-size: 13.5px; color: rgba(255,255,255,.62); }

        /* ---- lawyer band ---- */
        .band {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 24px;
            background: linear-gradient(120deg, var(--orange-50), #fff);
            border: 1px solid #F6D9C8; border-radius: 22px; padding: 40px 44px;
        }
        .band h2 { font-size: clamp(1.4rem, 1.1rem + 1.2vw, 2rem); font-weight: 700; color: var(--pine); letter-spacing: -.02em; }
        .band p { margin-top: 8px; color: var(--mute); max-width: 48ch; font-size: 14.5px; }

        /* ---- footer ---- */
        footer.site { border-top: 1px solid var(--line); padding: 48px 0; margin-top: 28px; }
        footer.site .wrap { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
        footer.site .f-links { display: flex; gap: 24px; flex-wrap: wrap; }
        footer.site a, footer.site p { font-size: 13.5px; color: var(--mute); }
        footer.site a:hover { color: var(--pine); }

        @media (max-width: 860px) {
            .topnav .navlink { display: none; }
            .stats, .cards, .step-grid { grid-template-columns: 1fr; }
            .steps { padding: 40px 28px; }
            .band { padding: 32px 28px; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } html { scroll-behavior: auto; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="wrap">
            <a href="{{ route('home') }}" class="brand">
                <span class="brand-mark">JBG</span>
                <span>Khidmat Nasihat<span class="dot" style="margin-left:6px;"></span></span>
            </a>
            <nav class="topnav">
                @auth
                    <a href="{{ route(auth()->user()->homeRoute()) }}" class="navlink">Ruang Saya</a>
                    <form method="POST" action="{{ route('awam.logout') }}" class="logout-form">
                        @csrf
                        <button type="submit" class="btn btn-primary logout-btn">Log Keluar</button>
                    </form>
                @else
                    <a href="{{ route('awam.login') }}" class="navlink">Khidmat Nasihat</a>
                    <a href="{{ route('awam.daftar') }}" class="navlink">Daftar</a>
                    <a href="{{ route('peguam.daftar') }}" class="navlink">Peguam Panel</a>
                    <a href="{{ route('system.login') }}" class="navlink">Kakitangan</a>
                    <a href="{{ route('awam.login') }}" class="btn btn-primary">Log Masuk</a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="wrap">
                <span class="eyebrow"><span class="dot"></span> Jabatan Bantuan Guaman Malaysia</span>
                <h1>Nasihat guaman <em>percuma</em>, dekat dengan anda.</h1>
                <p class="lead">
                    Mohon khidmat nasihat undang-undang, semak kelayakan dan tempah janji temu di
                    cawangan JBG berhampiran — semuanya dalam talian, tanpa kos.
                </p>
                <div class="hero-cta">
                    @auth
                        <a href="{{ route(auth()->user()->homeRoute()) }}" class="btn btn-primary btn-lg">Pergi ke Ruang Saya &rarr;</a>
                    @else
                        <a href="{{ route('awam.login') }}" class="btn btn-primary btn-lg">Mohon Khidmat Nasihat &rarr;</a>
                        <a href="{{ route('awam.daftar') }}" class="btn btn-ghost btn-lg">Daftar Akaun</a>
                    @endauth
                </div>
                @guest
                    <p class="hero-note">
                        Sudah ada akaun? <a href="{{ route('awam.login') }}">Log masuk dengan No. Kad Pengenalan</a>.
                    </p>
                @endguest

                <div class="stats">
                    <div class="stat"><b>Percuma</b><span>Tiada bayaran untuk nasihat awal</span></div>
                    <div class="stat"><b>Dalam talian</b><span>Mohon &amp; tempah tanpa beratur</span></div>
                    <div class="stat"><b>Peguam panel</b><span>Disokong peguam berdaftar</span></div>
                </div>
            </div>
        </section>

        <section class="block" id="khidmat">
            <div class="wrap">
                <div class="block-head">
                    <span class="kicker">Apa yang anda boleh buat</span>
                    <h2>Satu portal untuk seluruh perjalanan bantuan guaman.</h2>
                    <p>Dari pertanyaan pertama sehingga janji temu disahkan — uruskan semuanya di satu tempat.</p>
                </div>
                <div class="cards">
                    <article class="card">
                        <div class="ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <h3>Khidmat Nasihat</h3>
                        <p>Mohon nasihat undang-undang mengikut kategori kes anda dan dapatkan pandangan daripada pegawai JBG.</p>
                    </article>
                    <article class="card">
                        <div class="ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </div>
                        <h3>Janji Temu</h3>
                        <p>Pilih cawangan, tarikh dan slot masa yang sesuai. Sahkan, jadual semula atau batal bila-bila masa.</p>
                    </article>
                    <article class="card">
                        <div class="ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </div>
                        <h3>Semakan Kelayakan</h3>
                        <p>Saringan ringkas menentukan kelayakan anda sebelum permohonan — jimat masa, tepat sasaran.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="block" id="cara" style="padding-top:0;">
            <div class="wrap">
                <div class="steps">
                    <div class="block-head" style="margin-bottom:32px;">
                        <span class="kicker">Cara mohon</span>
                        <h2>Empat langkah mudah.</h2>
                    </div>
                    <div class="step-grid">
                        <div class="step"><div class="n">1</div><h4>Daftar akaun</h4><p>Guna No. Kad Pengenalan untuk daftar masuk portal.</p></div>
                        <div class="step"><div class="n">2</div><h4>Semak kelayakan</h4><p>Jawab saringan ringkas untuk sahkan kelayakan anda.</p></div>
                        <div class="step"><div class="n">3</div><h4>Tempah janji temu</h4><p>Pilih cawangan dan slot masa yang anda mahu.</p></div>
                        <div class="step"><div class="n">4</div><h4>Dapatkan nasihat</h4><p>Hadir temu janji dan terima pandangan undang-undang.</p></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="block" id="peguam" style="padding-top:0;">
            <div class="wrap">
                <div class="band">
                    <div>
                        <h2>Anda seorang peguam?</h2>
                        <p>Sertai Panel Peguam JBG dan bantu rakyat yang memerlukan. Daftar permohonan anda dalam talian.</p>
                    </div>
                    <a href="{{ route('peguam.daftar') }}" class="btn btn-orange btn-lg">Sertai Panel Peguam &rarr;</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site">
        <div class="wrap">
            <a href="{{ route('home') }}" class="brand">
                <span class="brand-mark">JBG</span>
                <span>Khidmat Nasihat</span>
            </a>
            <nav class="f-links">
                @auth
                    <a href="{{ route(auth()->user()->homeRoute()) }}">Ruang Saya</a>
                    <form method="POST" action="{{ route('awam.logout') }}" class="logout-form">
                        @csrf
                        <button type="submit" class="f-logout">Log Keluar</button>
                    </form>
                @else
                    <a href="{{ route('awam.login') }}">Log Masuk</a>
                    <a href="{{ route('awam.daftar') }}">Daftar</a>
                    <a href="{{ route('peguam.daftar') }}">Peguam Panel</a>
                    <a href="{{ route('system.login') }}">Kakitangan</a>
                @endauth
            </nav>
            <p>&copy; {{ now()->year }} Jabatan Bantuan Guaman Malaysia</p>
        </div>
    </footer>

    {{-- AI@JBG chat widget (server-side proxy → Python microservice). --}}
    @include('partials.chatbot')
</body>
</html>
