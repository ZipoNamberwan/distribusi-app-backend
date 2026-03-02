# Base image using php 8.4 with frankenphp
FROM dunglas/frankenphp:php8.4

# Set timezone to Asia/Jakarta
# ENV TZ=Asia/Jakarta

# Install dependency like git, vim, supervisor and cron
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    libzip-dev \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libonig-dev \
    supervisor \
    cron \
    default-mysql-client \
    && docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath gd zip \
    && apt-get clean

# Set git safe directory
RUN git config --global --add safe.directory /var/www

# Install latest stable Node.js (via nvm) and npm
RUN curl -fsSL https://deb.nodesource.com/setup_current.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy source code, except path inside .dockerignore
COPY --chown=www-data:www-data . /var/www

# Make backup database script executable
RUN chmod +x /var/www/docker/scripts/backup.sh

# Setup supervisord
COPY --chown=www-data:www-data ./docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# Setup cron
COPY ./docker/cron/crontab /etc/cron.d/laravel-cron
RUN chmod 0644 /etc/cron.d/laravel-cron && \
    crontab /etc/cron.d/laravel-cron && \
    touch /var/log/cron.log && \
    chmod 0666 /var/log/cron.log

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN cd /var/www && composer install --optimize-autoloader --no-dev

# Install npm dependencies
RUN cd /var/www && npm install

# Setup Caddy permission
RUN mkdir -p /data/caddy && \
    chown -R www-data:www-data /data/caddy && \
    chmod -R 755 /data/caddy

# Set working directory for every command after this, for example docker exec -it will start from this directory
WORKDIR /var/www
