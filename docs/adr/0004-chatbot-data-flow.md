# ADR-0004 — Chatbot external data flow + PDPA (CFG-13)

**Status:** Accepted · **Date:** 2026-07 · **Audit ref:** CFG-13 (Codex AUD-016)

## Context

The AI@JBG chat widget posts to a server-side proxy (`ChatbotController@ask` →
`ChatbotClient`), which relays the message to an external AI microservice (a Hugging
Face Space, URL from `config('services.chatbot.url')`). CFG-13 flagged that citizen
input + the user's name were forwarded to a third party with no notice/consent.

## Data flow (what leaves the system)

`ChatbotClient::ask()` sends exactly three fields to the external service:

| Field | Value | Notes |
|---|---|---|
| `message` | the user-typed question | required — it *is* the query |
| `session_id` | a random int stored in the session | conversation threading only; not an account id |
| `user_name` | **`''` (empty)** | CFG-13: no longer forwarded (see below) |

Credentials (basic-auth user/pass) stay server-side; the browser only ever talks to
our proxy, never the external service.

## Decision

1. **Minimise PII (CFG-13):** stop forwarding the user's name. `ChatbotController`
   now sends `user_name = ''` for everyone (the bot types it as a required string, so
   `''` — the existing guest value — is sent). Only the message content and an opaque
   session id leave the system.
2. **Notice (CFG-13):** the widget shows a PDPA notice under the input — "Mesej anda
   diproses oleh perkhidmatan AI luaran. Sila jangan kongsi maklumat peribadi atau
   sulit." — so users know their message is relayed to an external AI service.

## Consequences

- The external service can no longer associate a conversation with a named user; only
  the message text (which the user controls) and a random session id are exposed.
- If richer consent is later required (explicit opt-in, logging of consent), add it at
  the widget-open step. The current notice satisfies the "inform" requirement.
