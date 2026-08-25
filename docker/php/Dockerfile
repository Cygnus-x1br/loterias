# docker/php/Dockerfile
FROM php:8.3-fpm

ARG UID=1000
ARG GID=1000

# Extensões exigidas pelo Laravel
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update && apt-get install -y git curl unzip zip \
    && install-php-extensions pdo_mysql mbstring exif pcntl bcmath gd zip redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Usuário não-root com o mesmo UID/GID do host (WSL),
# para que arquivos criados no bind mount tenham dono correto
RUN groupadd -g ${GID} developer \
    && useradd -u ${UID} -g developer -m developer

# PHP-FPM passa a rodar como esse usuário, não como www-data
RUN sed -i "s/user = www-data/user = developer/g; s/group = www-data/group = developer/g" \
    /usr/local/etc/php-fpm.d/www.conf

USER developer
WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
