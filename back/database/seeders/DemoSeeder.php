<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Carga los datos de demo para que la app tenga contenido visible al
 * validar el frontend manualmente.
 *
 * NO se ejecuta en CI: la mayoria de los tests asumen BD vacia y se
 * rompen si encuentran 3 bodegas, 6 productos, 18 lotes, 18 existencias
 * y ~30 movimientos preexistentes. CI corre `migrate:fresh --seed` que
 * invoca DatabaseSeeder, no este.
 *
 * Uso en dev:
 *
 *   docker compose exec backend php artisan db:seed --class=DemoSeeder
 *
 * Es idempotente: usa updateOrCreate / firstOrCreate con claves naturales
 * (name, cum, terna warehouse+product+lot), asi que se puede correr
 * varias veces sin duplicar filas.
 *
 * Orden de los call(): respeta las dependencias entre seeders.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BodegaSeeder::class,
            GroupSeeder::class,
            ProductSeeder::class,
            LotSeeder::class,
            InventorySeeder::class,
            KardexDemoSeeder::class,
        ]);
    }
}
