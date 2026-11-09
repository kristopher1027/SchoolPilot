#!/bin/sh
set -eu

cd /var/www/html

: "${APP_KEY:?APP_KEY must be configured in Render environment variables}"
: "${SCHOOLPILOT_DEMO_ADMIN_PASSWORD:?SCHOOLPILOT_DEMO_ADMIN_PASSWORD must be configured in Render environment variables}"

mkdir -p database/tenants storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
touch "${DB_DATABASE:-/var/www/html/database/database.sqlite}"

php artisan config:cache
php artisan migrate --database=central_sqlite --force
php artisan schoolpilot:demo-setup

exec "$@"
