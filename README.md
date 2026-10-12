# FaithFellows

Multi-tenant church management and engagement SaaS platform by
[Kakebe Technologies Limited](https://kakebe.tech) — Lira City, Uganda.

**Tagline:** Empowering Your Ministry in the Digital Age

## Repository Structure

```
faithfellows/
├── web/   → Laravel 12 web application + admin dashboard + REST API (/api/v1)
└── app/   → Expo / React Native mobile app (iOS + Android)
```

One Git repository, one database, one backend. The Laravel app serves the website **and** the mobile API.
Each project keeps its own dependencies, `.env` file and build output.

More detail: [`web/README.md`](web/README.md) (features, architecture, deployment),
[`web/docs/API.md`](web/docs/API.md) (REST API), [`web/DECISIONS.md`](web/DECISIONS.md) (design decisions),
[`app/README.md`](app/README.md) (mobile app).

## Tech Stack

**Web (`web/`)**
- Laravel 12 (PHP 8.2+)
- MySQL 8.0+ / MariaDB 10.4+
- Bootstrap 5.3
- Blade + Alpine.js + vanilla JS
- Vite build tool
- Laravel Sanctum (API tokens)
- Spatie Laravel Permission (roles)

**App (`app/`)**
- Expo SDK 57 (React Native), JavaScript (no TypeScript yet)
- Expo Router (file-based navigation)
- Axios (API calls)
- Expo SecureStore (auth token)
- Expo Audio (sermon playback — replaces the removed expo-av)
- Expo Notifications (push, later)
- Space Grotesk font

## Local Development

### Prerequisites
- PHP 8.2+, Composer
- MySQL 8.0+ (or MariaDB 10.4+)
- Node.js 20+ and npm
- The **Expo Go** app on your phone (App Store / Google Play)

### Setup

```bash
git clone <repo-url> faithfellows
cd faithfellows

# Web
cd web
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install
npm run build

# App
cd ../app
npm install
cp .env.example .env
# Edit .env and set EXPO_PUBLIC_API_URL to your computer's LAN IP
```

### Running both (two terminals)

Terminal 1 — Laravel backend:

```bash
cd web
php artisan serve --host=0.0.0.0 --port=8000
```

Terminal 2 — Expo mobile app:

```bash
cd app
npx expo start
```

Open Expo Go on your phone and scan the QR code. (From the repo root you can also use
`npm run web:dev` and `npm run app:start`, or `./scripts/dev.sh` to start both.)

### Finding your computer's IP (for phone testing)

- macOS: `ipconfig getifaddr en0`
- Windows: `ipconfig` → IPv4 Address
- Linux: `hostname -I`

Use it in `app/.env`:

```
EXPO_PUBLIC_API_URL=http://192.168.1.42:8000/api
```

Your phone and computer must be on the same Wi-Fi. Also set `APP_URL=http://192.168.1.42:8000` in `web/.env`
so logos, photos and sermon audio get addresses the phone can reach. Check the connection from the
app's **Profile → Connection** row, or open `http://192.168.1.42:8000/api/ping` in the phone's browser.

### Demo accounts (after `php artisan migrate --seed`)

| Role | Email | Password |
|------|-------|----------|
| Member | `member@faithfellows.test` | `password` |
| Church admin | `pastor@faithfellows.test` | `password` |

## Deployment

- Web: Hostinger shared hosting or a VPS (see `web/deploy/`)
- App: EAS Build → App Store / Google Play

## Brand

- Primary: Viva Magenta `#BC2649`
- Font: Space Grotesk
- Logo: FaithFellows wordmark + cross icon

## Contact

- Kakebe Technologies Limited
- Rm B1 Plot 43, Renzo City House, Obote Ave., Lira City
- Phone: 0393249845
- Email: service@kakebe.tech
