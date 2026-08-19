#!/bin/sh
set -e

# Production entrypoint (deployment.md / infrastructure/docker/Dockerfile.prod).
# Container-provided env MUST win over any baked-in .env (Laravel reads .env
# before process env), and we never ship a real .env in the image.

if [ ! -f .env ]; then
    if [ -f .env.production.example ]; then
        cp .env.production.example .env
    else
        : > .env
    fi
    echo "[entrypoint] .env created (empty; container env applies)"
fi

# Override .env with container-provided values for the canonical set.
OVERRIDE_VARS="APP_ENV APP_DEBUG APP_URL APP_KEY DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_SSLMODE SESSION_DRIVER CACHE_STORE QUEUE_CONNECTION LOG_CHANNEL"

for var in $OVERRIDE_VARS; do
    value="$(eval "printf '%s' \"\$${var}\"" 2>/dev/null)"
    if [ -n "$value" ]; then
        if grep -q "^${var}=" .env; then
            sed -i "s|^${var}=.*|${var}=${value}|" .env
        else
            printf '%s=%s\n' "$var" "$value" >> .env
        fi
    fi
done

# APP_KEY is required and secret; fail fast if not provided in production.
if [ -z "$(grep -E '^APP_KEY=.+' .env | head -n1)" ]; then
    echo "[entrypoint] FATAL: APP_KEY is required in production." >&2
    exit 1
fi

# Build the Laravel config/route/event caches with the REAL runtime env applied
# (never frozen at image-build time, so secret-backed config is correct).
php artisan config:cache --ansi || { echo "[entrypoint] config:cache failed" >&2; exit 1; }
php artisan route:cache --ansi || { echo "[entrypoint] route:cache failed" >&2; exit 1; }
php artisan event:cache --ansi || { echo "[entrypoint] event:cache failed" >&2; exit 1; }

# Role dispatch (mirrors deployment.md container roles).
case "$1" in
    artisan)
        shift
        exec php artisan "$@"
        ;;
    migrate)
        exec php artisan migrate --force
        ;;
    queue-worker)
        exec php artisan queue:work --sleep=3 --tries=3 --timeout=90
        ;;
    scheduler)
        exec php artisan schedule:work
        ;;
    *)
        exec php-fpm
        ;;
esac
