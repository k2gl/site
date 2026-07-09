# The tools API: a FrankenPHP container running the k2gl packages themselves.
# Dependencies resolve in the composer image (it has zip/unzip; the vendor tree
# is pure PHP, so it ports over cleanly); the runtime ships no extra extensions —
# everything the packages need (openssl, sodium, hash, json) is in the base.
FROM composer:2 AS deps

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --ignore-platform-reqs

FROM dunglas/frankenphp:1-php8.5

RUN php -m | grep -qi openssl && php -m | grep -qi sodium

WORKDIR /app
COPY --from=deps /app/vendor vendor/
COPY public/ public/
COPY src/ src/
COPY resources/ resources/
COPY Caddyfile /etc/frankenphp/Caddyfile
