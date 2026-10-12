#!/bin/bash
# Convenience script: runs the Laravel backend and the Expo dev server side by side.
# Usage (from the repo root):  ./scripts/dev.sh      Stop both with Ctrl+C.
set -e
cd "$(dirname "$0")/.."

echo "Starting Laravel (web) + Expo (app)..."
(cd web && php artisan serve --host=0.0.0.0 --port=8000) &
LARAVEL_PID=$!
trap 'kill $LARAVEL_PID 2>/dev/null' EXIT INT TERM

(cd app && npx expo start)
wait
