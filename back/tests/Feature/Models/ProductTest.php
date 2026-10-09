<?php

namespace Tests\Feature\Models;

use App\Models\Lot;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del modelo Product.
 *
 * Cada metodo cubre una responsabilidad distinta del modelo: generacion de
 * identificador, valores por defecto, casts, relaciones y auditoria.
 */
class ProductTest extends TestCase
{
    use RefreshDatabase;

    /** El identificador es un UUID generado por el modelo, no por el cliente. */
    public function test_el_identificador_se_genera_solo(): void
    {
        $producto = Product::factory()->create();

        $this->assertNotEmpty($producto->id);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($producto->id));
    }

    /**
     * Regresion: un producto recien creado debe quedar activo.
     *
     * El bug original venia de StoreProductRequest, que forzaba
     * 'status' => (bool) null cuando el formulario no enviaba el campo, y
     * dejaba el producto inactivo e invisible para el selector de lotes.
     */
    public function test_un_producto_nace_activo(): void
    {
        $producto = Product::factory()->create();

        $this->assertTrue($producto->status);
        $this->assertTrue($producto->fresh()->status);
    }

    public function test_status_se_castea_a_booleano(): void
    {
        $activo = Product::factory()->create(['status' => 1]);
        $inactivo = Product::factory()->inactivo()->create();

        $this->assertIsBool($activo->status);
        $this->assertIsBool($inactivo->status);
        $this->assertFalse($inactivo->fresh()->status);
    }

    /** Las claves foraneas se castean a string para no romper el JSON. */
    public function test_las_claves_foraneas_se_castean_a_string(): void
    {
        $producto = Product::factory()->create();

        $this->assertIsString($producto->unit_id);
        $this->assertIsString($producto->group_id);
    }

    public function test_pertenece_a_un_grupo_y_a_una_unidad(): void
    {
        $producto = Product::factory()->create();

        $this->assertInstanceOf(Unit::class, $producto->unit);
        $this->assertNotNull($producto->group);
    }

    /**
     * Regresion: la relacion lots() no existia y hacia fallar con
     * "Call to undefined method" tanto el toggle de estado como el borrado.
     */
    public function test_la_relacion_lotes_devuelve_sus_lotes(): void
    {
        $producto = Product::factory()->create();
        $this->assertCount(0, $producto->lots);

        Lot::factory()->count(2)->create(['product_id' => $producto->id]);

        $this->assertCount(2, $producto->fresh()->lots);
    }

    public function test_el_borrado_es_logico_y_el_producto_sigue_en_la_base(): void
    {
        $producto = Product::factory()->create();
        $id = $producto->id;

        $producto->delete();

        $this->assertSoftDeleted('products', ['id' => $id]);
        $this->assertNull(Product::find($id));
        $this->assertNotNull(Product::withTrashed()->find($id));
    }

    public function test_el_grupo_y_la_unidad_deben_existir_en_la_base(): void
    {
        $producto = Product::factory()->create();

        $this->assertDatabaseHas('groups', ['id' => $producto->group_id]);
        $this->assertDatabaseHas('units', ['id' => $producto->unit_id]);
    }
}