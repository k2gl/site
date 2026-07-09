# The tools API: a FrankenPHP container running the k2gl packages themselves.
FROM dunglas/frankenphp:1-php8.5 AS deps

RUN install-php-extensions apcu @composer \
 && php -m | grep -qi openssl && php -m | grep -qi sodium

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

FROM dunglas/frankenphp:1-php8.5

RUN install-php-extensions apcu

WORKDIR /app
COPY --from=deps /app/vendor vendor/
COPY public/ public/
COPY src/ src/
COPY resources/ resources/
COPY Caddyfile /etc/frankenphp/Caddyfile
