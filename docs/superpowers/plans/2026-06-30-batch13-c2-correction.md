# Batch 13 Plan — Correction: Task C2 (Feedback) SUPERSEDED

**Date:** 2026-06-30
**Applies to:** `docs/superpowers/plans/2026-06-30-batch13-public-awam-portal.md` — **Task C2** and the Slice C file list.
**Reason:** A concurrent Batch 12 commit (`d6c470c feat(maklumbalas): public post-appointment satisfaction feedback`) already shipped the maklumbalas feature. Building it again in Batch 13 would collide (duplicate migration + model).

> Read THIS file's override before executing Task C2 in the main plan. Where they conflict, this file wins.

---

## What Batch 12 already built (`d6c470c`) — REUSE, do not rebuild

| Artifact | Path |
|---|---|
| Table | `maklum_balas` (migration `2026_06_30_120010_create_maklum_balas_table.php`) — **note: `maklum_balas`, not `maklumbalas`** |
| Model | `app/Models/MaklumBalas.php` + `KhidmatNasihat::maklumBalas()` (HasOne) |
| Controller | `app/Http/Controllers/MaklumBalasController.php` (`show` / `store`) |
| Request | `app/Http/Requests/MaklumBalasRequest.php` (richer survey: `soalan_1` boxes, `lain_lain` required_if, `soalan_2a` enum) |
| Routes | **PUBLIC, no auth**, throttled 6/min, keyed by `no_permohonan`, one-per-KN: `maklum-balas.show` (`GET /maklum-balas/{no_permohonan}`), `maklum-balas.store` (`POST /maklum-balas/{no_permohonan}`) |
| Views | `resources/views/maklum-balas/{layout,borang,terima-kasih,belum-tersedia}.blade.php` |
| Tests | `tests/Feature/Batch12MaklumBalasTest.php` (9 tests) |

Access model: a citizen opens the public feedback link **after a `SELESAI` appointment, without logging in**. This satisfies the Batch 13 spec's "Submit satisfaction feedback" scope item via reuse.

---

## REPLACEMENT for Task C2 — link-only (do this instead)

**Do NOT** create `2026_06_30_130003_create_maklumbalas_table.php`, `app/Models/MaklumBalas.php`,
`app/Http/Controllers/Awam/MaklumBalasController.php`, `app/Http/Requests/Awam/AwamMaklumBalasRequest.php`,
`resources/views/awam/permohonan/maklumbalas.blade.php`, or `tests/Feature/Awam/AwamMaklumBalasTest.php`.
Skip every step in the main plan's Task C2.

Instead:

**Files:**
- Extend: `resources/views/awam/permohonan/show.blade.php`
- Test: `tests/Feature/Awam/AwamLifecycleTest.php` (add one case)

- [ ] **Step 1: Add a failing test** — the citizen show page links to the public feedback page when the KN is `SELESAI`.

```php
    public function test_show_links_to_feedback_when_selesai(): void
    {
        $u = User::factory()->create(['user_type' => 'awam']);
        $u->assignRole('awam');
        $kn = KhidmatNasihat::factory()->create([
            'id_pengguna' => $u->id, 'status_kn' => KhidmatNasihat::STATUS_SELESAI,
        ]);

        $this->actingAs($u)->get("/awam/permohonan/{$kn->id}")
            ->assertOk()
            ->assertSee(route('maklum-balas.show', $kn->no_permohonan));
    }
```

- [ ] **Step 2: Run, verify FAIL.** `php artisan test --filter=test_show_links_to_feedback_when_selesai`

- [ ] **Step 3: Add the link** to `resources/views/awam/permohonan/show.blade.php` (only when `SELESAI`):

```blade
@if ($khidmat->status_kn === \App\Models\KhidmatNasihat::STATUS_SELESAI)
    <a class="awam-btn" href="{{ route('maklum-balas.show', $khidmat->no_permohonan) }}">Beri Maklum Balas</a>
@endif
```

- [ ] **Step 4: Run, verify PASS, then commit.**

```bash
git add resources/views/awam/permohonan/show.blade.php tests/Feature/Awam/AwamLifecycleTest.php
git commit -m "feat(awam): link citizen portal to batch-12 public feedback (reuse maklum_balas)"
```

---

## Also update during execution
- **Slice C file list** (main plan, line ~43): ignore the three `maklumbalas` create lines + `AwamMaklumBalasTest`.
- **Self-review line** "feedback (C2)": now reads "feedback (C2 — reuses batch-12 `maklum_balas`, link only)".
- **Branch hygiene:** the citizen portal's authed `/awam/...` feedback route is intentionally NOT added — feedback stays on the public `maklum-balas.*` routes.
