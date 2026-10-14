# Hosting on shared hosting (Hostinger) at https://ff.bodaerp.site

This is the quick route for a pilot: one shared-hosting account, one database, no server to manage. For a VPS with nginx,
workers and live streaming, see `DEPLOYMENT.md`.

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

`web/.env.production` is the settings file for the server. Everything in it is filled in except `DB_PASSWORD`.

`build.py` options: `sql` (only the database), `zip` (only the site).
A different admin email or a new `DEMO_PASSWORD` is changed in `.env.production` before running it.

## Steps in Hostinger (hPanel)

1. **Domain.** Create the subdomain `ff` for `bodaerp.site` (Domains, Subdomains). Note the folder it uses (File Manager shows it).
2. **PHP.** Websites, Manage, Advanced, PHP Configuration: choose **PHP 8.2 or 8.3**. Make sure these extensions are ticked:
   `curl, dom, exif, fileinfo, gd, iconv, mbstring, openssl, pdo_mysql, tokenizer, zip` (and `intl` if offered).
   Options: `upload_max_filesize 64M`, `post_max_size 64M`, `memory_limit 256M`, `max_execution_time 120`.
3. **Upload the site.** File Manager, open the subdomain's folder, upload `faithfellows-site.zip`, **Extract** it there, then delete
   the zip. When it is done, `artisan`, `.htaccess`, `app` and `public` sit directly in that folder.
4. **Settings.** Upload `.env.production` into the same folder and rename it to exactly `.env` (turn on "show hidden files").
   Open it and replace `DB_PASSWORD="<PASTE_NEW_PASSWORD>"` with the database password (keep the quotes).
5. **Database.** hPanel, Databases, phpMyAdmin, open `u850523537_faithfellowsDB`, **Import**, choose `faithfellows-database.sql`.
   Import the Luganda file next, only if you want the hymn book in the database (still hidden). Import each file **once**:
   they replace the tables, so importing again would erase everything entered since.
6. **Secure connection.** Websites, SSL: make sure the certificate for `ff.bodaerp.site` is active and turn on **Force HTTPS**.
7. **Uploaded pictures and files.** Run once: `php artisan storage:link` (SSH), or add a one-time cron job with the command
   `php /home/u850523537/<folder>/artisan storage:link` and remove it after a minute.
8. **Scheduled jobs and the queue** (emails, push, receipts, podcasts): hPanel, Advanced, Cron Jobs, two jobs, every minute:
   ```
   php /home/u850523537/<folder>/artisan schedule:run
   php /home/u850523537/<folder>/artisan queue:work --stop-when-empty --max-time=50
   ```
   Use the `php` command Hostinger shows in the cron form if it gives a full path such as `/usr/bin/php`.

Check it: open `https://ff.bodaerp.site/up` (a green page), then `https://ff.bodaerp.site/api/ping`, then the home page.

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

The app builds in `app/eas.json` already point to `https://ff.bodaerp.site/api` (profiles `preview`, `production-apk`,
`production`). Build one with `npx.cmd eas-cli build -p android --profile preview`. See `app/README.md`.

## Things to set up afterwards

- **ioTec Pay.** `IOTEC_SANDBOX=false` is copied from your computer, so gifts move **real money**. Register the callback
  `https://ff.bodaerp.site/webhooks/iotec` with ioTec. Set `IOTEC_SANDBOX=true` first to test with ioTec's test numbers.
- **Email.** `MAIL_MAILER=log` keeps emails in `storage/logs` so sign-up works before email exists. Create a mailbox in
  Hostinger (Emails), then switch `.env` to the `smtp` lines (commented in the file). Password resets need real email.
- **Google sign-in.** Add `https://ff.bodaerp.site/auth/google/app/callback` to the authorised redirect URIs in Google Cloud.
- **Church subdomains** (`grace-lira.ff.bodaerp.site`) need a wildcard DNS record and wildcard subdomain in Hostinger. Not needed for the app.
- **Hymn book.** The Luganda hymn book is under copyright. It is hidden. Publish it in Super admin, Hymn book, only with the
  publisher's written permission (the Inter-Diocesan Committee, Archdiocese of Kampala).
- **Sample data.** The churches and people are fictional. Before real churches use the site, build a database without them: take the `DemoSeeder` step (and the password re-hash that follows it) out of `build_sql()` in `deploy/shared-hosting/build.py`, then import into a fresh database.

## Updating the site later

### Through Git (recommended): push, and the site updates

1. **You push** to `main` on GitHub (`git push`). The workflow `.github/workflows/production-branch.yml` builds the finished site
   (the same thing `build.py` makes: production PHP packages, built styles, root `.htaccess`) and commits it to a branch
   called **`production`**. It takes about 3 minutes (see the Actions tab on GitHub). Only pushes that change `web/` start it.
2. **Hostinger pulls that branch** into the site folder, either when you press **Deploy** in hPanel or by itself through the webhook.
3. **The scheduler applies new database migrations** within a minute (`FF_AUTO_MIGRATE=true` in the server `.env`). Nothing else to do.

The branch never contains `.env`, uploaded files, logs or caches, so a deploy cannot overwrite them. Keep the repository private.

**Connecting Hostinger to the branch (once)**

1. Push first, so the `production` branch exists (Actions tab: "Build the production branch" must be green).
2. In hPanel open **Advanced, Git** and create a repository entry:
   - **Repository:** `git@github.com:KaKebe-Technologies-Limited/faithfellowapp-sedu.git` (the SSH address, because the repository is private).
   - **Branch:** `production`.
   - **Install path:** the folder of the site. For the subdomain `ff.bodaerp.site` that is `ff` (inside `public_html`); if you open Git from the
     subdomain's own page, leave it empty. The folder must be empty for the first deploy.
3. hPanel shows an **SSH key**. In GitHub open the repository, Settings, **Deploy keys**, Add deploy key, paste it (read-only is enough).
4. Move the existing site out of the way first: in File Manager rename `ff` to `ff-old`, then create the Git entry (it creates a new `ff`),
   press **Deploy**.
5. Copy `ff-old/.env` into the new `ff` folder. Add the line `FF_AUTO_MIGRATE=true` to that `.env` if it is not there. If anyone uploaded
   pictures, copy `ff-old/storage/app/public/*` into `ff/storage/app/public/`. Run `php artisan storage:link` again (once). When the site
   works, delete `ff-old`.
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
| Error 500 / blank page | Read `storage/logs/laravel-<date>.log`. Usually a missing `.env`, a wrong PHP version (needs 8.2+) or a missing extension. |
| "Access denied for user" | `DB_PASSWORD` in `.env` is wrong, or the database user is not attached to the database. |
| "No application encryption key" | `.env` was not uploaded or is not named exactly `.env`. |
| Styles missing or mixed-content warnings | `APP_URL` is not `https://ff.bodaerp.site`, or Force HTTPS is off. |
| 404 on every page except the home page | `.htaccess` was not extracted (hidden file), or mod_rewrite is off. |
| Pictures do not show | `php artisan storage:link` was not run. |
| Emails and pushes never arrive | The cron jobs (step 8) are missing. |
