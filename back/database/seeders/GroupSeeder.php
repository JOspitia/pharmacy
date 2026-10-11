<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tres grupos terapeuticos para clasificar los productos del enunciado
 * (seccion 6: "medicamentos con 2 o 3 lotes cada uno").
 *
 * Categorias tipicas de farmacia hospitalaria: analgesicos (uso masivo),
 * antibioticos (control de infeccion) y control especial (receta +
 * autorizacion del regente, RN-05).
 */
class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('name', 'admin')->first();
        if (! $admin) {
            throw new \RuntimeException(
                'GroupSeeder necesita el usuario admin del UserSeeder. '
                . 'Corre UserSeeder antes que este seeder.'
            );
        }

        $grupos = [
            [
                'name' => 'Analgesicos y Antipireticos',
                'description' => 'Para manejo del dolor y fiebre. Uso frecuente en consulta externa y urgencias.',
                'status' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
            [
                'name' => 'Antibioticos',
                'description' => 'Tratamiento de infecciones bacterianas. Rotacion controlada por sensibilidad.',
                'status' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
            [
                'name' => 'Control Especial',
                'description' => 'Medicamentos sujetos a fiscalizacion. Requieren autorizacion del regente (RN-05).',
                'status' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        ];

        foreach ($grupos as $grupo) {
            Group::updateOrCreate(
                ['name' => $grupo['name']],
                $grupo,
            );
        }
    }
}
