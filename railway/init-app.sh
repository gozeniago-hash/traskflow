#!/bin/sh
# Railway pre-deploy step: runs once per deploy before the new version goes live.
set -e

php artisan migrate --force

# Seed demo data only on the very first deploy (when no users exist yet).
if [ "$SEED_DEMO_DATA" = "true" ]; then
  php artisan db:seed --force --class=Database\\Seeders\\DemoSeederOnce
fi

php artisan optimize:clear
php artisan optimize
php artisan storage:link || true
