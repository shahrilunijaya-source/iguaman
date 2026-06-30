{{-- AI@JBG chat widget. Self-contained (scoped CSS + vanilla JS), talks only to
     the server-side proxy (route chatbot.ask). Drop @include('partials.chatbot')
     before </body> on any public page. Brand: teal #00B8A9 / pine #003D3A. --}}
<div id="jbgcb" data-endpoint="{{ route('chatbot.ask') }}" data-token="{{ csrf_token() }}">
    <button type="button" class="jbgcb-fab" aria-label="Buka chatbot AI@JBG" aria-expanded="false">
        <span class="jbgcb-fab-icon" aria-hidden="true">&#128172;</span>
    </button>

    <section class="jbgcb-panel" role="dialog" aria-label="AI@JBG chatbot" hidden>
        <header class="jbgcb-head">
            <div>
                <strong>AI@JBG</strong>
                <span class="jbgcb-sub">Pembantu Bantuan Guaman</span>
            </div>
            <button type="button" class="jbgcb-close" aria-label="Tutup">&times;</button>
        </header>

        <div class="jbgcb-log" aria-live="polite">
            <div class="jbgcb-msg jbgcb-bot">Salam Malaysia MADANI. Saya AI@JBG. Boleh saya bantu anda?</div>
        </div>

        <form class="jbgcb-form" autocomplete="off">
            <input type="text" class="jbgcb-input" name="message" maxlength="1000"
                   placeholder="Taip soalan anda…" aria-label="Mesej" required>
            <button type="submit" class="jbgcb-send" aria-label="Hantar">&#10148;</button>
        </form>
    </section>
</div>

<style>
    #jbgcb { --jbgcb-teal:#00B8A9; --jbgcb-pine:#003D3A; position:fixed; right:1.25rem; bottom:1.25rem; z-index:9999; font-family:ui-sans-serif,system-ui,sans-serif; }
    #jbgcb *, #jbgcb *::before, #jbgcb *::after { box-sizing:border-box; }
    .jbgcb-fab { width:3.5rem; height:3.5rem; border-radius:9999px; border:0; cursor:pointer; background:var(--jbgcb-teal); color:#fff; box-shadow:0 6px 20px rgba(0,61,58,.35); font-size:1.5rem; line-height:1; transition:transform .15s ease, box-shadow .15s ease; }
    .jbgcb-fab:hover { transform:translateY(-2px); box-shadow:0 10px 26px rgba(0,61,58,.45); }
    .jbgcb-fab:focus-visible { outline:3px solid var(--jbgcb-pine); outline-offset:2px; }
    .jbgcb-panel { position:absolute; right:0; bottom:4.5rem; width:min(22rem,calc(100vw - 2.5rem)); height:min(30rem,70vh); background:#fff; border-radius:1rem; box-shadow:0 20px 50px rgba(0,61,58,.3); display:flex; flex-direction:column; overflow:hidden; border:1px solid rgba(0,61,58,.12); }
    /* The [hidden] attribute toggles visibility; without this rule the
       display:flex above would override the UA [hidden]{display:none} and
       the panel could never be hidden (close button looked dead). */
    .jbgcb-panel[hidden] { display:none; }
    .jbgcb-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding:.85rem 1rem; background:var(--jbgcb-pine); color:#fff; }
    .jbgcb-head strong { display:block; font-size:.95rem; }
    .jbgcb-sub { font-size:.7rem; opacity:.8; }
    .jbgcb-close { display:grid; place-items:center; width:2rem; height:2rem; flex-shrink:0; background:transparent; border:0; border-radius:.5rem; color:#fff; font-size:1.5rem; line-height:1; cursor:pointer; transition:background .15s; }
    .jbgcb-close:hover { background:rgba(255,255,255,.14); }
    .jbgcb-close:focus-visible { outline:2px solid var(--jbgcb-teal); outline-offset:2px; }
    .jbgcb-log { flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:.6rem; background:#f6f9f9; }
    .jbgcb-msg { max-width:85%; padding:.55rem .8rem; border-radius:.85rem; font-size:.85rem; line-height:1.4; white-space:pre-wrap; word-wrap:break-word; }
    .jbgcb-bot { align-self:flex-start; background:#fff; color:#0f2e2c; border:1px solid rgba(0,61,58,.12); border-bottom-left-radius:.2rem; }
    .jbgcb-user { align-self:flex-end; background:var(--jbgcb-teal); color:#fff; border-bottom-right-radius:.2rem; }
    .jbgcb-msg.jbgcb-pending { opacity:.6; font-style:italic; }
    .jbgcb-form { display:flex; gap:.4rem; padding:.6rem; border-top:1px solid rgba(0,61,58,.1); background:#fff; }
    .jbgcb-input { flex:1; border:1px solid rgba(0,61,58,.2); border-radius:.6rem; padding:.55rem .7rem; font-size:.85rem; }
    .jbgcb-input:focus { outline:2px solid var(--jbgcb-teal); border-color:transparent; }
    .jbgcb-send { border:0; border-radius:.6rem; background:var(--jbgcb-teal); color:#fff; padding:0 .9rem; font-size:1rem; cursor:pointer; }
    .jbgcb-send:disabled { opacity:.5; cursor:not-allowed; }
    @media (prefers-reduced-motion:reduce) { .jbgcb-fab { transition:none; } }
</style>

<script>
(function () {
    var root = document.getElementById('jbgcb');
    if (!root) return;

    var endpoint = root.dataset.endpoint;
    var token = root.dataset.token;
    var fab = root.querySelector('.jbgcb-fab');
    var panel = root.querySelector('.jbgcb-panel');
    var closeBtn = root.querySelector('.jbgcb-close');
    var log = root.querySelector('.jbgcb-log');
    var form = root.querySelector('.jbgcb-form');
    var input = root.querySelector('.jbgcb-input');
    var sendBtn = root.querySelector('.jbgcb-send');
    var busy = false;

    function togglePanel(open) {
        panel.hidden = !open;
        fab.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) input.focus();
    }

    function addMsg(text, who, pending) {
        var el = document.createElement('div');
        el.className = 'jbgcb-msg ' + (who === 'user' ? 'jbgcb-user' : 'jbgcb-bot');
        if (pending) el.classList.add('jbgcb-pending');
        el.textContent = text;
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
        return el;
    }

    fab.addEventListener('click', function () { togglePanel(panel.hidden); });
    closeBtn.addEventListener('click', function () { togglePanel(false); fab.focus(); });

    // Esc closes the panel when it is open.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { togglePanel(false); fab.focus(); }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (busy) return;
        var text = input.value.trim();
        if (!text) return;

        addMsg(text, 'user');
        input.value = '';
        busy = true;
        sendBtn.disabled = true;
        var pending = addMsg('Menaip…', 'bot', true);

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ message: text })
        })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (data) {
            pending.classList.remove('jbgcb-pending');
            pending.textContent = (data && data.reply) ? data.reply : 'Maaf, berlaku ralat. Sila cuba lagi.';
        })
        .catch(function () {
            pending.classList.remove('jbgcb-pending');
            pending.textContent = 'Maaf, sambungan gagal. Sila cuba lagi.';
        })
        .finally(function () {
            busy = false;
            sendBtn.disabled = false;
            log.scrollTop = log.scrollHeight;
            input.focus();
        });
    });
})();
</script>
