# Imagen del backend (Laravel 9 / PHP 8.2).
# Se elige php:8.2-cli y no nginx + php-fpm porque para la evaluacion se sirve
# con `php artisan serve`, que evita depender de un servidor web adicional y de
# su configuracion. Queda registrado como supuesto en el README.

FROM php:8.2-cli

# Extensiones que Laravel 9 y el driver MySQL necesitan y que la imagen base
# no trae compiladas.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer binario, copiado desde su propia imagen oficial.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Se copian primero solo los manifiestos para que la capa de dependencias
# quede cacheada y no se reinstale en cada cambio de codigo.
COPY composer.json composer.lock ./
RUN composer install \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY . .

RUN composer dump-autoload --optimize

# El servidor escucha en 0.0.0.0 para que sea alcanzable desde el exterior
# del contenedor; si escucha en localhost el puerto publicado no responde.
EXPOSE 8000

# Espera a que MySQL acepte conexiones, aplica migraciones y seeders, y levanta
# el servidor. `migrate --force` es necesario porque en un contenedor APP_ENV
# no es "local" y Laravel pediria confirmacion interactiva.
CMD ["sh", "-c", "\
    until php -r 'exit(@fsockopen(getenv(\"DB_HOST\"), (int) getenv(\"DB_PORT\")) ? 0 : 1);' 2>/dev/null; do \
        echo \"Esperando a la base de datos...\"; sleep 2; \
    done; \
    php artisan migrate --force --seed; \
    php artisan serve --host=0.0.0.0 --port=8000\
"]
