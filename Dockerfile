FROM php:8.2-apache AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev \
    && docker-php-ext-install -j"$(nproc)" intl mbstring mysqli opcache \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN a2enmod rewrite \
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

RUN mkdir -p writable/cache writable/debugbar writable/logs writable/session writable/uploads \
    && chown -R www-data:www-data writable \
    && chmod -R 775 writable

ENV CI_ENVIRONMENT=production

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r '$s = @fsockopen("127.0.0.1", 3000, $errno, $errstr, 2); if (! $s) { exit(1); } fclose($s);'

CMD ["sh", "-c", "php spark migrate --all && exec apache2-foreground"]
