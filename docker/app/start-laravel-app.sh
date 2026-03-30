#!/bin/sh

set -eu

app_dir="${APP_DIR:-/var/www/app}"
app_port="${APP_PORT:-8000}"
lock_hash_file="vendor/.composer.lock.sha1"

cd "$app_dir"

if [ ! -f composer.json ]; then
  echo "composer.json not found in $app_dir" >&2
  exit 1
fi

if [ -z "${APP_KEY:-}" ]; then
  export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi

current_lock_hash=""

if [ -f composer.lock ]; then
  current_lock_hash="$(sha1sum composer.lock | awk '{print $1}')"
fi

installed_lock_hash=""

if [ -f "$lock_hash_file" ]; then
  installed_lock_hash="$(cat "$lock_hash_file")"
fi

if [ ! -f vendor/autoload.php ] || [ "$current_lock_hash" != "$installed_lock_hash" ]; then
  composer install --no-interaction --prefer-dist

  if [ -n "$current_lock_hash" ]; then
    mkdir -p "$(dirname "$lock_hash_file")"
    printf '%s' "$current_lock_hash" > "$lock_hash_file"
  fi
fi

php artisan migrate --force --graceful

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

exec php artisan serve --host=0.0.0.0 --port="$app_port"
