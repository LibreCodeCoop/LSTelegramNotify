ARG LIMESURVEY_STACK_IMAGE=martialblog/limesurvey@sha256:4bfa6cac546fc95de6b26dc9afe118814df7941ebdec3f902f18cfbcd1478452
FROM ${LIMESURVEY_STACK_IMAGE}

USER root

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer-home \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    HOME=/tmp/composer-home

RUN apt-get update \
    && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y --no-install-recommends ca-certificates curl git unzip \
    && mkdir -p "$COMPOSER_HOME" "$COMPOSER_CACHE_DIR" \
    && chmod 1777 /tmp "$COMPOSER_HOME" "$COMPOSER_CACHE_DIR" \
    && git config --system --add safe.directory /var/www/html/plugins/LSTelegramNotify \
    && php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');" \
    && php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-setup.php \
    && composer --version \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
