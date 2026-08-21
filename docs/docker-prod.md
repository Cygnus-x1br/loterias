# Instruções para Criação do Ambiente de Produção Docker (Laravel 12 / PHP 8.3 / Vite / Livewire)

Documento de referência para replicação da infraestrutura Docker de produção em projetos Laravel com stack similar.

---

### 1. `Dockerfile.prod` (Multi-stage Build)

- **Estágio 1 - Build Frontend:**
  - **NÃO utilize** `node:*-alpine`. Utilize **`node:20-slim`** (base Debian/glibc) para evitar falhas de compilação de binários nativos no Vite/Rolldown/Tailwind com a `musl libc`.
  - Executar: `npm ci && npm run build`.

- **Estágio 2 - Backend (PHP-FPM + Nginx):**
  - Base: `php:8.3-fpm-alpine`.
  - Instalar pacotes de sistema necessários (incluindo bibliotecas de imagem e fontes):
    `nginx zip libzip-dev unzip curl mariadb-client git oniguruma-dev icu-dev libpng-dev libjpeg-turbo-dev freetype-dev`.
  - Configurar e instalar extensões PHP (incluindo `gd`, `intl`, `bcmath`, `pcntl`, `exif`, `zip`, `pdo_mysql`, `mbstring`):
    ```dockerfile
    RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
        && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath intl gd
    ```
  - Copiar Composer binário: `COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer`.
  - Instalar dependências sem pacotes de desenvolvimento:
    `RUN composer install --no-dev --optimize-autoloader --no-interaction`
  - Copiar os assets do estágio de build:
    `COPY --from=frontend-build /app/public/build /var/www/html/public/build`
  - Ajustar permissões para o usuário `www-data` nas pastas `storage` e `bootstrap/cache`.

---

### 2. Nginx de Produção (`docker/nginx/default.prod.conf`)

- **CRÍTICO (Livewire / Assets Dinâmicos):** 
  No bloco de arquivos estáticos (`.(jpg|jpeg|png|gif|ico|css|js)$`), **é obrigatório** incluir o fallback `try_files $uri $uri/ /index.php?$query_string;`.
  *Motivo:* O Livewire serve seu JavaScript (`/livewire/livewire.min.js`) dinamicamente via rota do Laravel. Sem esse fallback, o Nginx retorna 404 e quebra toda a reatividade do frontend.

```nginx
server {
    listen 80;
    index index.php index.html;
    server_name _;
    root /var/www/html/public;

    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires max;
        log_not_found off;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

---

### 3. Script de Inicialização (`docker/entrypoint.prod.sh`)

- Executar migrations com `--force`: `php artisan migrate --force`.
- Otimizar caches de produção:
  ```sh
  php artisan optimize:clear
  php artisan config:cache
  php artisan event:cache
  php artisan route:cache
  php artisan view:cache
  ```
- Subir PHP-FPM em background e Nginx em foreground:
  ```sh
  php-fpm -D
  nginx -g "daemon off;"
  ```

---

### 4. `docker-compose.prod.yml`

- Variáveis de ambiente configuráveis via `.env`:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_KEY=${APP_KEY}`
  - `APP_URL=${APP_URL:-http://localhost:8000}` *(destacar ao usuário que deve corresponder ao IP/domínio real de acesso)*
  - `DB_CONNECTION=mysql`, `DB_HOST=mariadb`, etc.
- Serviço do MariaDB com volume persistente nomeado.

---

### 5. Boas Práticas para Seeders e Formulários em Produção

- **Seeders sem dependência de Faker:** 
  Como em produção rodamos `composer install --no-dev`, o pacote `fakerphp/faker` não existe. Seeders de produção **nunca** devem chamar `Model::factory()`. Devem usar métodos diretos do Eloquent (`Model::updateOrCreate(...)` ou `Model::create(...)`).
- **Tratamento de dados brutos / JSON no `upsert()`:**
  O método `Model::upsert()` ignora os *casts* do Eloquent. Ao importar arrays/JSONs volumosos:
  - Codificar arrays em JSON manualmente (`json_encode($data)`).
  - Truncar datas ISO 8601 para `YYYY-MM-DD` para colunas `DATE` do MySQL.
  - Injetar `created_at` e `updated_at` manualmente no payload.
- **Formulários Blade / Livewire:**
  Sempre declarar explicitamente `method="POST"` e `@csrf` nos formulários de autenticação como fallback de segurança, impedindo que falhas temporárias de rede/JS exponham senhas na URL via GET.
