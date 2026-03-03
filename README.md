# SKFO

SKFO.RU is a ProcessWire-based travel portal focused on the North Caucasus.

## Stack

- ProcessWire CMS (custom templates in `public/site/templates`)
- PHP 8.2
- DDEV (nginx-fpm + MariaDB 11.8)

## Quick Start (Local)

1. Start containers:
```bash
ddev start
```

2. Open project URLs:
- Site: `https://skfo.ddev.site`
- ProcessWire admin: `https://skfo.ddev.site/processwire/`
- Content Center: `https://skfo.ddev.site/content-admin/`

3. Useful pages:
- Hotels list: `https://skfo.ddev.site/hotels/`
- Hotel details: `https://skfo.ddev.site/hotel/<slug>/`

## Content Management

The custom Content Center (`/content-admin/`) is used for operational editing of:
- tours
- hotels
- articles
- places
- hotel placements on `/hotels/`

Core editing still works via ProcessWire admin (`/processwire/`).

## SMTP for OTP Emails

The project supports SMTP delivery for one-time auth codes (`email + OTP`).

1. Pick a provider template from `.ddev/`:
- `.ddev/config.smtp.mailru.yaml.example`
- `.ddev/config.smtp.yandex.yaml.example`
- `.ddev/config.smtp.gmail.yaml.example`
- `.ddev/config.smtp.sendgrid.yaml.example`

2. Copy one to `.ddev/config.smtp.local.yaml`.

3. Fill credentials:
- `SKFO_SMTP_USER`
- `SKFO_SMTP_PASS`
- `SKFO_SMTP_FROM_EMAIL`
- `SKFO_SMTP_FROM_NAME`

4. Restart DDEV:
```bash
ddev restart
```

Notes:
- Use app passwords for Mail.ru/Yandex/Gmail.
- Without SMTP config, local mail is available in Mailpit:
  - `http://skfo.ddev.site:8025`
  - `https://skfo.ddev.site:8026` (may be unavailable in some setups)

## Logs and Git Hygiene

Runtime ProcessWire logs are generated in `public/site/assets/logs/`.

- `public/site/assets/logs/*.txt` is ignored by Git.
- Generated logs should not be committed.

## Security Notes

Do not commit secrets or environment-specific credentials:
- `public/site/config.php`
- SMTP credentials in `.ddev/config.smtp.local.yaml`

## Handy Commands

```bash
# Check containers and ports
ddev describe

# Open shell in web container
ddev ssh

# Run PHP syntax check for key templates
php -l public/site/templates/hotels.php
php -l public/site/templates/hotel.php
```
