# Use a stable PHP-FPM image
# php:8.2-fpm-alpine3.20 is a good stable choice.
# Or php:8.3-fpm-alpine if you need PHP 8.3 features and it's stable.
FROM php:8.2-fpm-alpine3.20

# Install system dependencies and PHP extensions required by Laravel
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    unzip \
    git \
    build-base \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    # Choose your database client and dev libs:
    postgresql-dev \
    # OR for MySQL: mysql-client mysql-dev
    && docker-php-ext-install pdo_pgsql \
    && docker-php-ext-install zip pcntl gd opcache \
    && rm -rf /var/cache/apk/*

# Copy Composer binary from an official Composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory for your application inside the container
WORKDIR /var/www/html

# Copy your entire Laravel application into the container
# This MUST happen before composer install, and it uses the build context
# (which you should set to 'backend/' in Render settings)
COPY . /var/www/html

# Install Composer dependencies -- this creates the 'vendor' directory
RUN composer install --optimize-autoloader

# Run database migrations as part of the build (for free tier workaround)
# IMPORTANT: This assumes your DB is available and simple migrations work at build time.
# For more complex/production setups, an entrypoint script or pre-deploy hook is better.
RUN php artisan migrate --force

RUN apk add --no-cache dos2unix

# Copy the custom entrypoint script
COPY entrypoint.sh /usr/local/bin/entrypoint.sh

RUN dos2unix /usr/local/bin/entrypoint.sh

# Make the entrypoint script executable
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN apk del dos2unix && rm -rf /var/cache/apk/*


RUN ls -la /usr/local/bin/entrypoint.sh
RUN file /usr/local/bin/entrypoint.sh


# Configure Nginx for Laravel's public directory
# Ensure backend/docker/nginx/default.conf exists in your repo
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Configure Supervisord to manage PHP-FPM and Nginx processes
# Ensure backend/docker/supervisor/supervisord.conf exists in your repo
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# Expose port 80, as Nginx will listen on this port
EXPOSE 80

# Command to run Supervisor when the container starts
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]