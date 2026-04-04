FROM php:8.2-fpm

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
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by Laravel
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Create a non-root user for running the application
RUN groupadd -g 1000 appuser \
    && useradd -u 1000 -ms /bin/bash -g appuser appuser

# Set working directory
WORKDIR /var/www/html

# Copy application files and set ownership
COPY --chown=appuser:appuser . .

# Install PHP dependencies (production optimised)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Fix storage & cache permissions
RUN chown -R appuser:appuser /var/www/html/storage \
    && chown -R appuser:appuser /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

USER appuser

EXPOSE 9000

CMD ["php-fpm"]
