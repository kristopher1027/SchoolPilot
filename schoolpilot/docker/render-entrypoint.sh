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

# Tenant databases are created by the root startup process, but Apache/PHP
# serves requests as www-data. Hand ownership over before Apache starts so
# SQLite can open and update tenant databases (including its journal files).
chown -R www-data:www-data database/tenants
find database/tenants -type d -exec chmod 0750 {} +
find database/tenants -type f -name '*.sqlite' -exec chmod 0640 {} +

exec "$@"
