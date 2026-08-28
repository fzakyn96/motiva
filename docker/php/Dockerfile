FROM dunglas/frankenphp:php8.4

WORKDIR /app

RUN install-php-extensions \
    pdo_sqlsrv \
    sqlsrv \
    redis \
    intl \
    bcmath \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
