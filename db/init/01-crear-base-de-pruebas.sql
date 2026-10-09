-- Crea la base de pruebas separada de la base de la aplicacion.
--
-- Es necesario porque las pruebas usan RefreshDatabase, que ejecuta
-- migrate:fresh y por lo tanto borra todas las tablas. Si apuntaran a la
-- misma base que usa la aplicacion, correr las pruebas destruiria los datos
-- de trabajo y el usuario de administrador sembrado.
--
-- MySQL ejecuta este script solo la primera vez que se inicializa el
-- contenedor, cuando el volumen de datos aun esta vacio.

CREATE DATABASE IF NOT EXISTS `farmacia_testing`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- El usuario de la aplicacion necesita permisos sobre la base de pruebas,
-- porque las pruebas se ejecutan con las mismas credenciales que el backend.
-- Sin este permiso el RefreshDatabase falla con "command denied".
GRANT ALL PRIVILEGES ON `farmacia_testing`.* TO 'farmacia'@'%';
FLUSH PRIVILEGES;