FROM php:8.2-fpm

# ── System dependencies ────────────────────────────────────────────────────────
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

# ── PHP extensions ─────────────────────────────────────────────────────────────
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# ── Composer ───────────────────────────────────────────────────────────────────
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ── Working directory ──────────────────────────────────────────────────────────
WORKDIR /var/www

# ── Copy application files ─────────────────────────────────────────────────────
COPY . .

# ── Install PHP dependencies ───────────────────────────────────────────────────
RUN composer install \
    --no-interaction \
    --no-dev \
    --optimize-autoloader \
    --ignore-platform-reqs

# ── Set permissions ────────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# ── Run as www-data ────────────────────────────────────────────────────────────
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
