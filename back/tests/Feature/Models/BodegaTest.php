<?php

namespace Tests\Feature\Models;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del modelo Bodega.
 *
 * Cada metodo cubre una responsabilidad distinta: generacion de identificador,
 * valores por defecto, casts, relaciones y borrado logico.
 */
class BodegaTest extends TestCase
{
    use RefreshDatabase;

    /** El identificador es un UUID generado por el modelo, no por el cliente. */
    public function test_el_identificador_se_genera_solo(): void
    {
        $bodega = Bodega::factory()->create();

        $this->assertNotEmpty($bodega->id);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($bodega->id));
    }

    /** Una bodega recien creada debe quedar activa por defecto. */
    public function test_una_bodega_nace_activa(): void
    {
        $bodega = Bodega::factory()->create();

        $this->assertTrue($bodega->status);
        $this->assertTrue($bodega->fresh()->status);
    }

    public function test_status_se_castea_a_booleano(): void
    {
        $activa = Bodega::factory()->create(['status' => true]);
        $inactiva = Bodega::factory()->inactivo()->create();

        $this->assertIsBool($activa->status);
        $this->assertIsBool($inactiva->status);
        $this->assertFalse($inactiva->fresh()->status);
    }

    /** Las claves foraneas se castean a string para no romper el JSON. */
    public function test_las_claves_foraneas_se_castean_a_string(): void
    {
        $bodega = Bodega::factory()->create();

        $this->assertIsString($bodega->id);
    }

    public function test_la_relacion_lots_devuelve_sus_lotes(): void
    {
        $bodega = Bodega::factory()->create();
        $this->assertCount(0, $bodega->lots);

        // HU-08: un lote ya no tiene bodega. La relacion bodega -> lotes pasa por
        // inventories: la bodega tiene existencias y cada existencia apunta a
        // un lote. Por eso se crean existencias, no lotes sueltos.
        $lote = Lot::factory()->create();
        $lote2 = Lot::factory()->create();
        Inventory::factory()->create(['warehouse_id' => $bodega->id, 'lot_id' => $lote->id, 'product_id' => $lote->product_id]);
        Inventory::factory()->create(['warehouse_id' => $bodega->id, 'lot_id' => $lote2->id, 'product_id' => $lote2->product_id]);

        $this->assertCount(2, $bodega->fresh()->lots);
    }

    public function test_el_borrado_es_logico_y_la_bodega_sigue_en_la_base(): void
    {
        $bodega = Bodega::factory()->create();
        $id = $bodega->id;

        $bodega->delete();

        $this->assertSoftDeleted('bodegas', ['id' => $id]);
        $this->assertNull(Bodega::find($id));
        $this->assertNotNull(Bodega::withTrashed()->find($id));
    }
}
