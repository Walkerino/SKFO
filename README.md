# SKFO
SKFO.RU is a unique resource for travelers and tourists who want to visit one of the most beautiful and unique regions in the world - the North Caucasus.

## SMTP for Real OTP Emails

The project now supports direct SMTP sending for auth codes (`email + one-time code`).

1. Pick a provider template from `.ddev/`:
- `.ddev/config.smtp.mailru.yaml.example`
- `.ddev/config.smtp.yandex.yaml.example`
- `.ddev/config.smtp.gmail.yaml.example`
- `.ddev/config.smtp.sendgrid.yaml.example`

2. Copy one of them to `.ddev/config.smtp.local.yaml`.

3. Fill your real SMTP credentials (`SKFO_SMTP_USER`, `SKFO_SMTP_PASS`, `SKFO_SMTP_FROM_EMAIL`).

4. Restart ddev:
```bash
ddev restart
```

Notes:
- For Mail.ru, Yandex and Gmail use an **app password**, not your account password.
- If SMTP is not configured, local ddev mail goes to Mailpit:
  - `http://skfo.ddev.site:8025` (HTTP)
  - `https://skfo.ddev.site:8026` may be unavailable in some local setups.
