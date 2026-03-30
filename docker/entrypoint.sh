#!/bin/sh
# Production entrypoint for Feedarium.
# Runs once per container start before supervisord hands off to the processes.
set -e

cd /var/www/html

# ── 1. Ensure writable directory structure exists inside mounted volumes ──────
# Docker volumes start empty, so we recreate the framework scaffold on first boot.
mkdir -p \
    storage/logs \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ── 2. Auto-generate and persist APP_KEY when not provided ────────────────────
# The generated key is stored in the storage volume so it survives container
# upgrades.  Set APP_KEY explicitly in your compose file to override this.
KEY_FILE="storage/app_key"

if [ -z "${APP_KEY}" ]; then
    if [ -f "$KEY_FILE" ]; then
        APP_KEY=$(cat "$KEY_FILE")
        export APP_KEY
        echo "[entrypoint] Loaded APP_KEY from ${KEY_FILE}."
    else
        APP_KEY=$(php artisan key:generate --show --no-interaction 2>/dev/null)
        export APP_KEY
        printf '%s' "$APP_KEY" > "$KEY_FILE"
        echo "[entrypoint] Generated new APP_KEY and saved to ${KEY_FILE}."
        echo "[entrypoint] TIP: Set APP_KEY=${APP_KEY} in your compose file to make this explicit."
    fi
fi

# ── 3. Wait for the database and run migrations ───────────────────────────────
# Retries up to 15 times (45 s) to handle slow-starting PostgreSQL containers.
echo "[entrypoint] Running database migrations..."
n=0
until [ "$n" -ge 15 ]; do
    php artisan migrate --force --no-interaction && break
    n=$((n + 1))
    echo "[entrypoint] Migration attempt ${n} failed – retrying in 3 s..."
    sleep 3
done

# ── 4. Cache config / routes / views for production performance ───────────────
echo "[entrypoint] Caching configuration..."
php artisan config:cache  --no-interaction

echo "[entrypoint] Caching routes..."
php artisan route:cache   --no-interaction

echo "[entrypoint] Caching views..."
php artisan view:cache    --no-interaction

# ── 5. Hand off to supervisord (nginx + php-fpm + queue + scheduler) ──────────
echo "[entrypoint] Starting services..."
exec "$@"

