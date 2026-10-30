FROM php:8.3-fpm

# ─── Instalar dependências do sistema ───
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    nginx \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ─── Instalar extensões PHP ───
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        fileinfo \
        opcache

# ─── Instalar Composer ───
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ─── Definir diretório de trabalho ───
WORKDIR /var/www/html

# ─── Copiar projeto ───
COPY . .

# ─── Instalar dependências do Laravel ───
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ─── Permissões ───
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# ─── Configurar Nginx (remover defaults e usar o customizado) ───
RUN rm -f /etc/nginx/conf.d/default.conf
RUN rm -f /etc/nginx/sites-enabled/default
COPY nginx.conf /etc/nginx/conf.d/default.conf

# ─── Expor porta ───
EXPOSE 80

# ─── Limpar cache de config e iniciar php-fpm + nginx ───
CMD ["sh", "-c", "php artisan config:clear; php-fpm & nginx -g 'daemon off;'"]