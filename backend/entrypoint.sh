#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

echo "Running post-startup database seeding..."
php artisan db:seed --force

echo "Starting Supervisor..."
# Execute the original CMD command (Supervisor)
exec supervisord -c /etc/supervisord.conf