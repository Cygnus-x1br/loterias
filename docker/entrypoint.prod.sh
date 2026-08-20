#!/bin/sh

# Roda as migrations (pode dar erro na primeira vez se o DB ainda estiver subindo, o restart always resolve)
php artisan migrate --force

# Limpa e otimiza os caches do Laravel para produção
php artisan optimize:clear
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

# Inicia o PHP-FPM em background
php-fpm -D

# Inicia o Nginx em foreground para manter o container rodando
nginx -g "daemon off;"
