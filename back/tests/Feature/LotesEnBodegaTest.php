<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del endpoint GET /api/bodegas/{bodega}/lots (HU-08, CA-12).
 *
 * Alimenta el selector de lote del formulario de Kardex.
 */
class LotesEnBodegaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\ModuleSeeder::class,
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\RolePermissionSeeder::class,
        ]);
    }

    private function regente(): User
    {
        return User::factory()->create([
            'role_id' => \App\Models\Role::where('name', 'regente_farmacia')->first()?->id,
        ]);
    }

    public function test_lotes_de_bodega_devuelve_los_que_tienen_existencia(): void
    {
        $bodegaA = Bodega::factory()->create();
        $bodegaB = Bodega::factory()->create();

        // Lote con stock en bodega A: debe aparecer.
        $loteA = Lot::factory()->create(['expiration_date' => now()->addDays(30)]);
        Inventory::factory()->create([
            'warehouse_id' => $bodegaA->id,
            'lot_id' => $loteA->id,
            'product_id' => $loteA->product_id,
            'quantity' => 50,
        ]);

        // Lote con stock en bodega B: NO debe aparecer cuando consulto A.
        $loteB = Lot::factory()->create(['expiration_date' => now()->addDays(60)]);
        Inventory::factory()->create([
            'warehouse_id' => $bodegaB->id,
            'lot_id' => $loteB->id,
            'product_id' => $loteB->product_id,
            'quantity' => 100,
        ]);

        $response = $this->actingAs($this->regente(), 'sanctum')
            ->getJson("/api/bodegas/{$bodegaA->id}/lots");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(strtolower($loteA->id), strtolower($data[0]['id']));
        $this->assertSame(50, $data[0]['stock']);
    }

    public function test_lotes_con_stock_cero_se_incluyen_para_reabastecer(): void
    {
        // El selector del Kardex debe mostrar lotes con stock 0 para que
        // el regente pueda registrar una ENTRY sobre un lote vacio.
        // La UI los distingue con un Tag "Sin stock" y el backend rechaza
        // TRANSFER_OUT/DISPENSATION sobre stock 0 con StockInsuficienteException.
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create();
        Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'quantity' => 0,
        ]);

        $response = $this->actingAs($this->regente(), 'sanctum')
            ->getJson("/api/bodegas/{$bodega->id}/lots");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(strtolower($lote->id), strtolower($data[0]['id']));
        $this->assertSame(0, $data[0]['stock']);
    }

    public function test_lotes_sin_existencia_en_bodega_se_excluyen(): void
    {
        // Un lote con stock en bodega B NO debe aparecer cuando consulto A,
        // aunque su stock sea 0 o positivo.
        $bodegaA = Bodega::factory()->create();
        $bodegaB = Bodega::factory()->create();

        $lote = Lot::factory()->create();
        Inventory::factory()->create([
            'warehouse_id' => $bodegaB->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'quantity' => 0,
        ]);

        $response = $this->actingAs($this->regente(), 'sanctum')
            ->getJson("/api/bodegas/{$bodegaA->id}/lots");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_lotes_se_ordenan_fefo(): void
    {
        $bodega = Bodega::factory()->create();

        $lote120 = Lot::factory()->create(['expiration_date' => now()->addDays(120)]);
        $lote10 = Lot::factory()->create(['expiration_date' => now()->addDays(10)]);
        $lote45 = Lot::factory()->create(['expiration_date' => now()->addDays(45)]);

        foreach ([$lote10, $lote45, $lote120] as $lote) {
            Inventory::factory()->create([
                'warehouse_id' => $bodega->id,
                'lot_id' => $lote->id,
                'product_id' => $lote->product_id,
                'quantity' => 5,
            ]);
        }

        $response = $this->actingAs($this->regente(), 'sanctum')
            ->getJson("/api/bodegas/{$bodega->id}/lots");

        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertSame(strtolower($lote10->id), strtolower($data[0]['id']));
        $this->assertSame(strtolower($lote45->id), strtolower($data[1]['id']));
        $this->assertSame(strtolower($lote120->id), strtolower($data[2]['id']));
    }
}
