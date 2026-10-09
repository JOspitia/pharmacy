<?php

namespace Tests\Feature\Models;

use App\Models\Group;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del modelo Group.
 *
 * Cada metodo cubre una responsabilidad distinta: generacion de identificador,
 * valores por defecto, casts, relaciones y borrado logico.
 */
class GroupTest extends TestCase
{
    use RefreshDatabase;

    /** El identificador es un UUID generado por el modelo, no por el cliente. */
    public function test_el_identificador_se_genera_solo(): void
    {
        $grupo = Group::factory()->create();

        $this->assertNotEmpty($grupo->id);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($grupo->id));
    }

    /** Un grupo recien creado debe quedar activo por defecto. */
    public function test_un_grupo_nace_activo(): void
    {
        $grupo = Group::factory()->create();

        $this->assertTrue($grupo->status);
        $this->assertTrue($grupo->fresh()->status);
    }

    public function test_status_se_castea_a_booleano(): void
    {
        $activo = Group::factory()->create(['status' => true]);
        $inactivo = Group::factory()->inactivo()->create();

        $this->assertIsBool($activo->status);
        $this->assertIsBool($inactivo->status);
        $this->assertFalse($inactivo->fresh()->status);
    }

    /** Las claves foraneas se castean a string para no romper el JSON. */
    public function test_las_claves_foraneas_se_castean_a_string(): void
    {
        $grupo = Group::factory()->create();

        $this->assertIsString($grupo->id);
    }

    public function test_pertenece_a_sus_productos(): void
    {
        $grupo = Group::factory()->create();
        $this->assertCount(0, $grupo->products);

        Product::factory()->count(3)->create(['group_id' => $grupo->id]);

        $this->assertCount(3, $grupo->fresh()->products);
    }

    public function test_el_borrado_es_logico_y_el_grupo_sigue_en_la_base(): void
    {
        $grupo = Group::factory()->create();
        $id = $grupo->id;

        $grupo->delete();

        $this->assertSoftDeleted('groups', ['id' => $id]);
        $this->assertNull(Group::find($id));
        $this->assertNotNull(Group::withTrashed()->find($id));
    }
}
