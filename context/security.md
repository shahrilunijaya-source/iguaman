# Security — iGuaman 2in1

## Done (in-app)

- **Passwords bcrypt** — unified `users.password` hashed (legacy was plaintext).
- **Forced reset** — all migrated accounts flagged `must_change_password=true`; `ForcePasswordChange` middleware pins them to `/password/change` until they set a new password (current-password required, min 8, must differ). New app-created accounts default false.
- **Active-only login** — `Auth::attempt(['is_active' => true, …])`.
- **RBAC** — `EnsureRole` middleware gates staff vs peguam areas + role-separated actions (endorse=pengarah, decide=admin/koordinator).
- **Security headers** — `SecurityHeaders` middleware on all web responses: X-Content-Type-Options, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy.
- **No hardcoded secrets** in app — config via `.env` (grep-clean).
- **CSRF** — Laravel default on all state-changing forms.

## Pre-prod checklist (ops — do before go-live)

- [ ] **Rotate the leaked email password** hardcoded in legacy `sistem-peguam-panel/config.php` (`aplikasi.jbg@bheuu.gov.my`). It is exposed in legacy source; treat as compromised. Set the new value only in prod `.env` (`MAIL_PASSWORD`).
- [ ] Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, fresh `APP_KEY`, real `DB_*`, `MAIL_*`, `SESSION_SECURE_COOKIE=true`.
- [ ] Confirm every migrated user is flagged (`SELECT COUNT(*) FROM users WHERE must_change_password=0` should equal only intentionally-set accounts).
- [ ] HTTPS + HSTS at the web server / Hostinger (add `Strict-Transport-Security` at edge).
- [ ] Consider rate-limiting login (`throttle`) and a real mail driver for password reset (currently `log` in dev).
- [ ] Review file-upload handling before enabling `uploaded_files` write paths.

## Notes

- Password reset (forgot) uses the Password broker → mail. Dev uses `MAIL_MAILER=log`; set SMTP in prod.
- Legacy `0000-00-00` zero-dates were imported as-is (relaxed sql_mode during ETL only). App runs strict mode.
