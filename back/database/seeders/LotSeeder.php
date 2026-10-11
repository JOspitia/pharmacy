<?php

namespace Database\Seeders;

use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Lotes sinteticos para los 6 productos del ProductSeeder.
 *
 * Por cada producto se crean 3 lotes con perfiles de vencimiento pensados
 * para ejercitar la pantalla de Kardex del frontend:
 *
 *   - Uno YA VENCIDO (expiration_date < today) -> aparece en alerts.expired
 *     y rechaza ENTRY con 422 por la validacion KE-09.
 *   - Uno POR VENCER (<= 30 dias) -> aparece en alerts.expiring_soon.
 *   - Uno VIGENTE (>= 60 dias) -> estado "ok", sin alerta.
 *
 * Asi el evaluador puede ver las 3 ramas del semaforo de vencimiento en
 * /inventario sin tener que esperar el tiempo real ni correr el seeder
 * varias veces.
 *
 * Los codigos de lote (LOTE-2026-XXX) son los que se muestran en el
 * selector del Kardex; son faciles de identificar al validar la UI.
 */
class LotSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('name', 'admin')->first();
        if (! $admin) {
            throw new \RuntimeException(
                'LotSeeder necesita el usuario admin del UserSeeder.'
            );
        }

        $productos = Product::all();

        if ($productos->isEmpty()) {
            throw new \RuntimeException(
                'LotSeeder necesita productos del ProductSeeder. '
                . 'Corre ProductSeeder antes que este seeder.'
            );
        }

        $contador = 0;
        foreach ($productos as $producto) {
            // Prefijo por producto para que los codigos sean reconocibles
            // al validar el frontend (ej. AC-001, AC-002 para Acetaminofen).
            $prefijo = strtoupper(substr($producto->name, 0, 2));

            $lotes = [
                // 1) Ya vencido: hace 5 dias (para que aparezca en alerts.expired)
                [
                    'name' => "LOTE-2026-{$prefijo}-VENC",
                    'product_id' => $producto->id,
                    'expiration_date' => now()->subDays(5)->format('Y-m-d'),
                    'description' => 'Lote vencido para validar alerts.expired y rechazo de ENTRY.',
                    'status' => true,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
                // 2) Por vencer: 20 dias (aparece en alerts.expiring_soon)
                [
                    'name' => "LOTE-2026-{$prefijo}-PROX",
                    'product_id' => $producto->id,
                    'expiration_date' => now()->addDays(20)->format('Y-m-d'),
                    'description' => 'Lote proximo a vencer (20d) para validar alerts.expiring_soon.',
                    'status' => true,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
                // 3) Vigente: 180 dias (estado "ok", sin alerta)
                [
                    'name' => "LOTE-2026-{$prefijo}-OK",
                    'product_id' => $producto->id,
                    'expiration_date' => now()->addDays(180)->format('Y-m-d'),
                    'description' => 'Lote vigente (180d) como caso base sin alerta.',
                    'status' => true,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            ];

            foreach ($lotes as $lote) {
                Lot::updateOrCreate(
                    ['name' => $lote['name']],
                    $lote,
                );
                $contador++;
            }
        }

        $this->command?->info("LotSeeder: {$contador} lotes creados/actualizados.");
    }
}
