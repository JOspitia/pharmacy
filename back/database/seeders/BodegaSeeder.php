<?php

namespace Database\Seeders;

use App\Models\Bodega;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tres bodegas del enunciado (seccion 6 del brief: "datos semilla").
 *
 * Nombres descriptivos para que la pantalla de Kardex los muestre
 * reconocibles al validar el frontend.
 */
class BodegaSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('name', 'admin')->first();
        if (! $admin) {
            throw new \RuntimeException(
                'BodegaSeeder necesita el usuario admin del UserSeeder. '
                . 'Corre UserSeeder antes que este seeder.'
            );
        }

        $bodegas = [
            [
                'name' => 'Farmacia Central',
                'description' => 'Bodega principal del hospital. Stock general de medicamentos.',
                'status' => true,
                'stock_minimo' => 50,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
            [
                'name' => 'Farmacia de Urgencias',
                'description' => 'Stock critico para atencion inmediata. Lotes de alta rotacion.',
                'status' => true,
                'stock_minimo' => 20,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
            [
                'name' => 'Bodega Hospitalizacion',
                'description' => 'Reserva para pacientes internados. Stock consolidado.',
                'status' => true,
                'stock_minimo' => 30,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        ];

        foreach ($bodegas as $bodega) {
            Bodega::updateOrCreate(
                ['name' => $bodega['name']],
                $bodega,
            );
        }
    }
}
