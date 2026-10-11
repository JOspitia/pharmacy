<?php

namespace Tests\Feature\Models;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pruebas del modelo Lot.
 *
 * CAMBIO HU-08 (RN-01): el lote ya no tiene stock ni bodega.
 *
 * El stock pertenece a la existencia (bodega x producto x lote). Estas pruebas
 * fijan el contrato nuevo: un lote es un atributo del producto (numero de lote
 * y vencimiento), y el stock se lee sumando sus existencias.
 */
class LotTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_identificador_se_genera_solo(): void
    {
        $lote = Lot::factory()->create();

        $this->assertNotEmpty($lote->id);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($lote->id));
    }

    public function test_un_lote_nace_activo(): void
    {
        $lote = Lot::factory()->create();

        $this->assertTrue($lote->status);
        $this->assertTrue($lote->fresh()->status);
    }

    /**
     * El lote no tiene columna stock: el stock total se calcula sumando las
     * existencias en todas las bodegas (RN-01).
     */
    public function test_el_lote_no_tiene_columna_stock(): void
    {
        $lote = Lot::factory()->create();

        $this->assertArrayNotHasKey('stock', $lote->getAttributes());
    }

    public function test_stock_total_suma_las_existencias_de_todas_las_bodegas(): void
    {
        $lote = Lot::factory()->create();

        Inventory::factory()->conCantidad(30)->create([
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'warehouse_id' => Bodega::factory(),
        ]);
        Inventory::factory()->conCantidad(12)->create([
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'warehouse_id' => Bodega::factory(),
        ]);

        $this->assertSame(42, $lote->stockTotal());
    }

    public function test_stock_total_es_cero_sin_existencias(): void
    {
        $lote = Lot::factory()->create();

        $this->assertSame(0, $lote->stockTotal());
    }

    /**
     * Un lote puede estar en varias bodegas a la vez. Es lo que hace posible un
     * traslado: antes el lote tenia una sola bodega y no habia donde aterrizar
     * la mercaderia en destino.
     */
    public function test_un_lote_puede_estar_en_multiples_bodegas(): void
    {
        $lote = Lot::factory()->create();

        $bodegaA = Bodega::factory()->create();
        $bodegaB = Bodega::factory()->create();

        Inventory::factory()->conCantidad(10)->create([
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'warehouse_id' => $bodegaA->id,
        ]);
        Inventory::factory()->conCantidad(5)->create([
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'warehouse_id' => $bodegaB->id,
        ]);

        $this->assertCount(2, $lote->inventories);

        // SQL Server devuelve los UNIQUEIDENTIFIER en mayusculas, mientras que
        // Eloquent los genera en minusculas. La comparacion va en minusculas a
        // proposito: lo que se verifica es que el lote esta en las dos
        // bodegas, no como el driver formatea el UUID.
        $this->assertEqualsCanonicalizing(
            [strtolower($bodegaA->id), strtolower($bodegaB->id)],
            array_map('strtolower', $lote->inventories->pluck('warehouse_id')->all())
        );
    }

    public function test_expiration_date_se_castea_a_fecha(): void
    {
        $lote = Lot::factory()->create(['expiration_date' => '2027-06-30']);

        $this->assertInstanceOf(Carbon::class, $lote->expiration_date);
        $this->assertSame('2027-06-30', $lote->expiration_date->toDateString());
    }

    public function test_pertenece_a_un_producto(): void
    {
        $lote = Lot::factory()->create();

        $this->assertNotNull($lote->product);
        $this->assertInstanceOf(\App\Models\Product::class, $lote->product);
    }

    public function test_el_producto_debe_existir_en_la_base(): void
    {
        $lote = Lot::factory()->create();

        $this->assertDatabaseHas('products', ['id' => $lote->product_id]);
    }

    /**
     * HU-08: el nombre del lote es unico por producto, no global. Dos productos
     * distintos pueden tener un lote llamado "L-001".
     */
    public function test_dos_productos_pueden_tener_lotes_con_el_mismo_nombre(): void
    {
        $productoA = \App\Models\Product::factory()->create();
        $productoB = \App\Models\Product::factory()->create();

        $loteA = Lot::factory()->create(['name' => 'L-001', 'product_id' => $productoA->id]);
        $loteB = Lot::factory()->create(['name' => 'L-001', 'product_id' => $productoB->id]);

        $this->assertNotSame($loteA->id, $loteB->id);
        $this->assertDatabaseHas('lots', ['name' => 'L-001', 'product_id' => $productoA->id]);
        $this->assertDatabaseHas('lots', ['name' => 'L-001', 'product_id' => $productoB->id]);
    }

    /**
     * El mismo producto no puede repetir nombre de lote.
     */
    public function test_el_mismo_producto_no_puede_repetir_nombre_de_lote(): void
    {
        $producto = \App\Models\Product::factory()->create();
        Lot::factory()->create(['name' => 'L-DUP', 'product_id' => $producto->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Lot::factory()->create(['name' => 'L-DUP', 'product_id' => $producto->id]);
    }
}