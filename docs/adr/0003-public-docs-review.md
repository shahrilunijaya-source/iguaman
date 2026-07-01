# ADR-0003 — Public documentation pages review (CFG-14)

**Status:** Accepted · **Date:** 2026-07 · **Audit ref:** CFG-14 (Codex AUD-022)

## Context

Two documentation pages are deliberately public (root `.htaccess` carve-out +
`routes/web.php` `docs.overview` / `docs.penambahbaikan`):

- `docs/penambahbaikan-22.html` — the 22-wish improvement roadmap / status.
- `docs/system-overview.html` — a system architecture overview.

The rest of `docs/` (audit reports, design tokens, spikes) stays blocked by the
`.htaccess` `[F]` rule. CFG-14 asked us to confirm the two public pages are
non-sensitive, or gate them behind auth.

## Findings

Scanned both files for secrets / credentials / PII (passwords, API keys, tokens,
`APP_KEY`, SMTP creds, DB URLs, IC numbers, `u######_` cPanel prefixes, long hex
blobs): **none found.** No data-protection exposure.

`penambahbaikan-22.html` is a benign roadmap/status page.

`system-overview.html` describes internal **architecture** detail — auth strategy
(`Auth::attempt`, bcrypt, session-based), middleware names, and some table/column
names. No secrets, but it carries mild **reconnaissance** value.

## Decision

1. **Both pages are confirmed free of secrets / credentials / PII** and may remain
   public (they were published deliberately for stakeholder sharing, commit `0438624`).
2. **Residual, accepted:** `system-overview.html` exposes architecture/schema detail.
   This is low risk (no secrets) and accepted for now.

## Consequences / follow-up (optional)

- If policy later requires hiding the architecture detail, gate `system-overview.html`
  behind auth: remove its `.htaccess` carve-out (so the `docs/` `[F]` rule blocks direct
  access) **and** move serving to an `auth`-middleware Laravel route. This is a
  deployment-coupled change (Apache + route) — deferred deliberately since no secrets
  justify the deploy risk today.
- Keep the `docs/` `[F]` block for everything except these two reviewed files; any new
  public doc must be reviewed the same way before being carved out.
