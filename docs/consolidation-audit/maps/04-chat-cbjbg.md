# Consolidation Audit Map 04 — CHAT system "cbjbg" (AI@JBG chatbot)

> READ-ONLY audit. Source: `c:/Users/User/Desktop/Claude/ClaudeCode/Aril/Project/Maintainence/iGuaman/cbjbg`
> Integration consumer: 2in1 Laravel app (`ChatbotController` + landing widget).
> Date: 2026-06-30. Auditor: System Analyst / Solution Architect.

---

## 1. What the bot is

**AI@JBG** — a standalone Python **FastAPI** microservice that wraps a **LangChain tool-calling agent** over **OpenAI GPT-4o**, with **FAISS** retrieval (RAG) over JBG (Jabatan Bantuan Guaman / Legal Aid Department) legal-aid documents, plus several live-scrape / web-search tools. It is a **public-information Q&A assistant** scoped strictly to JBG, legal aid, and the JBG staff directory. It does **NOT** touch the 2in1 system's records, users, or permissions.

- Active source file: **`main-with-cors.py`** (33.8 KB) — the hardened, deployed version.
- Superseded/reference file: **`main-commented-hero-serpapi-jwt.py`** (45 KB) — pre-hardening, **hardcoded secrets**, gitignored + dockerignored, never deployed. Has two extra tools (`fetch_kiraan_data` salary-deduction calculator, `search_guideline_documents`) and a JDN/corporate variant. NOT used by 2in1.
- Deploy target: **Hugging Face Spaces** (Docker SDK, `app_port: 7860`). Live URL configured in 2in1: `https://shahrilunijaya-iguaman-jbg-bot.hf.space`.

Despite a `from flask import ...` line (line 12), Flask is **unused dead import** — the app is pure FastAPI/uvicorn.

---

## 2. API endpoints (request/response shape)

Two endpoints only. Both defined in `main-with-cors.py`.

### `POST /generate_token` — HTTP Basic → JWT
- Auth: HTTP Basic, creds `BOT_API_USER` / `BOT_API_PASS` (env). Constant-time compare via `secrets.compare_digest`.
- Returns: `{ "access_token": "<JWT>" }`. JWT is HS256, payload `{user_id, iat, exp}`, TTL `JWT_TTL_MIN` (default 60 min).
- Boots refuse if `JWT_SECRET` missing or `< 32` chars (`RuntimeError`).

### `POST /forward_message` — Bearer JWT
- Auth: `Authorization: Bearer <JWT>` (verified by `verify_jwt_token`).
- Request body (Pydantic `MessageRequest`):
  ```json
  { "message": "string", "session_id": <int>, "user_name": "string|optional" }
  ```
  - `session_id` is an **int** and is the conversation-thread key (in-memory history).
  - `user_name` typed `str` — sending `null` triggers a **422**; the Laravel proxy sends `''` for guests.
- Response (`forward_message` success):
  ```json
  { "http_code": 200, "http_error": "", "content_raw": "<bot reply text>", "http_data": { "url","method","headers","request_body" } }
  ```
  - **Leak note:** `http_data.headers` echoes the **full inbound request headers** (incl. the `Authorization: Bearer` JWT) straight back in the response body. Harmless today because the Laravel proxy only forwards `content_raw`, but it's needless reflected exposure.
- Error response shape: `{ "http_code", "http_error", "content_raw": "<Malay apology>" }`.

### Implicit/unintended exposure
- FastAPI default **`/docs`, `/redoc`, `/openapi.json` are open and unauthenticated** (confirmed in `boot.log`: `GET /docs 200`, `GET /openapi.json 200`). Endpoint schema is publicly enumerable.

---

## 3. The agent + tools (what it actually does)

LLM: `ChatOpenAI(temperature=0.7, model="gpt-4o")`, bound to 8 tools. `run_agent()` is a hand-rolled tool-calling loop (max 3 iterations). System prompt locks scope to JBG/legal-aid, forces a "Salam Malaysia MADANI…" greeting on first turn, caps replies at 150 words, refuses creative/coding/meta requests.

| Tool | Purpose | Data source / external dependency |
|------|---------|-----------------------------------|
| `fetch_jbg_portal_info` | history/vision/eligibility/procedures/fees/leadership/branches | **Live HTTP scrape** of `https://www.jbg.gov.my` (BeautifulSoup, keyword→URL routing over ~16 hardcoded portal URLs, 8s timeout). On failure → `_portal_docs_fallback()` → FAISS. |
| `search_documents` | detailed legal procedures (sivil/syariah/jenayah), corporate guidelines | **FAISS** `similarity_search(k=8)` over embedded PDFs |
| `get_contact_info` | staff/minister/VIP directory lookup | **CSV** `jbg_staff_directory.csv` + fuzzywuzzy fuzzy match (threshold 40, top 5) |
| `qamus` | Malay dictionary | **Live scrape** of DBP PRPM `prpm.dbp.gov.my` (only if user says "DBP"/"PRPM"/"kamus") |
| `google_search` | "this week" web results | **SerpAPI** (`SERPAPI_KEY`), Google engine, gl=my, qdr:w, top 3 |
| `news_today` | today's news row | **MySQL** `chatbot.search_results` (DB on JBG internal net `10.19.206.132`) |
| `get_current_time` | current datetime | local |
| `team_credit` | dev-team credit string | static literal |

**RAG yes** (FAISS over PDFs + staff CSV). **Web search yes** (SerpAPI + portal/DBP scrape). **Auth yes** (Basic→JWT).

---

## 4. Data sources

- **FAISS index** (`faiss_index/index.faiss`, `index.pkl`) — prebuilt, baked into image, loaded read-only at boot with `allow_dangerous_deserialization=True` (acceptable since index is self-built, but worth noting — pickle deserialization). On-disk in repo these are **Git LFS pointers** (132 bytes each; real index ≈ 6.26 MB per pointer `oid`). LFS resolution required for a working clone/deploy.
- **Knowledge-base PDFs** (LFS-tracked) under `knowledge-base/`:
  - `JBG-corporate/` — Akta Bantuan Guaman 1971 + 2017 pindaan, PUA 128/129 2023, Peraturan Bantuan Guaman, directory, etc.
  - `jenayah/`, `sivil/`, `syariah/` — citizen-facing legal procedure PDFs.
  - `JDN-corporate/` present but **NOT loaded** by `main-with-cors.py` (only corporate/jenayah/syariah/sivil folders are embedded). Leftover from JDN variant.
- **CSVs:** `jbg_staff_directory.csv` (5 officers incl. KP Dato Norazmi — columns NAMA PEGAWAI, JAWATAN, BAHAGIAN, SEKSYEN/TREK, TELEFON, EMEL, LOKASI PEGAWAI); `jbg_general_info.csv` (1 row, website URL — effectively unused).
- **MySQL `chatbot` DB** (`news_today` only) — host `10.19.206.132` on **JBG internal network**, unreachable from HF Spaces; that tool is effectively dead in cloud deploy (README says leave blank).

---

## 5. Auth model

- **Inbound to bot:** two-step — Basic auth (`BOT_API_USER`/`BOT_API_PASS`) to mint a 60-min JWT, then Bearer JWT on `/forward_message`. The JWT `user_id` is just the basic-auth username (`adlajklld@1`), **not** a 2in1 user identity — there is **no per-end-user identity** passed in any auth-bearing way. `user_name` is an unauthenticated free-text hint used only for greeting personalization.
- **No authorization / RBAC.** Any caller with the shared Basic creds gets identical full access. The bot has **no concept of 2in1 roles, permissions, or record ownership**.

---

## 6. Integration with 2in1 (current state)

Pattern: **server-side proxy + self-contained browser widget**. The bot's creds/JWT never reach the browser.

- **Config:** `config/services.php` → `services.chatbot` = `{ url: BOT_API_URL, user: BOT_API_USER, pass: BOT_API_PASS, timeout: BOT_API_TIMEOUT(30) }`. 2in1 `.env` lines 67–71 hold the live HF URL + matching creds.
- **Proxy controller:** `app/Http/Controllers/ChatbotController.php::ask()`
  - Validates `message` (`required|string|max:1000`).
  - Mints/stores a stable per-session `chatbot_sid` (`random_int(100000, 2147483647)`) in the Laravel session → bot `session_id`.
  - Calls `POST {base}/generate_token` (Basic) → `POST {base}/forward_message` (Bearer), forwarding `message`, `session_id`, and `user_name = auth()->user()?->name ?? ''`.
  - Returns `{ reply: <content_raw> }`. Degrades gracefully: 503 (unconfigured), 502 (token/message/transport failure), Malay user-facing messages, warns/errors to `Log`.
- **Route:** `routes/web.php:57` — `POST /chatbot/ask` → `ChatbotController@ask`, **`throttle:20,1`** (20/min), name `chatbot.ask`. Public (no auth middleware).
- **Widget:** `resources/views/partials/chatbot.blade.php` — self-contained floating FAB + dialog, scoped CSS (teal `#00B8A9` / pine `#003D3A`), vanilla JS `fetch` to `route('chatbot.ask')` with CSRF token (`X-CSRF-TOKEN` + `X-Requested-With`). Included **only on `welcome.blade.php`** (public landing page).

**Net:** integration is one-directional and read-only — 2in1 → bot for Q&A text. The bot returns plain text. No webhooks, no callbacks, no DB sharing, no 2in1 record access.

---

## 7. Does it have access to system records / permissions today?

**No.** Confirmed:
- cbjbg's only DB is the separate `chatbot` DB on JBG's internal net (`news_today` tool), **not** 2in1's `iguaman_2in1` MySQL DB.
- No reference to any 2in1 table/model (permohonan, janji_temu, peguam, awam, users, kes_, ref_) anywhere in `main-with-cors.py`.
- 2in1 only ever sends a free-text `message`, an opaque `session_id`, and the logged-in user's display `name`. The bot cannot read or write 2in1 data, and respects no 2in1 permission model.

---

## 8. Security exposure

| Sev | Finding |
|-----|---------|
| **CRITICAL** | **Live secrets sit in plaintext on disk** at `cbjbg/.env`: real `OPENAI_API_KEY` (`sk-proj-…`), `JWT_SECRET`, `BOT_API_USER`/`PASS`, `DB_PASSWORD` (`JBGAbcd@357`), `SERPAPI_KEY`. The file's own header says the OpenAI key "was exposed in plaintext" and must be **ROTATED** before production — treat all five as compromised and rotate. (`.env` is gitignored and was **never committed** — verified via `git log --all -- .env` empty — so exposure is local-disk, not repo.) |
| **HIGH** | **Shared static Basic creds = the only gate.** `BOT_API_USER`/`PASS` are identical in cbjbg `.env` and 2in1 `.env`. Anyone with them mints JWTs at will; no per-user identity, no rotation, no IP allowlist. Same creds duplicated across two repos. |
| **HIGH** | Pre-hardening file `main-commented-hero-serpapi-jwt.py` contains **hardcoded secrets in source** — OpenAI key path, SerpAPI key `2806bd…`, MySQL password `JBGAbcd@357`, Basic creds, and a **14-char JWT_SECRET `AI@JDNCh4tB0T!`**. Gitignored + dockerignored, but present on disk. **`boot.log` shows the service was actually run with that 14-byte HMAC key** (repeated `InsecureKeyLengthWarning: HMAC key is 14 bytes`), i.e. the weak secret was used at least in a prior/dev run before the ≥32 guard. |
| **MEDIUM** | **Open Swagger:** `/docs`, `/redoc`, `/openapi.json` are publicly served unauthenticated (confirmed in `boot.log`). Leaks endpoint/schema surface. |
| **MEDIUM** | **`/forward_message` reflects full inbound request headers** (incl. the Bearer JWT) back in `http_data.headers`. Reflected token exposure; not consumed by the proxy but should not be returned. |
| **MEDIUM** | **No auth on the heavy/paid endpoint at the bot layer beyond the shared JWT.** OpenAI + SerpAPI cost is gated only by 2in1's `throttle:20,1` per-IP. A direct caller with Basic creds bypasses that throttle entirely (the bot has no rate limit of its own). |
| **LOW** | CORS in `main-with-cors.py` is **correctly locked** (env-driven `CORS_ORIGINS`, defaults to localhost, explicitly avoids `*`, `allow_credentials=True`) — this is the *fixed* state vs. the prompt's concern about "open CORS". Note `CORS_ORIGINS` in the on-disk `.env` is still `http://localhost:8000` (dev), so the deployed Space must set the real 2in1 origin or browser-direct calls fail (they don't today since traffic is server-side proxied, making CORS largely moot for the proxy path). |
| **LOW** | FAISS loaded with `allow_dangerous_deserialization=True` (pickle). Safe only because the index is self-built and baked into the image; becomes a risk if the index file is ever sourced from an untrusted location. |
| **LOW** | OpenAI dependency = **data egress**: every user question (and `user_name`) is sent to OpenAI's API. For a govt legal-aid context this is a data-residency/PII consideration, though questions are public-info-oriented. |

---

## 9. Limitations

- **Ephemeral, in-process memory.** `user_conversations` is a plain dict in RAM keyed by `session_id` (last 10 turns). **Lost on every restart**, and **not shared across replicas** — horizontal scaling breaks conversation continuity. HF Spaces sleep/restart wipes history.
- **`news_today` tool is dead in cloud** (DB on JBG internal net, unreachable from HF Spaces).
- **`fetch_jbg_portal_info` is fragile** — depends on live `jbg.gov.my` HTML structure (hardcoded CSS selectors + URL map). Layout changes silently degrade it to the FAISS fallback.
- **Knowledge base is small / static** — must rebuild & re-bake the FAISS index to update content (delete `faiss_index/` to force re-embed, costs OpenAI embedding calls). Staff directory is only 5 officers.
- **Single shared identity** — bot cannot personalize by 2in1 role/case, cannot answer "status of *my* application", because it has zero access to 2in1 records. Purely generic JBG info.
- **No streaming** — replies are returned whole; widget shows a "Menaip…" placeholder then swaps in the full text.
- **No tests, no CI** — repo has no test suite; only `boot.log`/`chatbot.log` runtime traces.
- **Vendor lock to OpenAI GPT-4o + SerpAPI** — both metered/paid; no fallback model.

---

## 10. Consolidation implications (for the merge)

- The chatbot is **cleanly decoupled** — it is the easiest subsystem to keep as-is: a microservice behind one Laravel proxy + one Blade partial. No DB entanglement with 2in1.
- **Keep-as-microservice** is the right call (matches the memory note "Integrate cbjbg chatbot — kept as microservice + Laravel proxy/widget, not rebuilt"). Consolidation work needed is operational, not architectural:
  1. **Rotate all five secrets** (OpenAI, JWT, Basic user/pass, SerpAPI, DB password) — they're plaintext on disk and one is self-declared exposed.
  2. **Move 2in1's `BOT_API_*` to real env/secret store**, stop duplicating creds across repos.
  3. **Disable `/docs` & `/redoc`** in production (or auth-gate them).
  4. **Stop reflecting request headers** in `/forward_message` response.
  5. **Set `CORS_ORIGINS`** to the real 2in1 origin on the deployed Space.
  6. Decide whether to surface the widget beyond the public landing page (currently only `welcome.blade.php`); if exposed to authenticated officers, consider passing a 2in1-issued identity if the bot ever needs record context (today it needs none).
  7. Persist conversation memory (Redis/DB) if multi-replica or restart-resilient history is wanted.
- **No record/permission bridge exists or is needed today** — if the consolidated system ever wants "ask about my case", that's net-new integration (the bot would need scoped, authorized read access to 2in1 data — not present now).

---

## Evidence index (files read)

- `cbjbg/main-with-cors.py` (active backend, full read)
- `cbjbg/main-commented-hero-serpapi-jwt.py` (pre-hardening reference — grep-scanned)
- `cbjbg/.env`, `.env.example`, `requirements.txt`, `Dockerfile`, `.gitignore`, `.dockerignore`, `.gitattributes`, `README.md`
- `cbjbg/knowledge-base/jbg_staff_directory.csv`, `jbg_general_info.csv`; dir listing of PDF folders; `faiss_index/` (LFS pointers)
- `cbjbg/boot.log` (runtime evidence: open /docs, 14-byte HMAC warnings, live /forward_message calls)
- `cbjbg` git: log (3 commits), tracked files, `.env` history (never committed)
- 2in1: `app/Http/Controllers/ChatbotController.php`, `resources/views/partials/chatbot.blade.php`, `routes/web.php` (lines 50–62), `config/services.php` (chatbot block), `.env` (lines 67–71), widget include sites (`welcome.blade.php`)
