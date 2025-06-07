FROM php:8.2-fpm

WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install msgpack \
    && docker-php-ext-enable msgpack \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files first
COPY composer.json composer.lock ./

# Install ALL dependencies including dev
RUN composer install --prefer-dist --no-scripts --no-autoloader

# Copy application files
COPY . .

# Copy and make entrypoint script executable
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Generate optimized autoload files
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
