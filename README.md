# FaithFellows

**Empowering Your Ministry in the Digital Age.**

FaithFellows is a multi-tenant church management and engagement platform built for Uganda and East Africa by **Kakebe Technologies Limited** (Lira City). Every church gets its own digital home with a website, sermons, live streams, events, mobile-money giving, community features and pastoral care. Members follow any number of churches from one account.

> Traditional methods of reaching and engaging congregations are no longer sufficient. Expensive radio and TV broadcasts limit your reach. FaithFellows offers a cost-effective, far-reaching, and user-friendly digital solution to keep your ministry thriving.

**Pricing:** UGX 60,000 per church per month, with the first 12 months of hosting free. A custom domain is optional at UGX 60,000 per year.

---

## Features

| Area | What churches and members get |
|------|-------------------------------|
| **Church websites** | An auto-generated site at `{slug}.faithfellows.app` (or a custom domain) built from drag-and-drop blocks, plus a public page in the church directory |
| **Content** | Sermons (audio/video, series, playlists, transcripts, offline listening), devotionals, announcements, reading plans |
| **Live** | RTMP (OBS) or embedded streams, an HLS player with an audio-only mode for low bandwidth, live chat, viewer analytics and recordings |
| **Events** | RSVPs, paid tickets, QR check-in, reminders, Q&A |
| **Giving** | MTN MoMo, Airtel Money, Iotec, cards (Stripe), bank transfer, USSD, cash records, campaigns with milestones, pledges, recurring gifts, PDF receipts, payouts and reconciliation |
| **Community** | A prayer wall (public / church / pastor-only, "I prayed", prayer chains), moderated testimonies, reactions and comments, small groups with leader tools, a volunteer rota |
| **Care** | Service attendance (daily QR + geofence), birthdays and follow-ups, encrypted pastoral notes, welfare, home visits, a discipleship pathway, confidential counseling |
| **Messaging** | In-app, email, web push, SMS and WhatsApp notifications, with quiet hours, a daily digest, a fallback chain and a church composer with SMS cost estimates |
| **Insight** | Church analytics and platform analytics, CSV exports and PDF reports |
| **Platform** | Church approvals, plans and billing, support tickets, moderation, users, blog, contact inbox, audit log, a payment-gateway kill switch |
| **Access** | Bootstrap PWA (installable, works offline), a lite mode for slow connections, English, Luo, Luganda and Swahili, a JSON API (`/api/v1`) |

Roles: **Super Admin** (Kakebe staff), **Church Admin**, **Church Staff** (custom roles from a permission catalogue), **Member** and **Visitor**.

## Tech stack

- **Laravel 12** on PHP 8.2+. Laravel 11 is blocked by security advisories; see [DECISIONS.md](DECISIONS.md) #1.
- **MySQL 8 / MariaDB 10.4+**
- **Blade + Alpine.js + Bootstrap 5.3**, built with **Vite**. No React, Vue, Tailwind or Inertia.
- Breeze (auth), Sanctum (API tokens), Spatie Permission and Medialibrary, DomPDF, simple-qrcode, minishlink/web-push
- Chart.js, Quill, hls.js and Leaflet, all lazy-loaded
- Pest for tests, Pint for formatting
- Brand: Viva Magenta `#BC2649`

## Quick start (local)

Requirements: PHP 8.2 with `pdo_mysql`, `gd` or `imagick`, `bcmath`, `intl`, `zip` and `openssl`; Composer; Node 20+; MySQL or MariaDB.

```bash
git clone <repo> faithfellows && cd faithfellows
composer install
npm install
cp .env.example .env
php artisan key:generate
# create the databases "faithfellows" and "faithfellows_test", then:
php artisan migrate --seed
php artisan ff:vapid --write        # web push keys (optional)
npm run build                       # or: npm run dev
php artisan serve
```

On Windows with XAMPP, use `C:\xampp\php\php.exe` for `php`. Web-push encryption also needs `OPENSSL_CONF=C:\xampp\php\extras\ssl\openssl.cnf` in the environment of the PHP process.

To process queued notifications, receipts and other jobs, run a queue worker in a second terminal:

```bash
php artisan queue:work
```

To run scheduled tasks locally (publishing, reminders, digests and so on), run the scheduler:

```bash
php artisan schedule:work
```

### Demo accounts (seeded when `APP_ENV` is not `production`)

| Role | Email | Password |
|------|-------|----------|
| Super admin | `admin@faithfellows.app` | `SEED_SUPER_ADMIN_PASSWORD` from `.env` |
| Church admin (Grace Community Church Lira) | `pastor@faithfellows.test` | `password` |
| Finance staff | `finance@faithfellows.test` | `password` |
| Member | `member@faithfellows.test` | `password` |

Payments run in **sandbox mode** (`PAYMENTS_SANDBOX=true`): mobile-money gifts "approve" after a few seconds, numbers ending in `0000` are declined, and numbers ending in `1111` never answer. SMS and WhatsApp are written to the log until `SMS_ENABLED` / `WHATSAPP_ENABLED` are set.

## Tests

Tests run against the MySQL database `faithfellows_test` (configured in `phpunit.xml`):

```bash
php artisan test            # full suite
php artisan test --filter=Giving
./vendor/bin/pint           # code style
```

## Project layout

```
app/
  Http/Controllers/{Public,Member,ChurchAdmin,SuperAdmin,Api/V1,Webhooks,ChurchSite}
  Models/                   Eloquent models (tenant models use BelongsToChurch)
  Services/                 Domain logic: Payment, Donation, Notification, Feed, Prayer, Care, Analytics…
  Notifications/            ChurchNotification base + channels (SMS, WhatsApp, WebPush)
  Policies/                 Per-model authorisation (tenant isolation + church permissions)
config/faithfellows.php     Brand, pricing, permission catalogue, languages, payment methods
routes/web.php              Public, member, /church/{slug}/admin, /super-admin, webhooks
routes/api.php              /api/v1 (Sanctum)
routes/console.php          ff:* commands and the schedule
deploy/                     nginx, RTMP, supervisor, cron, deploy/backup/restore scripts
docs/                       Deployment, admin, onboarding and API guides
lang/                       Luo, Luganda, Swahili UI translations (drafts for native review)
DECISIONS.md                Every architectural decision, sprint by sprint
```

## Documentation

- [Deployment guide](docs/DEPLOYMENT.md): server setup, environment, workers, streaming, backups
- [Super admin guide](docs/ADMIN_GUIDE.md): running the platform day to day
- [Church onboarding guide](docs/CHURCH_ONBOARDING.md): from application to first live service
- [API reference](docs/API.md): `/api/v1` for the mobile app and integrations
- [DECISIONS.md](DECISIONS.md): why things are built the way they are

## Company

**Kakebe Technologies Limited**, Rm B1 Plot 43, Renzo City House, Obote Ave., Lira City · P.O Box 660031, Lira City
0393249845 · service@kakebe.tech · [kakebe.tech](https://www.kakebe.tech)

© Kakebe Technologies Limited. All rights reserved. Proprietary software.
