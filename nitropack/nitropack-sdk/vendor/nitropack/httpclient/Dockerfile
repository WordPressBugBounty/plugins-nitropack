FROM php:8.3-apache-bookworm

RUN apt-get update -q \
    && apt-get install git unzip netcat-openbsd -y -q

## Disabling deflate module, so we have complete control over the response.
## If enabled, we can't properly test chunked responses.
RUN a2dismod deflate -f

## Required PHP modules
RUN docker-php-ext-install pcntl \
    && pecl install -f -o brotli && docker-php-ext-enable brotli

RUN mkdir -p -m 0700 ~/.ssh \
    && ssh-keyscan github.com >> ~/.ssh/known_hosts \
    && ssh-keyscan bitbucket.org >> ~/.ssh/known_hosts

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
