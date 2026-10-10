<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * El orden importa: UserSeeder resuelve el nombre del rol contra la tabla
     * roles, asi que los roles tienen que existir antes de que se intente
     * crear un usuario.
     *
     * @return void
     */
    public function run()
    {
        // El orden importa por dependencias entre seeders:
        // PermissionSeeder necesita los modulos, RolePermissionSeeder necesita
        // roles y permisos, y UserSeeder resuelve el nombre del rol contra la
        // tabla roles.
        $this->call([
            RoleSeeder::class,
            ModuleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            UnitSeeder::class,
        ]);
    }
}
