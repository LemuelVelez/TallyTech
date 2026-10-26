FROM php:8.2-apache AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev libpng-dev libjpeg62-turbo-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" intl mbstring mysqli opcache gd \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN printf '%s\n' 'opcache.enable=1' 'opcache.memory_consumption=192' 'opcache.interned_strings_buffer=16' 'opcache.max_accelerated_files=20000' 'opcache.validate_timestamps=0' > /usr/local/etc/php/conf.d/zz-tallytech-opcache.ini

RUN a2enmod rewrite expires deflate headers \
    && sed -ri -e "s!Listen 80!Listen 3000!g" /etc/apache2/ports.conf \
    && sed -ri -e "s!<VirtualHost \*:80>!<VirtualHost *:3000>!g" /etc/apache2/sites-available/*.conf \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/tallytech.conf \
    && a2enconf tallytech

FROM php-base AS vendor

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

FROM php-base AS runtime

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN install -D -m 0644 public/uploads/team-avatars/.htaccess /usr/local/share/tallytech/team-avatar.htaccess \
    && install -D -m 0644 public/uploads/user-avatars/.htaccess /usr/local/share/tallytech/user-avatar.htaccess \
    && mkdir -p writable/cache writable/debugbar writable/logs writable/session writable/uploads public/uploads/team-avatars public/uploads/user-avatars \
    && chown -R www-data:www-data writable public/uploads/team-avatars public/uploads/user-avatars \
    && chmod -R 775 writable public/uploads/team-avatars public/uploads/user-avatars

# Team and user avatars are runtime data. In Coolify, mount persistent volumes
# at /var/www/html/public/uploads/team-avatars and
# /var/www/html/public/uploads/user-avatars so uploads survive redeploys.

ENV CI_ENVIRONMENT=development

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r '$s = @fsockopen("127.0.0.1", 3000, $errno, $errstr, 2); if (! $s) { exit(1); } fclose($s);'

CMD set -eu; \
    APP_AVATAR_DIR=/var/www/html/public/uploads/team-avatars; \
    USER_AVATAR_DIR=/var/www/html/public/uploads/user-avatars; \
    mkdir -p writable/cache writable/debugbar writable/logs writable/session writable/uploads "$APP_AVATAR_DIR" "$USER_AVATAR_DIR"; \
    cp /usr/local/share/tallytech/team-avatar.htaccess "$APP_AVATAR_DIR/.htaccess"; \
    cp /usr/local/share/tallytech/user-avatar.htaccess "$USER_AVATAR_DIR/.htaccess"; \
    touch "$APP_AVATAR_DIR/.gitkeep" "$USER_AVATAR_DIR/.gitkeep"; \
    chown -R www-data:www-data writable "$APP_AVATAR_DIR" "$USER_AVATAR_DIR"; \
    chmod -R 775 writable "$APP_AVATAR_DIR" "$USER_AVATAR_DIR"; \
    chmod 644 "$APP_AVATAR_DIR/.htaccess" "$USER_AVATAR_DIR/.htaccess"; \
    php spark migrate --all; \
    exec apache2-foreground
