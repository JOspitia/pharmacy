<?php

/**
 * Bootstrap de las pruebas.
 *
 * Se ejecuta antes de que Laravel arranque y fija la base de datos de pruebas
 * de forma explicita, sobrescribiendo la que llega por el entorno.
 *
 * Por que no alcanza con <env name="DB_DATABASE" .../> en phpunit.xml: dentro
 * de Docker esa variable ya viene definida por el env_file del compose, y el
 * cargador de entorno de Laravel es inmutable, asi que no la sobreescribe.
 * Tampoco sirve cambiarla en .env.testing por la misma razon.
 *
 * Por que importa: las pruebas usan RefreshDatabase, que ejecuta
 * migrate:fresh. Si apuntaran a la base de la aplicacion, cada corrida de
 * pruebas borraria los datos de trabajo y el usuario de administrador.
 */

$_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'mysql';
$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = 'farmacia_testing';

putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=farmacia_testing');

require __DIR__ . '/../vendor/autoload.php';