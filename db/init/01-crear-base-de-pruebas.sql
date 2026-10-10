-- Crea la base de pruebas separada de la base de la aplicacion.
--
-- Es necesario porque las pruebas usan RefreshDatabase, que ejecuta
-- migrate:fresh y por lo tanto borra todas las tablas. Si apuntaran a la
-- misma base que usa la aplicacion, correr las pruebas destruiria los datos
-- de trabajo y el usuario de administrador sembrado.
--
-- ANTES: este archivo se montaba en /docker-entrypoint-initdb.d de la imagen
-- de MySQL, que lo ejecuta solo la primera vez que se inicializa el
-- contenedor. SQL Server NO tiene ese punto de extension, asi que ahora la
-- creacion de ambas bases la hace el servicio `db-init` de docker-compose.yml,
-- que corre sqlcmd una vez y termina.
--
-- Se conserva el archivo como documentacion del motivo por el que existen dos
-- bases. La ejecucion real esta en docker-compose.yml.

IF DB_ID('farmacia_testing') IS NULL
    CREATE DATABASE farmacia_testing;
GO