# Domain Facts

> Facts Claude can't derive from the code. Keep short. Update as you learn.

## Background

- **iGuaman 2in1** = combine two legacy raw-PHP systems into one Laravel app: `sistem-peguam-panel` (lawyer panel / case assignment) + `sistem-rekod-kes` (case records / mediation / court / statistics).
- Domain: Malaysian legal aid — **JBG (Jabatan Bantuan Guaman) / BHEUU**.
- **Both source systems already share ONE database: `sistemspk`** (MariaDB). They are two front-ends on one schema → merge is natural.
- Full merge analysis: [[2in1-merge-plan]] (`context/2in1-merge-plan.md`).

## Key Terms

| Term | Meaning |
|------|---------|
| Bantuan Guaman | Legal aid |
| Peguam Panel | Panel lawyer (external, assigned cases) |
| OYD (Orang Yang Dibantu) | Assisted person / beneficiary / applicant |
| Permohonan | Application (legal aid intake, 5-stage: peringkat 1–5) |
| Agihan | Case assignment to lawyer (baru/semasa/semula = new/current/reassign) |
| Pengantaraan | Mediation |
| Kes Mahkamah | Court case (sivil = civil, syariah) |
| Sidang | Hearing/session |
| Cawangan | Branch · Pegawai = officer · Pengarah = director |
| `forms` | Main case/application table (78 cols) |

## Gotchas / Non-Obvious Rules

- **Plaintext passwords** in both legacy systems — bcrypt on migration, force reset at launch.
- **Hardcoded secrets**: `sistem-peguam-panel/config.php` has email password in source; DB creds in source. Rotate + move to `.env`.
- 3 separate user tables (`users`, `users_peguam_panel_2`, `_3`) → must unify to one `users` + roles.
- Legacy schema has NO foreign keys — relationships are implicit via matching id/id_kes/kp columns. Validate before modeling.
- Agents suggested Filament/Nova — **rejected** (Shahril rule: plain Laravel + Blade only).

## Related
[[project]]
