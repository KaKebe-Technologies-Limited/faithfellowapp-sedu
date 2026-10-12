#!/usr/bin/env bash
# FaithFellows nightly backup: MySQL dump + uploaded media + .env, kept 14 days,
# optionally copied off-site with rclone (set FF_BACKUP_REMOTE, e.g. "b2:ff-backups").
#
# Credentials come from /root/.my.cnf ([client] user=… password=…) so they never
# appear in the process list.
set -euo pipefail

APP=/var/www/faithfellows
DEST=${FF_BACKUP_DIR:-/var/backups/faithfellows}
DB=${FF_DB_NAME:-faithfellows}
KEEP_DAYS=${FF_BACKUP_DAYS:-14}
STAMP=$(date +%Y%m%d-%H%M)

mkdir -p "$DEST"
umask 077

echo "[$(date -Is)] Backing up database $DB"
mysqldump --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 "$DB" \
  | gzip -9 > "$DEST/db-$STAMP.sql.gz"

echo "[$(date -Is)] Backing up uploaded files"
tar -czf "$DEST/storage-$STAMP.tar.gz" -C "$APP/shared/storage" app

cp "$APP/shared/.env" "$DEST/env-$STAMP"

# Integrity check: a truncated dump must fail loudly.
gzip -t "$DEST/db-$STAMP.sql.gz"

if [[ -n "${FF_BACKUP_REMOTE:-}" ]]; then
  echo "[$(date -Is)] Copying to $FF_BACKUP_REMOTE"
  rclone copy "$DEST" "$FF_BACKUP_REMOTE" --include "*-$STAMP*"
fi

find "$DEST" -type f -mtime +"$KEEP_DAYS" -delete
echo "[$(date -Is)] Backup complete: $(du -sh "$DEST" | cut -f1) on disk"
