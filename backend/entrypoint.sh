#!/bin/sh

# Exit immediately if a command exits with a non-zero status.
set -e

echo "Running database migrations..."
php artisan migrate:fresh --seed --force

echo "Clearing and optimizing Laravel caches..."
php artisan optimize:clear

echo "Starting Supervisor..."
# Execute the original CMD command (Supervisor)
exec supervisord -c /etc/supervisord.conf