#!/bin/sh
set -e

# Container-provided env (DB_HOST, DB_*, APP_URL, ...) MUST win over any
# checked-out or scaffold-generated .env, because Laravel's env() reads the
# .env file before the process environment.
# AUTH_MAX_ATTEMPTS_PER_MINUTE / API_MAX_REQUESTS_PER_MINUTE are written to
# .env so they also reach the `php artisan serve` child process (Laravel's
# ServeCommand passes only a whitelist of process vars to `php -S`; anything
# that must affect the served app has to live in the .env file).
OVERRIDE_VARS="APP_ENV APP_URL APP_DEBUG DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_SSLMODE AUTH_MAX_ATTEMPTS_PER_MINUTE API_MAX_REQUESTS_PER_MINUTE"

if [ ! -f .env ]; then
    cp .env.example .env
    echo "[entrypoint] .env created from .env.example"
fi

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

if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress --prefer-dist
    echo "[entrypoint] composer dependencies installed"
fi

if [ -z "$(grep -E '^APP_KEY=.+' .env | head -n1)" ]; then
    php artisan key:generate --force
    echo "[entrypoint] APP_KEY generated"
fi

# Default command runs migrations then serves the app (dev profile).
case "$1" in
    artisan)
        shift
        exec php artisan "$@"
        ;;
    composer)
        shift
        exec composer "$@"
        ;;
    migrate)
        exec php artisan migrate --force
        ;;
    *)
        php artisan migrate --force || echo "[entrypoint] migrate failed; continuing"
        exec php artisan serve --host=0.0.0.0 --port=8000
        ;;
esac