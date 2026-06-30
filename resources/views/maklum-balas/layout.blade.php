<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Maklum Balas') — JBG</title>
    <style>
        /* Minimal self-contained public surface (no public layout yet — batch 13).
           First public page in the app, so it carries its own brand styling rather
           than the staff shell. Brand tokens mirror theme.css (teal #1a6fa8 /
           pine #0d2e48) as CSS-var fallbacks, so the page renders standalone and
           still inherits theme.css overrides if a brand stylesheet is loaded. */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: var(--font-sans, 'Inter', system-ui, sans-serif);
            color: var(--ink-navy, #0d2e48);
            background:
                radial-gradient(1200px 600px at 100% -10%, var(--teal-50, #e0f2fc), transparent 60%),
                #f4f7f9;
            line-height: 1.55;
        }
        .mb-shell { min-height: 100vh; display: flex; align-items: flex-start; justify-content: center; padding: clamp(1.5rem, 4vw, 4rem) 1rem; }
        .mb-card {
            width: 100%; max-width: 640px; background: #fff; border-radius: 16px;
            box-shadow: 0 12px 40px rgba(var(--pine-rgb, 13,46,72), 0.10);
            border: 1px solid rgba(var(--pine-rgb, 13,46,72), 0.06);
            overflow: hidden;
        }
        .mb-head {
            background: linear-gradient(135deg, var(--pine, #0d2e48), var(--teal-700, #124070));
            color: #fff; padding: 1.75rem clamp(1.25rem, 4vw, 2.25rem);
        }
        .mb-eyebrow { font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; opacity: .82; margin: 0 0 .35rem; }
        .mb-head h1 { margin: 0; font-size: clamp(1.35rem, 1rem + 1.6vw, 1.75rem); font-weight: 700; }
        .mb-ref { margin: .5rem 0 0; font-size: .9rem; opacity: .9; }
        .mb-body { padding: clamp(1.25rem, 4vw, 2.25rem); }
        .mb-body p { margin: 0 0 1rem; }
        fieldset { border: 1px solid rgba(var(--pine-rgb, 13,46,72), 0.14); border-radius: 12px; padding: 1rem 1.15rem 1.15rem; margin: 0 0 1.5rem; }
        legend { font-weight: 700; padding: 0 .5rem; color: var(--pine, #0d2e48); }
        .mb-check, .mb-radio { display: flex; align-items: flex-start; gap: .6rem; padding: .4rem 0; }
        .mb-check input, .mb-radio input { margin-top: .2rem; width: 1.05rem; height: 1.05rem; accent-color: var(--teal, #1a6fa8); }
        label { cursor: pointer; }
        .mb-field { margin-top: .75rem; }
        .mb-field label.mb-label { display: block; font-weight: 600; margin-bottom: .35rem; }
        input[type="text"], textarea {
            width: 100%; padding: .65rem .8rem; border: 1px solid rgba(var(--pine-rgb, 13,46,72), 0.22);
            border-radius: 9px; font: inherit; color: inherit; background: #fff;
        }
        input[type="text"]:focus, textarea:focus { outline: 2px solid var(--teal, #1a6fa8); outline-offset: 1px; border-color: var(--teal, #1a6fa8); }
        textarea { min-height: 96px; resize: vertical; }
        .mb-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            background: var(--teal, #1a6fa8); color: #fff; border: 0; border-radius: 10px;
            font-weight: 700; font-size: 1rem; padding: .8rem 1.6rem; cursor: pointer;
            transition: background var(--duration-fast, 150ms) ease;
        }
        .mb-btn:hover { background: var(--teal-600, #155d8f); }
        .mb-errors { background: var(--orange-50, #fde8d8); border: 1px solid rgba(var(--orange-rgb, 224,112,48), 0.4); color: #8a2c0c; border-radius: 10px; padding: .85rem 1rem; margin: 0 0 1.5rem; }
        .mb-errors ul { margin: .4rem 0 0; padding-left: 1.1rem; }
        .mb-notis { background: var(--teal-50, #e0f2fc); border: 1px solid rgba(var(--brand-rgb, 26,111,168), 0.4); border-radius: 10px; padding: .85rem 1rem; margin: 0 0 1.5rem; color: var(--teal-700, #124070); }
        .mb-state-icon { font-size: 2.4rem; line-height: 1; margin-bottom: .25rem; }
        .mb-foot { text-align: center; font-size: .78rem; color: rgba(var(--pine-rgb, 13,46,72), 0.55); padding: 1.1rem; }
        .req { color: var(--orange, #e07030); }
    </style>
</head>
<body>
    <div class="mb-shell">
        <main class="mb-card">
            <header class="mb-head">
                <p class="mb-eyebrow">Jabatan Bantuan Guaman</p>
                <h1>@yield('heading', 'Maklum Balas Perkhidmatan')</h1>
                @hasSection('ref')
                    <p class="mb-ref">@yield('ref')</p>
                @endif
            </header>
            <div class="mb-body">
                @yield('content')
            </div>
            <footer class="mb-foot">© {{ date('Y') }} Jabatan Bantuan Guaman</footer>
        </main>
    </div>
</body>
</html>
