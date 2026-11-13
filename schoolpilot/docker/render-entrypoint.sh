#!/bin/sh
set -eu

cd /var/www/html

: "${APP_KEY:?APP_KEY must be configured in Render environment variables}"

CENTRAL_DB="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
TENANT_DB_DIR="${SCHOOLPILOT_TENANT_DATABASE_PATH:-/var/www/html/database/tenants}"
DEMO_MODE="${SCHOOLPILOT_DEMO_MODE:-false}"

case "$CENTRAL_DB" in
    /*) ;;
    *) echo "DB_DATABASE must be an absolute path in the Render container." >&2; exit 1 ;;
esac

case "$TENANT_DB_DIR" in
    /*) ;;
    *) echo "SCHOOLPILOT_TENANT_DATABASE_PATH must be an absolute path in the Render container." >&2; exit 1 ;;
esac

mkdir -p "$(dirname "$CENTRAL_DB")" "$TENANT_DB_DIR" \
    storage/framework/cache/data storage/framework/cache/locks \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
touch "$CENTRAL_DB"

# Configure Laravel before running migrations and provisioning commands.
php artisan config:cache
php artisan migrate --database=central_sqlite --force

if [ "$DEMO_MODE" = "true" ] || [ "$DEMO_MODE" = "1" ]; then
    : "${SCHOOLPILOT_DEMO_ADMIN_PASSWORD:?SCHOOLPILOT_DEMO_ADMIN_PASSWORD is required when demo mode is enabled}"
    php artisan schoolpilot:demo-setup
fi

# Apache/PHP runs as www-data. SQLite needs write access to the containing
# directories as well as the database files for journal/WAL sidecar files.
chown -R www-data:www-data "$(dirname "$CENTRAL_DB")" "$TENANT_DB_DIR" storage bootstrap/cache
find "$(dirname "$CENTRAL_DB")" "$TENANT_DB_DIR" -type d -exec chmod 0750 {} +
find "$(dirname "$CENTRAL_DB")" "$TENANT_DB_DIR" -type f -name '*.sqlite' -exec chmod 0640 {} +

exec "$@"
