# docker/php/Dockerfile
FROM php:8.3-fpm

ARG UID=1000
ARG GID=1000

# Extensões exigidas pelo Laravel
RUN apt-get update && apt-get install -y \
    git curl unzip zip libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

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
