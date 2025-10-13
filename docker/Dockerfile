# PHP 8.4 (Alpine) Dockerfile for Laravel
FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    shadow \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libxpm-dev \
    freetype-dev \
    icu-dev \
    zlib-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    sqlite-dev \
    libxml2-dev \
    g++ \
    make \
    autoconf \
    gcc \
    gmp-dev \
    openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
    && docker-php-ext-install -j$(nproc) gd intl pdo pdo_mysql pdo_pgsql pdo_sqlite zip bcmath opcache xml gmp

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Install Larastan and PHP CS Fixer globally
RUN composer global require nunomaduro/larastan friendsofphp/php-cs-fixer --prefer-dist --no-interaction

# Add Composer global bin to PATH
ENV PATH="/root/.composer/vendor/bin:$PATH"

# Install Node.js and npm for frontend asset building
RUN apk add --no-cache nodejs npm

ENV APPUSER=feedarium

# Create non-root user with home and bash shell, set permissions
RUN adduser -D -u 1000 -h /home/"$APPUSER" -s /bin/bash "$APPUSER" \
    && mkdir -p /var/www/html \
    && chown -R "$APPUSER":"$APPUSER" /var/www/html

WORKDIR /var/www/html

USER "$APPUSER"

CMD ["php-fpm"]
