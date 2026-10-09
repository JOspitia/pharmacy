<?php

namespace Tests\Feature\Models;

use App\Models\Sensor;
use App\Models\Reading;
use App\Models\Bodega;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del modelo Sensor.
 *
 * Cada metodo cubre una responsabilidad distinta: generacion de identificador,
 * valores por defecto, casts, relaciones y borrado logico.
 */
class SensorTest extends TestCase
{
    use RefreshDatabase;

    /** El identificador es un UUID generado por el modelo, no por el cliente. */
    public function test_el_identificador_se_genera_solo(): void
    {
        $sensor = Sensor::factory()->create();

        $this->assertNotEmpty($sensor->id);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($sensor->id));
    }

    /** Un sensor recien creado debe quedar activo por defecto. */
    public function test_un_sensor_nace_activo(): void
    {
        $sensor = Sensor::factory()->create();

        $this->assertTrue($sensor->status);
        $this->assertTrue($sensor->fresh()->status);
    }

    public function test_status_se_castea_a_booleano(): void
    {
        $activo = Sensor::factory()->create(['status' => true]);
        $inactivo = Sensor::factory()->inactivo()->create();

        $this->assertIsBool($activo->status);
        $this->assertIsBool($inactivo->status);
        $this->assertFalse($inactivo->fresh()->status);
    }

    /** Las claves foraneas se castean a string para no romper el JSON. */
    public function test_las_claves_foraneas_se_castean_a_string(): void
    {
        $sensor = Sensor::factory()->create();

        $this->assertIsString($sensor->id);
        $this->assertIsString($sensor->warehouse_id);
    }

    public function test_pertenece_a_una_bodega(): void
    {
        $sensor = Sensor::factory()->create();

        $this->assertInstanceOf(Bodega::class, $sensor->warehouse);
    }

    public function test_la_relacion_readings_devuelve_sus_lecturas(): void
    {
        $sensor = Sensor::factory()->create();
        $this->assertCount(0, $sensor->readings);

        Reading::factory()->count(4)->create(['sensor_id' => $sensor->id]);

        $this->assertCount(4, $sensor->fresh()->readings);
    }

    public function test_el_borrado_es_logico_y_el_sensor_sigue_en_la_base(): void
    {
        $sensor = Sensor::factory()->create();
        $id = $sensor->id;

        $sensor->delete();

        $this->assertSoftDeleted('sensors', ['id' => $id]);
        $this->assertNull(Sensor::find($id));
        $this->assertNotNull(Sensor::withTrashed()->find($id));
    }
}
