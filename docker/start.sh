#!/bin/sh
# Container entrypoint: prepare runtime storage, then serve the app on the port Hugging Face expects.
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Add it to the host's environment variables (e.g. Render > Environment)." >&2
    exit 1
fi

mkdir -p storage/app/private/tailored-cvs storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache
touch database/database.sqlite

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Daily cleanup of old tailored CVs (cv:prune).
php artisan schedule:work > /dev/null 2>&1 &

# Render assigns $PORT; Hugging Face Spaces expects 7860.
exec php artisan serve --host=0.0.0.0 --port="${PORT:-7860}"
