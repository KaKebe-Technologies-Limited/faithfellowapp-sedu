# Deployment guide

This guide takes a fresh **Ubuntu 22.04 or 24.04** server to a production FaithFellows install. A 4 vCPU / 8 GB VPS comfortably serves a few hundred churches. Live streaming is the heaviest component, and can be moved to its own server or to Cloudflare Stream.

## 1. Architecture

```
                 Cloudflare (DNS, TLS, caching of /build)
                              │
                 ┌────────────┴────────────┐
           nginx :443                 nginx-rtmp :1935  ← OBS from churches
   faithfellows.com, *.faithfellows.com,     │ HLS → /var/www/hls
   custom church domains                      │
                 │                            │ on_publish / on_publish_done
              PHP-FPM 8.2 ─── Laravel ────────┘
                 │            │
             MySQL 8      Redis (optional: cache, queue, sessions)
                              │
                 Supervisor: queue workers (+ Reverb)
                 Cron: schedule:run every minute, nightly backup
```

## 2. Packages

```bash
sudo apt update && sudo apt -y upgrade
sudo apt -y install nginx libnginx-mod-rtmp mysql-server redis-server supervisor git unzip curl certbot python3-certbot-dns-cloudflare
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt -y install php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis php8.2-gd php8.2-imagick php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl php8.2-gmp php8.2-opcache
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo bash - && sudo apt -y install nodejs
```

`php8.2-gmp` speeds up web-push encryption, and `ffmpeg` is optional for audio-only streams. In `/etc/php/8.2/fpm/php.ini`, set:

```ini
upload_max_filesize = 200M
post_max_size = 210M
memory_limit = 256M
max_execution_time = 120
opcache.enable = 1
opcache.validate_timestamps = 0   ; deploy.sh reloads FPM after each release
```

## 3. Database

```sql
CREATE DATABASE faithfellows CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'faithfellows'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL PRIVILEGES ON faithfellows.* TO 'faithfellows'@'localhost';
```

Store the same credentials in `/root/.my.cnf` (mode 600) for `backup.sh`.

## 4. Directory layout and first release

```bash
sudo mkdir -p /var/www/faithfellows/{releases,shared/storage} /var/www/hls
sudo chown -R www-data:www-data /var/www/faithfellows /var/www/hls
sudo -u www-data cp .env.example /var/www/faithfellows/shared/.env   # then edit, see section 5
sudo -u www-data mkdir -p /var/www/faithfellows/shared/storage/{app/public,framework/{cache,sessions,views},logs}
sudo -u www-data FF_REPO=git@github.com:<org>/faithfellows.git deploy/scripts/deploy.sh main
sudo -u www-data php /var/www/faithfellows/current/artisan key:generate
sudo -u www-data php /var/www/faithfellows/current/artisan db:seed --force   # roles, plans, super admin, blog (no demo data in production)
```

`deploy.sh` clones into `releases/<timestamp>`, links the shared `.env` and `storage`, installs dependencies, builds assets, migrates, caches config, routes and views, switches the `current` symlink, reloads PHP-FPM and restarts workers. It keeps the last 5 releases and prints a one-line rollback command.

## 5. Environment (`shared/.env`)

| Key | Production value |
|-----|------------------|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://faithfellows.com` |
| `FF_ROOT_DOMAIN` | `faithfellows.com` (church sites live at `{slug}.faithfellows.com`) |
| `DB_*` | the database above |
| `QUEUE_CONNECTION` | `redis` (or `database`) |
| `CACHE_STORE` / `SESSION_DRIVER` | `redis` |
| `SESSION_SECURE_COOKIE` | `true` |
| `MAIL_*` | SMTP / SES / Mailgun credentials, with `MAIL_FROM_ADDRESS=service@kakebe.tech` |
| `PAYMENTS_SANDBOX` | `false` once gateways are live |
| `MTN_MOMO_*`, `AIRTEL_*`, `IOTEC_*`, `STRIPE_*` | gateway credentials. A gateway without credentials stays in sandbox. ioTec Pay (`IOTEC_CLIENT_ID`, `IOTEC_CLIENT_SECRET`, `IOTEC_LIVE_WALLET_ID`, `IOTEC_IPN_SECRET`) can take both MTN and Airtel payments from the app; keep `IOTEC_SANDBOX=false` in production, `true` only with ioTec's test numbers |
| `AFRICASTALKING_*`, `SMS_ENABLED`, `WHATSAPP_ENABLED`, `SMS_COST_UGX` | messaging |
| `USSD_SECRET` | random string; register the USSD callback as `https://faithfellows.com/webhooks/ussd?secret=…` |
| `VAPID_*` | `php artisan ff:vapid` (generate once; changing the keys invalidates every browser subscription) |
| `STREAMING_*` | `self_hosted` with `STREAMING_RTMP_URL=rtmp://live.faithfellows.com/live`, `STREAMING_HLS_BASE_URL=https://live.faithfellows.com/hls`, or Cloudflare Stream |
| `BROADCAST_CONNECTION` | `reverb` for real-time in-app notifications, otherwise `log` |
| `SEED_SUPER_ADMIN_EMAIL` / `SEED_SUPER_ADMIN_PASSWORD` | first super admin. Change the password after the first login |

After editing, run `php artisan config:cache` (deploy.sh does this on every release).

## 6. nginx, TLS and custom domains

```bash
sudo cp deploy/nginx/faithfellows.conf /etc/nginx/sites-available/
sudo ln -s /etc/nginx/sites-available/faithfellows.conf /etc/nginx/sites-enabled/
sudo cp deploy/nginx/rtmp.conf /etc/nginx/rtmp.conf      # and add "include /etc/nginx/rtmp.conf;" at the top level of nginx.conf
sudo certbot certonly --dns-cloudflare --dns-cloudflare-credentials /root/cf.ini -d faithfellows.com -d '*.faithfellows.com'
sudo nginx -t && sudo systemctl reload nginx
```

- **Wildcard DNS:** `A faithfellows.com` and `A *.faithfellows.com` point to the server. `live.faithfellows.com` points to the streaming server.
- **Custom church domains:** the church adds a CNAME to `faithfellows.com` (or an A record), and a super admin marks the domain verified. Then issue a certificate with `certbot --nginx -d www.church-domain.org`. The default server block serves it, and `ResolveCustomDomain` maps the host to the church.
- **Firewall:** `ufw allow 22,80,443,1935/tcp`.

## 7. Workers, scheduler and backups

```bash
sudo cp deploy/supervisor/faithfellows.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo cp deploy/cron/faithfellows /etc/cron.d/faithfellows
sudo chmod +x /var/www/faithfellows/current/deploy/scripts/*.sh
```

- Queue workers handle notification fan-out, emails, receipts, delayed (quiet-hours) push and SMS, and transcription.
- The scheduler runs every `ff:*` task listed in `deploy/cron/faithfellows`.
- `backup.sh` runs nightly at 02:15. It dumps the database, archives `storage/app` and `.env`, keeps 14 days, and copies off-site when `FF_BACKUP_REMOTE` (rclone) is set. Test a restore with `deploy/scripts/restore.sh <stamp>` on a staging server every quarter.

## 8. Payment gateway callbacks

Register these URLs with each provider. They are also shown on **Super admin → Payment gateways**.

| Provider | Callback URL |
|----------|--------------|
| MTN MoMo | `https://faithfellows.com/webhooks/mtn-momo` |
| Airtel Money | `https://faithfellows.com/webhooks/airtel` |
| Iotec | `https://faithfellows.com/webhooks/iotec` |
| Stripe | `https://faithfellows.com/webhooks/stripe` (events: `checkout.session.completed`, `checkout.session.expired`, `charge.refunded`) |
| Africa's Talking USSD | `https://faithfellows.com/webhooks/ussd?secret=<USSD_SECRET>` |

Mobile-money callbacks are never trusted on their own: the app re-queries the gateway before marking a gift paid. `ff:payments-reconcile` re-checks pending payments every 5 minutes.

## 9. Monitoring checklist

- `storage/logs/laravel.log` (daily rotation recommended: `LOG_CHANNEL=daily`), plus `worker.log` and `reverb.log`
- `php artisan queue:failed`: failed jobs (also counted on Super admin → Settings → Integrations)
- `/up`: health endpoint for uptime monitors
- Disk space for `/var/www/hls` and `storage/app` (sermon media grows quickly). Consider S3-compatible storage for media at scale (`FILESYSTEM_DISK=s3`, `MEDIA_DISK=s3`).

## 10. Upgrades and rollback

- **Deploy:** `sudo -u www-data /var/www/faithfellows/current/deploy/scripts/deploy.sh main`
- **Rollback:** point `current` at the previous release (deploy.sh prints the exact command). If the release contained migrations, restore the pre-deploy backup or run `php artisan migrate:rollback --step=1` in the old release.
- **Maintenance window:** `php artisan down --render="errors::503" --retry=60`, then `php artisan up`.
