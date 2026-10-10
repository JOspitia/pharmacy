# Imagen del backend (Laravel 9 / PHP 8.2).
# Se elige php:8.2-cli y no nginx + php-fpm porque para la evaluacion se sirve
# con `php artisan serve`, que evita depender de un servidor web adicional y de
# su configuracion. Queda registrado como supuesto en el README.
#
# mirror.gcr.io/library/ es el espejo gratuito de Docker Hub. Se usa porque
# Docker Hub limita las descargas anonimas por IP y los runners de GitHub
# Actions comparten IP, lo que hace fallar el pipeline con HTTP 429.

FROM mirror.gcr.io/library/php:8.2-cli

# Las capas van separadas a proposito: si una falla, el log dice exactamente
# cual fue. En un unico RUN el error real queda enterrado entre la compilacion
# de intl, que es larguisima.
#
# 1) Paquetes base del sistema.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        gnupg \
        ca-certificates \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        unixodbc \
        unixodbc-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2) Repositorio de Microsoft y driver ODBC 18.
#
# ACCEPT_EULA tiene que estar en el entorno del proceso que instala los .deb:
# sin la variable abortan con "The EULA was not accepted" y el fallo aparece
# como un error de dpkg que no dice nada sobre la causa real.
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
       | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list \
       > /etc/apt/sources.list.d/mssql-release.list \
    && sed -i 's|deb \[|deb [signed-by=/usr/share/keyrings/microsoft-prod.gpg |' \
       /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends \
        msodbcsql18 \
        mssql-tools18 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 3) Extensiones de PHP que ya venia instalando el proyecto.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        mbstring \
        bcmath \
        intl \
        zip \
        gd

# 4) Driver de SQL Server.
#
# pdo_sqlsrv y sqlsrv llegan por PECL, no por apt: son la via que Microsoft
# documenta para PHP fuera de Windows, y necesitan las cabeceras de unixODBC
# del paso 1. Los dos se instalan juntos porque pdo_sqlsrv depende de sqlsrv.
#
# LAS VERSIONES ESTAN FIJADAS A 5.12.0 a proposito. Sin el pin, PECL resuelve la
# ultima release, y desde la 5.13 Microsoft exige PHP >= 8.3 mientras que el
# proyecto corre en 8.2. El fallo es críptico: pecl responde "No valid packages
# found" sin decir que el problema es la version de PHP.
RUN ACCEPT_EULA=Y pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Prueba de humo: si pdo_sqlsrv no hubiera quedado bien enlazado, el fallo
# aparece aqui y no mas adelante como "could not find driver" en runtime.
RUN php -r 'exit(extension_loaded("pdo_sqlsrv") ? 0 : 1);'

# Composer binario, copiado desde su propia imagen oficial.
COPY --from=mirror.gcr.io/library/composer:2 /usr/bin/composer /usr/bin/composer

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

# Espera a que SQL Server acepte conexiones, aplica migraciones y seeders, y
# levanta el servidor. `migrate --force` es necesario porque en un contenedor
# APP_ENV no es "local" y Laravel pediria confirmacion interactiva.
CMD ["sh", "-c", "\
    until php -r 'exit(@fsockopen(getenv(\"DB_HOST\"), (int) getenv(\"DB_PORT\")) ? 0 : 1);' 2>/dev/null; do \
        echo \"Esperando a la base de datos...\"; sleep 2; \
    done; \
    php artisan migrate --force --seed; \
    php artisan serve --host=0.0.0.0 --port=8000\
"]
