FROM sonarsource/sonar-scanner-cli:latest AS sonar-scanner

FROM php:8.4-fpm-alpine

ARG RDKAFKA_VERSION=6.0.5

RUN apk add --no-cache \
        nodejs \
        npm \
        icu-dev \
        libpq-dev \
        librdkafka \
        librdkafka-dev \
        bash \
        openjdk17-jre

RUN docker-php-ext-install \
        pdo_mysql \
        pdo_pgsql \
        intl

# Исходники лежат в php/rdkafka-6.0.5.tar.gz — скачайте один раз на хосте:
#   bash php/download-rdkafka.sh
COPY rdkafka-${RDKAFKA_VERSION}.tar.gz /tmp/rdkafka.tar.gz

RUN apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS \
    && tar xzf /tmp/rdkafka.tar.gz -C /tmp \
    && cd "/tmp/php-rdkafka-${RDKAFKA_VERSION}" \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable rdkafka \
    && cd / \
    && rm -rf /tmp/rdkafka.tar.gz "/tmp/php-rdkafka-${RDKAFKA_VERSION}" \
    && apk del .phpize-deps

COPY --from=sonar-scanner /opt/sonar-scanner /opt/sonar-scanner

# Встроенный JRE из Debian-образа не работает на Alpine — используем openjdk17-jre из apk.
RUN rm -rf /opt/sonar-scanner/jre

ENV PATH="/opt/sonar-scanner/bin:/usr/bin:${PATH}"
ENV SONAR_USER_HOME=/tmp/sonar-scanner-home

RUN cp "${PHP_INI_DIR}/php.ini-development" "${PHP_INI_DIR}/php.ini" \
    && sed -i 's/max_execution_time = 30/max_execution_time = 180/' "${PHP_INI_DIR}/php.ini"

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN composer global require laravel/installer

WORKDIR /var/www/html

RUN rm -rf /var/cache/apk/*
