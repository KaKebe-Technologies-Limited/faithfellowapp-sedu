# Hosting on shared hosting (Hostinger) at https://faithfellows.com

This is the quick route for a pilot: one shared-hosting account, one database, no server to manage. For a VPS with nginx,
workers and live streaming, see `DEPLOYMENT.md`.

**The address of everything is `https://faithfellows.com`.** The API is `https://faithfellows.com/api`, the website is the same
host, and the mobile app is built to call it. `www.faithfellows.com` redirects to it. Church websites are `{church}.faithfellows.com`
(they need a wildcard DNS record, see "Things to set up afterwards").

## What you upload

Run this on your computer, from the `web` folder (it needs MariaDB running):

```bash
python deploy/shared-hosting/build.py
```

It writes three files to `web/deploy/shared-hosting/out/` (never committed to git) and checks the database files by
loading them into a throwaway database first:

| File | What it is |
|---|---|
| `faithfellows-database.sql` | Every table, the roles and plans, the first super admin, the blog, the public-domain Classic Hymns **and sample data** (five churches, members, sermons, events, giving, prayer, quizzes) so the site is not empty. |
| `hymnal-mujje-tutendereze-omukama.sql` | Optional. The Luganda Catholic hymn book, loaded **hidden**. It stays out of the app until you publish it (see "Hymn book" below). |
| `faithfellows-site.zip` | The website: code, built styles, the PHP packages for production, and a root `.htaccess` that serves the `public` folder. |

`web/.env.production` is the settings file for the server. Everything in it is filled in, including the database (`u850523537_FaithFello`).

`build.py` options: `sql` (only the database), `zip` (only the site), `stage <folder>` (the finished site as a folder, used by Git).
A different admin email or a new `DEMO_PASSWORD` is changed in `.env.production` before running it.

## Steps in Hostinger (hPanel)

1. **Website.** `faithfellows.com` must be a website in your hosting account (Websites, Add website, use the domain you own; point its
   nameservers or DNS at Hostinger). Its folder is `domains/faithfellows.com/public_html`. A new website shows a "Default page": that is normal.
2. **PHP.** Websites, Manage, Advanced, PHP Configuration: choose **PHP 8.2 or 8.3**. Make sure these extensions are ticked:
   `curl, dom, exif, fileinfo, gd, iconv, mbstring, openssl, pdo_mysql, tokenizer, zip` (and `intl` if offered).
   Options: `upload_max_filesize 420M`, `post_max_size 440M`, `memory_limit 256M`, `max_execution_time 900` (the site also ships
   `public/.user.ini` with the upload and time values; hPanel wins if it sets lower ones, so raise them here too). Church staff can upload audio
   up to 150 MB and video up to 200 MB in one form; the platform admin's songs (up to 400 MB each, Super admin → Gospel radio) are sent in 4 MB
   pieces and do not depend on `upload_max_filesize`. The limits are `FF_MAX_AUDIO_MB`, `FF_MAX_ADMIN_AUDIO_MB` and `FF_MAX_UPLOAD_MB`.
3. **Upload the site** (skip this when you use Git, see below). File Manager, open `public_html`, delete the default page, upload
   `faithfellows-site.zip`, **Extract** it there, then delete the zip. `artisan`, `.htaccess`, `app` and `public` sit directly in `public_html`.
4. **Settings.** Upload `.env.production` into the same folder and rename it to exactly `.env` (turn on "show hidden files").
5. **Database.** hPanel, Databases, phpMyAdmin, open `u850523537_FaithFello`, **Import**, choose `faithfellows-database.sql`.
   Import the Luganda file next, only if you want the hymn book in the database (still hidden). Import each file **once**:
   they replace the tables, so importing again would erase everything entered since.
6. **Secure connection.** Websites, SSL: make sure the certificate for `faithfellows.com` is active and turn on **Force HTTPS**.
7. **Uploaded pictures and files.** Run once: `php artisan storage:link` (SSH), or add a one-time cron job with the command
   `php /home/u850523537/domains/faithfellows.com/public_html/artisan storage:link` and remove it after a minute.
8. **Scheduled jobs and the queue** (emails, push, receipts, podcasts, new database migrations): hPanel, Advanced, Cron Jobs, two jobs, every minute:
   ```
   php /home/u850523537/domains/faithfellows.com/public_html/artisan schedule:run
   php /home/u850523537/domains/faithfellows.com/public_html/artisan queue:work --stop-when-empty --max-time=50
   ```
   Use the `php` command Hostinger shows in the cron form if it gives a full path such as `/usr/bin/php`.

Check it: open `https://faithfellows.com/up` (a green page), then `https://faithfellows.com/api/ping`, then the home page.

## Moving from the first test address (ff.bodaerp.site)

`faithfellows.com` has its own database (`u850523537_FaithFello`), so it starts fresh with the sample data and the admin
`admin@faithfellows.com`. The old test site and its database are not touched.

1. Do steps 1 to 8 above for `faithfellows.com` (import `faithfellows-database.sql` into `u850523537_FaithFello`).
2. Create the mailbox `admin@faithfellows.com` in Hostinger (Emails), so password resets reach you.
3. Apps built for the old address stop working. Install the new build (profile `preview`), which calls `https://faithfellows.com/api`.
4. When everything works, remove the `ff.bodaerp.site` website and its old database from hPanel.

## Signing in

The passwords are in `web/.env.production` on your computer (not in this document, not in git):

| Account | Email | Password line |
|---|---|---|
| Super admin (`/login`, then `/super-admin`) | `SEED_SUPER_ADMIN_EMAIL` | `SEED_SUPER_ADMIN_PASSWORD` |
| Sample member (the app) | `member@faithfellows.test` | `DEMO_PASSWORD` |
| Sample church admin | `pastor@faithfellows.test` | `DEMO_PASSWORD` |
| Sample finance staff | `finance@faithfellows.test` | `DEMO_PASSWORD` |

All the other sample members use `DEMO_PASSWORD` too. The public password "password" does **not** work on the hosted site.
Change the super admin password after the first login. Anyone can create their own account in the app.

## The mobile app

The app builds in `app/eas.json` point to `https://faithfellows.com/api` (profiles `preview`, `production-apk`, `production`).
Build one with `npx.cmd eas-cli build -p android --profile preview`. See `app/README.md`.

## Things to set up afterwards

- **ioTec Pay.** `IOTEC_SANDBOX=false` is copied from your computer, so gifts move **real money**. Register the callback
  `https://faithfellows.com/webhooks/iotec` with ioTec. Set `IOTEC_SANDBOX=true` first to test with ioTec's test numbers.
- **Email.** `MAIL_MAILER=log` keeps emails in `storage/logs` so sign-up works before email exists. Create a mailbox in
  Hostinger (Emails), then switch `.env` to the `smtp` lines (commented in the file). Password resets need real email.
- **Google sign-in.** Add `https://faithfellows.com/auth/google/app/callback` to the authorised redirect URIs in Google Cloud.
- **Church subdomains** (`grace-lira.faithfellows.com`) need a wildcard subdomain: in Hostinger add `*` under the domain's subdomains (and
  a wildcard certificate). Not needed for the app. Until then links to a church's own site do not open.
- **Hymn book.** The Luganda hymn book is under copyright. It is hidden. Publish it in Super admin, Hymn book, only with the
  publisher's written permission (the Inter-Diocesan Committee, Archdiocese of Kampala).
- **Sample data.** The churches and people are fictional. Before real churches use the site, build a database without them: take the `DemoSeeder` step (and the password re-hash that follows it) out of `build_sql()` in `deploy/shared-hosting/build.py`, then import into a fresh database.

## Updating the site later

### Through Git (recommended): push, and the site updates

1. **You push** to `main` on GitHub (`git push`). The workflow `.github/workflows/production-branch.yml` builds the finished site
   (the same thing `build.py` makes: production PHP packages, built styles, root `.htaccess`) and commits it to a branch
   called **`production`**. It takes about 3 minutes (see the Actions tab on GitHub). Only pushes that change `web/` start it.
2. **Hostinger pulls that branch** into the site folder, either when you press **Deploy** in hPanel or by itself through the webhook.
3. **New database changes are applied** within a minute by the scheduler (`FF_AUTO_MIGRATE=true` in the server `.env`, and the `schedule:run` cron job from step 8). If you have no cron job, open **Super admin, Database updates** after an update that changes the database (the menu shows a number when something waits) and press **Apply now**. Until a change is applied, the screens that need it show a message instead of failing.

The branch never contains `.env`, uploaded files, logs or caches, so a deploy cannot overwrite them. Keep the repository private.

**Connecting Hostinger to the branch (once)**

1. Push first, so the `production` branch exists (Actions tab: "Build the production branch" must be green).
2. In hPanel open the `faithfellows.com` website, **Advanced, Git**, and create a repository entry:
   - **Repository:** `git@github.com:KaKebe-Technologies-Limited/faithfellowapp-sedu.git` (the SSH address, because the repository is private).
   - **Branch:** `production`.
   - **Install path:** leave it empty (the site lives in `public_html`). The folder must be empty for the first deploy, so delete the default page first.
3. hPanel shows an **SSH key**. In GitHub open the repository, Settings, **Deploy keys**, Add deploy key, paste it (read-only is enough).
4. Press **Deploy**.
5. Upload `.env.production` into `public_html` as `.env` (step 4 above), import the database (step 5), run `php artisan storage:link` once, and add the cron jobs (step 8).
6. For automatic deploys: in hPanel copy the **webhook URL** of the Git entry, then in GitHub open Settings, **Webhooks**, Add webhook,
   paste the URL, content type `application/json`, event "Just the push event".

**Working day:** commit, `git push`. When the Actions run is green, the site is updated (press Deploy in hPanel if you did not set up the webhook).

**Good to know**

- The first run is slow (about 8,000 vendor files). Later runs only commit what changed.
- A migration runs on the live database as soon as it is deployed. Export the database from phpMyAdmin before pushing a risky one,
  and keep migrations additive (add columns and tables, do not drop them) so installed apps keep working.
- If the Actions run is red, nothing is deployed: open the run, read the red step.

### By hand (zip)

Run `python deploy/shared-hosting/build.py zip`, upload the new zip, **Extract** over the old files (it never contains `.env` or
uploaded files, so they stay), and run `php artisan migrate --force` if the update has database changes. Do not import the SQL again.

## If something is wrong

| Symptom | Likely cause |
|---|---|
| **403 Forbidden** on the home page | The website folder holds the wrong files. The Git entry must deploy the **`production`** branch, never `main` (`main` is the source code: no `index.php` in the root, so the server refuses to list it). See "If the site shows 403" below. |
| A Super admin page shows 500 right after an update | The update brought a database change that has not been applied. Open `/super-admin/updates` and press **Apply now** (or add the cron job, step 8). |
| Error 500 / blank page | Read `storage/logs/laravel-<date>.log`. Usually a missing `.env`, a wrong PHP version (needs 8.2+) or a missing extension. |
| "Access denied for user" | `DB_PASSWORD` in `.env` is wrong, or the database user is not attached to the database. |
| "No application encryption key" | `.env` was not uploaded or is not named exactly `.env`. |
| Styles missing or mixed-content warnings | `APP_URL` is not `https://faithfellows.com`, or Force HTTPS is off. |
| 404 on every page except the home page | `.htaccess` was not extracted (hidden file), or mod_rewrite is off. |
| Pictures do not show | `php artisan storage:link` was not run. |
| Emails and pushes never arrive | The cron jobs (step 8) are missing. |

### If the site shows 403, or you can open `/web/...` or `/app/...` addresses

That means the Git entry in hPanel deploys `main` instead of `production`. Fix it in this order:

1. In GitHub, Actions: "Build the production branch" must have run green once (the `production` branch must exist).
2. In hPanel, Advanced, Git: **delete the entry** (and the webhook in GitHub, Settings, Webhooks, if you added one).
3. In File Manager open `public_html`, show hidden files, select **everything** (including `.git`) and delete it.
4. Create the Git entry again with branch **`production`** and an empty install path, press **Deploy**, upload `.env`, run `storage:link`.
5. `https://faithfellows.com/up` must show a green page, and `https://faithfellows.com/web/artisan` must be 404.

The `.htaccess` at the top of `main` makes a server that is pointed at `main` by mistake serve nothing, so the source stays private in the meantime.

## After the app 1.1 update: email, Google sign-in and the ESV Bible

Three things are set up once, by the super admin, on the website (nothing needs to be typed into a server file, and none of the secrets belong in git):

1. **Email (receipts, the thank-you note every giver gets, password codes).** Super admin, **Email**: the Gmail address (`faithfellowsofficial@gmail.com`), its Google **App Password** (Google account, Security, 2-Step Verification, App passwords), switch **Send emails from this account** on, **Save**, then **Send test**. The password is saved encrypted and never shown again. Emails are queued: the `queue:work` cron job of step 8 must be running. (Alternatively put `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` in the server `.env`; the website setting wins when it is on.)
2. **Google sign-in.** Super admin, **Google sign-in**: the OAuth client ID and secret of the Google Cloud project. In Google Cloud, Credentials, open that Web client and add `https://faithfellows.com/auth/google/app/callback` under **Authorised redirect URIs**; publish the OAuth consent screen (otherwise only listed test users can sign in).
3. **ESV Bible.** Get a free key at https://api.esv.org/account/ and put `ESV_API_KEY=…` in the server `.env`. Until then the app reads the King James Version. The ESV is licensed text: it is never stored on the server or the phones, and Crossway's terms apply (non-commercial use).

New database changes (giving reasons, community games and the 25 sample games, inspiration) are applied by the scheduler, or from Super admin, **Database updates**. Members need the 1.1.0 app (a new APK) for the new icon and bottom bar; the screens themselves also update over the air once their phone has 1.1.0.

### Money, quiet churches, sign-up emails and inspiration pushes

All of these run from the one `schedule:run` cron job (every minute) and the `queue:work` job: the billing reminders (`ff:billing`, 06:00), the dormant-church check (`ff:dormancy`, 06:30) and the inspiration pushes (`ff:inspiration`, every minute, sends at the times set in Super admin, Inspiration). Set the monthly fee under Super admin, Plans, and how long a quiet church waits under Settings. **Super admin, Push test** sends a test notification to a member's phone and shows why it did not arrive. **Super admin, Email** lists who is emailed about every new account.

