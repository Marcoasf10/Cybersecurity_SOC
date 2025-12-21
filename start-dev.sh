#!/bin/bash
set -e

# Copy .env.example if .env does not exist
if [ ! -f /var/www/html/.env ]; then
    echo "Creating .env from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Wait for MySQL
until mysqladmin ping -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" --silent; do
    echo "Waiting for MySQL..."
    sleep 2
done

# Generate application key if missing
php /var/www/html/artisan key:generate --force

# Run migrations and seeders
php /var/www/html/artisan migrate:fresh --seed --force

# Install Passport clients
php /var/www/html/artisan passport:install --force

# Start container
exec /usr/local/bin/start-container "$@"