#!/usr/bin/env bash
# FaithFellows zero-downtime deploy (release directories + symlink switch).
#
#   /var/www/faithfellows/
#     releases/20261012-1530/   ← each deploy
#     shared/.env  shared/storage/
#     current → releases/<latest>
#
# Usage:  sudo -u www-data deploy/scripts/deploy.sh [git-ref]   (default: main)
set -euo pipefail

APP=/var/www/faithfellows
REPO=${FF_REPO:-git@github.com:kakebe-tech/faithfellows.git}
REF=${1:-main}
KEEP=5
RELEASE="$APP/releases/$(date +%Y%m%d-%H%M%S)"

echo "→ Cloning $REF into $RELEASE"
git clone --depth 1 --branch "$REF" "$REPO" "$RELEASE"
cd "$RELEASE"

echo "→ Linking shared .env and storage"
ln -sfn "$APP/shared/.env" .env
rm -rf storage && ln -sfn "$APP/shared/storage" storage

echo "→ Installing PHP dependencies"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "→ Building front-end assets"
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules

echo "→ Migrating database"
php artisan migrate --force

echo "→ Caching config, routes, views, events"
php artisan optimize
php artisan storage:link >/dev/null 2>&1 || true

echo "→ Switching current → $RELEASE"
ln -sfn "$RELEASE" "$APP/current.tmp" && mv -Tf "$APP/current.tmp" "$APP/current"

echo "→ Reloading PHP-FPM and restarting workers"
sudo systemctl reload php8.2-fpm
php "$APP/current/artisan" queue:restart
sudo supervisorctl restart faithfellows:faithfellows-reverb >/dev/null 2>&1 || true

echo "→ Pruning old releases (keeping $KEEP)"
ls -1dt "$APP"/releases/* | tail -n +$((KEEP + 1)) | xargs -r rm -rf

echo "✓ Deployed $(git -C "$RELEASE" rev-parse --short HEAD) to $APP/current"
echo "  Rollback: ln -sfn \$(ls -1dt $APP/releases/* | sed -n 2p) $APP/current && sudo systemctl reload php8.2-fpm && php $APP/current/artisan queue:restart"
