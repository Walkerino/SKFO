# SKFO Deployment Guide

This project is a ProcessWire site with docroot in `public/`.

## What Is Prepared

- `public/site/config.php` now supports environment variables for production.
- `ops/deploy.sh` deploys a timestamped release and updates `current` symlink.
- `ops/rollback.sh` switches `current` to previous (or specified) release.
- `ops/backup.sh` creates remote DB + uploaded files backups.
- `ops/nginx/skfo.conf.example` contains a production Nginx vhost template.
- `ops/.env.deploy.example` includes required variables.

## 1. Server Requirements

- Ubuntu/Debian server with SSH access.
- Nginx.
- PHP-FPM 8.2+ with extensions: `pdo_mysql`, `gd`, `mbstring`, `json`, `xml`, `curl`, `zip`, `fileinfo`, `openssl`.
- MariaDB/MySQL.
- `rsync`, `composer`, `tar`, `gzip`, `mysqldump`.

## 2. Directory Layout on Server

Deployment scripts expect this layout:

- `/var/www/skfo/releases/<timestamp>`
- `/var/www/skfo/current` (symlink to active release)
- `/var/www/skfo/shared/site-assets-files`
- `/var/www/skfo/shared/site-assets-sessions`
- `/var/www/skfo/shared/site-assets-logs`
- `/var/www/skfo/shared/backups`

Only uploaded files and runtime sessions/logs are shared between releases.

## 3. Nginx Setup

1. Copy template:

```bash
sudo cp ops/nginx/skfo.conf.example /etc/nginx/sites-available/skfo.conf
```

2. Update:

- `server_name`
- `fastcgi_pass` socket path (if your PHP version differs)
- `root` if using a different deployment path

3. Enable and reload:

```bash
sudo ln -s /etc/nginx/sites-available/skfo.conf /etc/nginx/sites-enabled/skfo.conf
sudo nginx -t
sudo systemctl reload nginx
```

4. Add TLS (Certbot):

```bash
sudo certbot --nginx -d skfo.ru -d www.skfo.ru
```

## 4. PHP-FPM Runtime Environment

`public/site/config.php` reads these env vars:

- `SKFO_DB_HOST`, `SKFO_DB_PORT`, `SKFO_DB_NAME`, `SKFO_DB_USER`, `SKFO_DB_PASS`
- `SKFO_HTTP_HOSTS` (comma-separated)
- `SKFO_DEBUG` (`0` for production)
- `SKFO_TIMEZONE`
- `SKFO_SESSION_NAME`
- `SKFO_USER_AUTH_SALT`, `SKFO_TABLE_SALT`

Example for PHP-FPM pool (`/etc/php/8.2/fpm/pool.d/www.conf` or custom pool):

```ini
env[SKFO_DB_HOST] = 127.0.0.1
env[SKFO_DB_PORT] = 3306
env[SKFO_DB_NAME] = skfo
env[SKFO_DB_USER] = skfo
env[SKFO_DB_PASS] = strong_password
env[SKFO_HTTP_HOSTS] = skfo.ru,www.skfo.ru
env[SKFO_DEBUG] = 0
env[SKFO_TIMEZONE] = Europe/Moscow
env[SKFO_SESSION_NAME] = pw353
env[SKFO_USER_AUTH_SALT] = <existing_salt>
env[SKFO_TABLE_SALT] = <existing_salt>
```

Then reload PHP-FPM:

```bash
sudo systemctl reload php8.2-fpm
```

## 5. Prepare Deploy Variables

```bash
cp ops/.env.deploy.example ops/.env.deploy
```

Fill real values in `ops/.env.deploy`.

Important: `ops/.env.deploy` is ignored by Git.

## 6. First Deploy

Run from project root:

```bash
chmod +x ops/deploy.sh ops/rollback.sh ops/backup.sh
./ops/deploy.sh
```

Then verify:

- site opens on main domain
- admin opens: `/processwire/`
- write permissions work (uploads, cache, sessions)

## 7. Regular Deploy

Each deploy creates a new release and switches `current`.

```bash
./ops/deploy.sh
```

## 8. Rollback

Rollback to previous release:

```bash
./ops/rollback.sh
```

Rollback to exact release ID:

```bash
./ops/rollback.sh 20260313183000
```

## 9. Backups

Create backup on server:

```bash
./ops/backup.sh
```

Creates:

- `shared/backups/db-<timestamp>.sql.gz`
- `shared/backups/files-<timestamp>.tar.gz`

Old backups are deleted by `BACKUP_KEEP_DAYS`.

## 10. Restore Notes

Database restore:

```bash
gunzip -c db-<timestamp>.sql.gz | mysql -h 127.0.0.1 -u skfo -p skfo
```

Files restore:

```bash
tar -xzf files-<timestamp>.tar.gz -C /var/www/skfo/shared/
```

After restore, clear runtime cache if needed:

```bash
rm -rf /var/www/skfo/current/public/site/assets/cache/*
```

## 11. Important Nuances

- Keep `SKFO_USER_AUTH_SALT` and `SKFO_TABLE_SALT` unchanged forever.
- Keep `SKFO_DEBUG=0` in production.
- `SKFO_HTTP_HOSTS` must contain all real domains, otherwise ProcessWire will reject requests.
- Ensure webserver user can write into:
  - `shared/site-assets-files`
  - `shared/site-assets-sessions`
  - `shared/site-assets-logs`
  - `current/public/site/assets/cache`
- Do not deploy `.ddev/` and local dev configs to production.
- Before each deploy in production, run `./ops/backup.sh`.

## 12. Security Follow-up

The repository has historical files in `_import/` that may include legacy credentials.
Treat them as compromised secrets: rotate DB/app passwords used there and avoid using those values in production.
