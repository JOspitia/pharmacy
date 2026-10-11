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
        //
        // NOTA: los seeders de demo (Bodega, Group, Product, Lot, Inventory,
        // KardexDemo) NO se ejecutan aca. viven en DemoSeeder y se corren
        // a mano con `php artisan db:seed --class=DemoSeeder` solo en la
        // base de dev. Razon: la mayoria de los tests asumen BD vacia y se
        // rompen si encuentran 3 bodegas preexistentes. CI corre migrate:fresh
        // --seed que invoca ESTE seeder, no DemoSeeder.
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
