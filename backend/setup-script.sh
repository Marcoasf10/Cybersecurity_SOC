#!/bin/sh
set -e

echo "⏳ Waiting for MySQL..."
until php -r "new PDO('mysql:host=${DB_HOST};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" >/dev/null 2>&1; do
  sleep 2
done

echo "✅ MySQL is up"

if [ ! -f storage/.migrated ]; then
  echo "📦 Running migrations..."
  php artisan migrate --force

  if [ "${SEED_DB}" = "true" ]; then
    echo "🌱 Seeding database..."
    php artisan db:seed --force
  fi

  touch storage/.migrated
else
  echo "⚡ Migrations already done, skipping"
fi

if [ ! -f storage/oauth-private.key ]; then
  echo "🔐 Installing Passport..."
  php artisan passport:install --force
fi

exec php artisan serve --host=0.0.0.0 --port=80