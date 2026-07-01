# ADR-0001 — Notification strategy (ARCH-07)

**Status:** Accepted · **Date:** 2026-07 · **Audit ref:** ARCH-07

## Context

Notifications are sent two ways today:

- **Inline synchronous mail** — `Mail::to($x)->send(new SomeMail(...))` at the point of
  the state change, best-effort (wrapped in `try/catch`, never rolling back the domain
  action if mail fails). 4 Mailables: `AgihanTransisiMail`, `KesDitawarkanMail`,
  `KesLebihMasaMail`, `PemindahanMasukMail`.
- **One event/listener** — `PemindahanCawanganDimulakan` → `MaklumkanPemindahanMasuk`
  (branch-transfer fan-out).

No `Notification::` usage. The host (Hostinger shared hosting) runs **no queue worker**
(see CFG-01 / PROC-11), so anything `ShouldQueue` would be enqueued and never delivered.

## Decision

1. **Default = inline synchronous `Mail::to(...)->send(...)`** at the state-change site,
   best-effort: catch and `report()` the exception, never roll back the domain action on a
   mail failure. This is the de-facto pattern and the only one that actually delivers on the
   current host.
2. **Events are reserved for genuine cross-cutting fan-out** — one domain action that
   triggers several independent reactions (the branch-transfer case). Do not introduce an
   event/listener for a single straight-line "do X then email Y".
3. **Do NOT make Mailables or listeners `ShouldQueue`** until the host has a running queue
   worker. Queuing mail on a workerless host silently drops it.

## Consequences

- Mail latency is on the request thread. Acceptable: volumes are low and sends are
  best-effort. A slow SMTP server slows the response but never corrupts state.
- **Revisit when CFG-01 is resolved** (a real queue worker / `queue:work` on the host):
  switch the Mailables to `ShouldQueue`, move the best-effort sends behind queued jobs, and
  promote more of the inline sends to events + queued listeners for throughput.
