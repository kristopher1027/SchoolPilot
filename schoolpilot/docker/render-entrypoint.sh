#!/bin/sh
set -eu

cd /var/www/html

: "${APP_KEY:?APP_KEY must be configured in Render environment variables}"

CENTRAL_DB="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
TENANT_DB_DIR="${SCHOOLPILOT_TENANT_DATABASE_PATH:-/var/www/html/database/tenants}"
DEMO_MODE="${SCHOOLPILOT_DEMO_MODE:-false}"
PUBLIC_STORAGE_DIR="${SCHOOLPILOT_PUBLIC_STORAGE_PATH:-/var/data/schoolpilot/public}"

case "$CENTRAL_DB" in
    /*) ;;
    *) echo "DB_DATABASE must be an absolute path in the Render container." >&2; exit 1 ;;
esac

case "$TENANT_DB_DIR" in
    /*) ;;
    *) echo "SCHOOLPILOT_TENANT_DATABASE_PATH must be an absolute path in the Render container." >&2; exit 1 ;;
esac

mkdir -p "$(dirname "$CENTRAL_DB")" "$TENANT_DB_DIR" "$PUBLIC_STORAGE_DIR" \
    storage/framework/cache/data storage/framework/cache/locks \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
touch "$CENTRAL_DB"

# Persist public uploads on the mounted Render disk.
mkdir -p storage/app
if [ -L storage/app/public ]; then
    if [ "$(readlink storage/app/public)" != "$PUBLIC_STORAGE_DIR" ]; then
        rm storage/app/public
    fi
elif [ -d storage/app/public ]; then
    cp -a storage/app/public/. "$PUBLIC_STORAGE_DIR"/
    rm -rf storage/app/public
fi
if [ ! -e storage/app/public ]; then
    ln -s "$PUBLIC_STORAGE_DIR" storage/app/public
fi

# Configure Laravel before running migrations and provisioning commands.
php artisan config:cache
php artisan storage:link --force
php artisan migrate --database=central_sqlite --force

if [ "$DEMO_MODE" = "true" ] || [ "$DEMO_MODE" = "1" ]; then
    : "${SCHOOLPILOT_DEMO_ADMIN_PASSWORD:?SCHOOLPILOT_DEMO_ADMIN_PASSWORD is required when demo mode is enabled}"
    php artisan schoolpilot:demo-setup
fi

# Apache/PHP runs as www-data. SQLite needs write access to the containing
# directories as well as the database files for journal/WAL sidecar files.
chown -R www-data:www-data "$(dirname "$CENTRAL_DB")" "$TENANT_DB_DIR" "$PUBLIC_STORAGE_DIR" storage bootstrap/cache
find "$(dirname "$CENTRAL_DB")" "$TENANT_DB_DIR" "$PUBLIC_STORAGE_DIR" -type d -exec chmod 0750 {} +
find "$(dirname "$CENTRAL_DB")" "$TENANT_DB_DIR" -type f -name '*.sqlite' -exec chmod 0640 {} +

exec "$@"
