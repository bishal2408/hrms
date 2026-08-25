#!/usr/bin/env bash
set -euo pipefail

# Render injects the real $PORT at runtime — this is deliberately not baked
# into the image at build time (see Dockerfile's comment on why config
# caching happens here, not during the build).
export PORT="${PORT:-8080}"

# envsubst restricted to '${PORT}' only: nginx's own directives use $uri,
# $query_string, $document_root, $fastcgi_script_name (no braces) — an
# unrestricted envsubst would substitute those too, silently replacing them
# with empty strings and breaking every route.
envsubst '${PORT}' < /etc/nginx/templates/nginx.conf.template > /etc/nginx/nginx.conf

echo "[entrypoint] Caching config/routes/views against the real runtime environment..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan storage:link || true

echo "[entrypoint] Waiting for the database..."
db_ready=0
for _ in $(seq 1 30); do
    if php artisan db:show > /dev/null 2>&1; then
        db_ready=1
        break
    fi
    sleep 2
done
if [ "$db_ready" -ne 1 ]; then
    echo "[entrypoint] Database not reachable after 60s — starting anyway; migrate will report the real error." >&2
fi

echo "[entrypoint] Running migrations..."
php artisan migrate --force

echo "[entrypoint] Boot complete, starting php-fpm + nginx."
exec "$@"
