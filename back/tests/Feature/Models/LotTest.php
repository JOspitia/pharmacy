<?php

namespace Tests\Feature\Models;

use App\Models\Bodega;
use App\Models\Lot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pruebas del modelo Lot.
 *
 * El foco principal es el cast de stock: sin el, SQL Server devolvia los
 * enteros como string y el validador del frontend rechazaba el formulario
 * al editar un lote.
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
     * Contrato de la API: stock siempre como entero.
     *
     * El cast del modelo es lo que garantiza esto con cualquier motor. Sobre
     * MySQL el driver de PDO ya devuelve int de forma nativa, asi que esta
     * prueba pasa incluso sin el cast; el fallo original que motivó el cast
     * ocurria con SQL Server, cuyo driver devuelve los enteros como string.
     * El test fija el contrato que el frontend consume, no la causa concreta.
     */
    public function test_stock_se_castea_a_entero(): void
    {
        $lote = Lot::factory()->create(['stock' => 10]);

        $this->assertIsInt($lote->stock);
        $this->assertSame(10, $lote->stock);
        $this->assertIsInt($lote->fresh()->stock);
    }

    /**
     * El cero tambien debe venir como entero. Un 0 que llega como null o false
     *rompe la regla de dominio "stock > 0" y con ella el bloqueo de borrado.
     */
    public function test_stock_cero_se_conserva_como_entero(): void
    {
        $lote = Lot::factory()->sinStock()->create();

        $this->assertIsInt($lote->stock);
        $this->assertSame(0, $lote->stock);
        $this->assertSame(0, $lote->fresh()->stock);
    }

    public function test_expiration_date_se_castea_a_fecha(): void
    {
        $lote = Lot::factory()->create(['expiration_date' => '2027-06-30']);

        $this->assertInstanceOf(Carbon::class, $lote->expiration_date);
        $this->assertSame('2027-06-30', $lote->expiration_date->toDateString());
    }

    public function test_pertenece_a_un_producto_y_a_una_bodega(): void
    {
        $lote = Lot::factory()->create();

        $this->assertNotNull($lote->product);
        $this->assertInstanceOf(Bodega::class, $lote->warehouse);
    }

    public function test_el_producto_y_la_bodega_deben_existir_en_la_base(): void
    {
        $lote = Lot::factory()->create();

        $this->assertDatabaseHas('products', ['id' => $lote->product_id]);
        $this->assertDatabaseHas('bodegas', ['id' => $lote->warehouse_id]);
    }

    public function test_el_borrado_es_logico(): void
    {
        $lote = Lot::factory()->create();
        $id = $lote->id;

        $lote->delete();

        $this->assertSoftDeleted('lots', ['id' => $id]);
        $this->assertNull(Lot::find($id));
    }

    public function test_las_claves_foraneas_se_castean_a_string(): void
    {
        $lote = Lot::factory()->create();

        $this->assertIsString($lote->product_id);
        $this->assertIsString($lote->warehouse_id);
    }
}