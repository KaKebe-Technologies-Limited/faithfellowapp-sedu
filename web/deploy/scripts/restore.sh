#!/usr/bin/env bash
# Restore a FaithFellows backup made by backup.sh.
#   deploy/scripts/restore.sh 20261012-0215
# Puts the site in maintenance mode, restores DB + files, then brings it back.
set -euo pipefail

STAMP=${1:?Usage: restore.sh <stamp, e.g. 20261012-0215>}
APP=/var/www/faithfellows
SRC=${FF_BACKUP_DIR:-/var/backups/faithfellows}
DB=${FF_DB_NAME:-faithfellows}

[[ -f "$SRC/db-$STAMP.sql.gz" ]] || { echo "No backup db-$STAMP.sql.gz in $SRC"; exit 1; }

read -r -p "Restore $STAMP over the LIVE database '$DB'? Type yes: " ok
[[ "$ok" == "yes" ]] || exit 1

php "$APP/current/artisan" down --retry=60

echo "→ Restoring database"
gunzip -c "$SRC/db-$STAMP.sql.gz" | mysql "$DB"

if [[ -f "$SRC/storage-$STAMP.tar.gz" ]]; then
  echo "→ Restoring uploaded files"
  tar -xzf "$SRC/storage-$STAMP.tar.gz" -C "$APP/shared/storage"
  chown -R www-data:www-data "$APP/shared/storage/app"
fi

php "$APP/current/artisan" optimize:clear
php "$APP/current/artisan" queue:restart
php "$APP/current/artisan" up
echo "✓ Restored $STAMP"
