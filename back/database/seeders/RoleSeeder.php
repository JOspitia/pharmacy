<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Catalogo de roles del sistema.
 *
 * updateOrCreate y no create para que el seeder sea idempotente y se pueda
 * correr mas de una vez sobre la misma base, que es el requisito de "datos
 * semilla automaticos y repetibles".
 *
 * Los mismos cinco roles que tenia el ENUM de users.role. La migracion
 * 2026_10_09_000008 tambien los inserta: lo hace para poder resolver los
 * usuarios en una base nueva, donde este seeder todavia no ha corrido. Como
 * los dos usan el mismo criterio, correr este seeder despues no duplica nada.
 */
class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            [
                'name' => 'admin',
                'description' => 'Administrador del sistema',
            ],
            [
                'name' => 'regente_farmacia',
                'description' => 'Regente de farmacia',
            ],
            [
                'name' => 'auxiliar_farmacia',
                'description' => 'Auxiliar de farmacia',
            ],
            [
                'name' => 'medico',
                'description' => 'Medico',
            ],
            [
                'name' => 'auditor',
                'description' => 'Auditor',
            ],
        ];

        foreach ($roles as $rol) {
            Role::updateOrCreate(
                ['name' => $rol['name']],
                ['description' => $rol['description']]
            );
        }
    }
}