<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\User;
use App\Services\KardexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Pruebas del Kardex y existencias (HU-08).
 *
 * Cada test cubre un criterio de aceptacion (CA) del documento.
 */
class KardexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Antes de cada test: refrescar DB y correr el seeder de roles y permisos.
     *
     * RefreshDatabase por defecto solo corre migraciones, no seeders. Sin los
     * roles, los helpers crearRegente/crearAuxiliar asignan role_id = null y
     * los usuarios terminan sin permisos, asi que toda verificacion de
     * permisos falla aunque el codigo este bien.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // El orden de los seeders esta fijado en DatabaseSeeder. Si lo
        // desordeno, los FK constraints y las dependencias entre seeders
        // explotan en runtime con errores que dicen "necesita el modulo X".
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\ModuleSeeder::class,
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\RolePermissionSeeder::class,
        ]);
    }

    // -------------------------------------------------------------------------
    // helpers
    // -------------------------------------------------------------------------

    private function crearRegente(): User
    {
        return User::factory()->create([
            'role_id' => \App\Models\Role::where('name', 'regente_farmacia')->first()?->id,
        ]);
    }

    private function crearAuxiliar(): User
    {
        return User::factory()->create([
            'role_id' => \App\Models\Role::where('name', 'auxiliar_farmacia')->first()?->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // CA-01: Registrar entrada de mercancía (regente)
    // -------------------------------------------------------------------------

    public function test_entrada_incrementa_stock_y_crea_movimiento(): void
    {
        $regente = $this->crearRegente();
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create([
            'expiration_date' => now()->addDays(60),
        ]);

        $tipo = MovementType::porNombre('ENTRY');

        // Sin existencia previa
        $this->assertDatabaseMissing('inventories', ['lot_id' => $lote->id]);
        $this->assertDatabaseMissing('inventories', ['warehouse_id' => $bodega->id]);

        // Registrar entrada
        $existencia = app(KardexService::class)->obtenerOCrearExistencia(
            $bodega->id,
            $lote
        );

        $movimiento = app(KardexService::class)->registrarMovimiento(
            $existencia,
            $tipo,
            100,
            $regente,
            null,
            null,
            'Recepcion OC-2026-0042'
        );

        // El stock subio 100
        $existencia->refresh();
        $this->assertSame(100, $existencia->quantity);

        // El movimiento se creo
        $this->assertDatabaseHas('movements', ['id' => $movimiento->id]);
        $this->assertDatabaseHas('movements', ['inventory_id' => $existencia->id]);
        $this->assertDatabaseHas('movements', ['quantity' => 100]);
    }

    // -------------------------------------------------------------------------
    // CA-02: Rechazar entrada de lote vencido
    // -------------------------------------------------------------------------

    public function test_entrada_lote_vencido_es_rechazada_por_validacion(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create([
            'expiration_date' => now()->subDays(1),
        ]);

        $regente = $this->crearRegente();

        // El request completo pasa por la API: si el lote esta vencido, la
        // validacion debe rechazarlo con 422.
        $response = $this->actingAs($regente, 'sanctum')
            ->postJson('/api/movements', [
                'lot_id' => $lote->id,
                'warehouse_id' => $bodega->id,
                'type' => 'ENTRY',
                'quantity' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['lot_id']);
    }

    // -------------------------------------------------------------------------
    // CA-03: La entrada crea la existencia si no existe
    // -------------------------------------------------------------------------

    public function test_entrada_crea_existencia_si_no_existe(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create([
            'expiration_date' => now()->addDays(30),
        ]);

        // Sin existencia previa en esta bodega
        $this->assertDatabaseMissing('inventories', [
            'lot_id' => $lote->id,
            'warehouse_id' => $bodega->id,
        ]);

        // Obtener o crear existencia
        $existencia = app(KardexService::class)->obtenerOCrearExistencia(
            $bodega->id,
            $lote
        );

        // La existencia fue creada
        $this->assertDatabaseHas('inventories', ['id' => $existencia->id]);
        $this->assertDatabaseHas('inventories', ['lot_id' => $lote->id]);
        $this->assertDatabaseHas('inventories', ['warehouse_id' => $bodega->id]);
        $this->assertDatabaseHas('inventories', ['quantity' => 0]);
    }

    // -------------------------------------------------------------------------
    // CA-04: Movimiento de salida no deja stock negativo
    // -------------------------------------------------------------------------

    public function test_salida_con_stock_insuficiente_lanza_excepcion(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create([
            'expiration_date' => now()->addDays(90),
        ]);
        $tipo = MovementType::porNombre('TRANSFER_OUT');

        // Crear existencia con 30 unidades
        $existencia = Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'quantity' => 30,
        ]);

        $regente = $this->crearRegente();

        $this->expectException(\App\Exceptions\StockInsuficienteException::class);

        app(KardexService::class)->registrarMovimiento(
            $existencia,
            $tipo,
            50,  // pide 50, hay 30
            $regente
        );
    }

    // -------------------------------------------------------------------------
    // CA-05: Kardex inmutable — sin update ni delete
    // -------------------------------------------------------------------------

    public function test_put_y_delete_sobre_movimiento_devuelven_405(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create();
        $existencia = Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'quantity' => 100,
        ]);

        $movimiento = Movement::factory()->create([
            'inventory_id' => $existencia->id,
        ]);

        $user = $this->crearRegente();

        // PUT devuelve 405
        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/movements/{$movimiento->id}", []);

        $response->assertStatus(405);

        // DELETE devuelve 405
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/movements/{$movimiento->id}");

        $response->assertStatus(405);
    }

    // -------------------------------------------------------------------------
    // CA-08: Listar inventarios ordenados por vencimiento (FEFO)
    // -------------------------------------------------------------------------

    public function test_inventarios_ordenados_por_fecha_vencimiento_fefo(): void
    {
        $bodega = Bodega::factory()->create();

        $lote1 = Lot::factory()->create(['expiration_date' => now()->addDays(10)]);
        $lote2 = Lot::factory()->create(['expiration_date' => now()->addDays(120)]);
        $lote3 = Lot::factory()->create(['expiration_date' => now()->addDays(45)]);

        $inv1 = Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote1->id,
            'product_id' => $lote1->product_id,
        ]);
        $inv2 = Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote2->id,
            'product_id' => $lote2->product_id,
        ]);
        $inv3 = Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote3->id,
            'product_id' => $lote3->product_id,
        ]);

        $user = $this->crearRegente();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/inventories');

        $response->assertStatus(200);

        $data = $response->json('data');

        // Orden FEFO: primero el que vence antes (10 dias), luego 45, luego 120.
        // strtolower en ambos lados: SQL Server devuelve los uniqueidentifier en
        // MAYUSCULAS, los modelos en minuscula, y assertSame distingue caso.
        $this->assertSame(strtolower($inv1->id), strtolower($data[0]['id']));
        $this->assertSame(strtolower($inv3->id), strtolower($data[1]['id']));
        $this->assertSame(strtolower($inv2->id), strtolower($data[2]['id']));
    }

    // -------------------------------------------------------------------------
    // CA-09: Alerta de lotes proximos a vencer
    // -------------------------------------------------------------------------

    public function test_inventario_por_vencer_aparece_en_alertas(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create([
            'expiration_date' => now()->addDays(20),
        ]);
        Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'quantity' => 50,
        ]);

        $user = $this->crearRegente();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/inventories');

        $response->assertStatus(200);

        $this->assertNotEmpty($response->json('alerts.expiring_soon'));
        $this->assertEmpty($response->json('alerts.expired'));
    }

    // -------------------------------------------------------------------------
    // CA-10: Alerta de lotes vencidos
    // -------------------------------------------------------------------------

    public function test_inventario_vencido_aparece_en_alertas(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create([
            'expiration_date' => now()->subDays(10),
        ]);
        Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'quantity' => 20,
        ]);

        $user = $this->crearRegente();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/inventories');

        $response->assertStatus(200);

        $this->assertNotEmpty($response->json('alerts.expired'));
        $this->assertEmpty($response->json('alerts.expiring_soon'));
    }

    // -------------------------------------------------------------------------
    // CA-11: Filtro expiring_within
    // -------------------------------------------------------------------------

    public function test_filtro_expiring_within_retorna_solo_lotes_en_ventana(): void
    {
        $bodega = Bodega::factory()->create();

        $lote10 = Lot::factory()->create(['expiration_date' => now()->addDays(10)]);
        $lote45 = Lot::factory()->create(['expiration_date' => now()->addDays(45)]);
        $lote120 = Lot::factory()->create(['expiration_date' => now()->addDays(120)]);

        foreach ([$lote10, $lote45, $lote120] as $lote) {
            Inventory::factory()->create([
                'warehouse_id' => $bodega->id,
                'lot_id' => $lote->id,
                'product_id' => $lote->product_id,
            ]);
        }

        $user = $this->crearRegente();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/inventories?expiring_within=60');

        $response->assertStatus(200);

        $data = $response->json('data');

        // Solo 10 y 45 dias en la lista principal
        $this->assertCount(2, $data);
        $this->assertSame(10, $data[0]['days_until_expiration']);
        $this->assertSame(45, $data[1]['days_until_expiration']);
    }

    // -------------------------------------------------------------------------
    // CA-13: Alerta de stock bajo
    // -------------------------------------------------------------------------

    public function test_inventario_bajo_minimo_aparece_en_alertas(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create();
        Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'quantity' => 5,
            'min_stock' => 20,
        ]);

        $user = $this->crearRegente();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/inventories');

        $response->assertStatus(200);

        $alertas = $response->json('alerts.low_stock');
        $this->assertCount(1, $alertas);
        $this->assertSame(-15, $alertas[0]['deficit']);
    }

    // -------------------------------------------------------------------------
    // CA-16: Permisos — regente_farmacia puede ajustar inventario
    // -------------------------------------------------------------------------

    public function test_regente_puede_crear_movimiento(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create([
            'expiration_date' => now()->addDays(90),
        ]);
        $regente = $this->crearRegente();

        $response = $this->actingAs($regente, 'sanctum')
            ->postJson('/api/movements', [
                'lot_id' => $lote->id,
                'warehouse_id' => $bodega->id,
                'type' => 'ENTRY',
                'quantity' => 50,
                'reason' => 'Test entrada',
            ]);

        $response->assertStatus(201);
        $this->assertSame(50, $response->json('data.inventory.quantity'));
    }

    // -------------------------------------------------------------------------
    // CA-17: Permisos — auxiliar NO puede ajustar inventario
    // -------------------------------------------------------------------------

    public function test_auxiliar_no_puede_crear_movimiento(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create([
            'expiration_date' => now()->addDays(90),
        ]);
        $auxiliar = $this->crearAuxiliar();

        $response = $this->actingAs($auxiliar, 'sanctum')
            ->postJson('/api/movements', [
                'lot_id' => $lote->id,
                'warehouse_id' => $bodega->id,
                'type' => 'ENTRY',
                'quantity' => 50,
            ]);

        // Auxiliar tiene can_read en kardex pero no can_create
        $response->assertStatus(403);
    }

    public function test_auxiliar_puede_consultar_movimientos(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create();
        $existencia = Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
        ]);
        Movement::factory()->create([
            'inventory_id' => $existencia->id,
        ]);

        $auxiliar = $this->crearAuxiliar();

        $response = $this->actingAs($auxiliar, 'sanctum')
            ->getJson('/api/movements');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // CA-14: Concurrencia — lockForUpdate serializa a quienes compiten por
    // la misma existencia.
    //
    // No podemos levantar dos procesos PHP dentro de un solo test, pero
    // podemos validar el comportamiento observable: si dos operaciones
    // intentan dejar quantity < 0 partiendo de un stock de 1, solo una
    // sobrevive y la otra recibe StockInsuficienteException. lockForUpdate
    // es lo que hace que la segunda vea el stock ya descontado por la
    // primera, no el valor original.
    // -------------------------------------------------------------------------

    public function test_concurrencia_dos_salidas_sobre_stock_unitario_solo_una_pasa(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create(['expiration_date' => now()->addDays(60)]);
        $existencia = Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'quantity' => 1,  // una sola unidad
        ]);

        $regente = $this->crearRegente();
        $tipoSalida = MovementType::porNombre('TRANSFER_OUT');

        // Primer movimiento: descuenta la unidad y queda en 0.
        $existencia->refresh();
        app(KardexService::class)->registrarMovimiento(
            $existencia,
            $tipoSalida,
            1,
            $regente
        );

        // Segundo movimiento: la fila de la base ya esta en 0, y el
        // KardexService la relee con lockForUpdate. Tiene que rechazar.
        $this->expectException(\App\Exceptions\StockInsuficienteException::class);
        app(KardexService::class)->registrarMovimiento(
            $existencia,
            $tipoSalida,
            1,
            $regente
        );
    }

    // -------------------------------------------------------------------------
    // CA-15: El CHECK de la base (chk_inventories_quantity_non_negative)
    // rechaza un UPDATE directo a un valor negativo, aunque el modelo
    // Eloquent (sin pasar por KardexService) lo intente. El test ejecuta
    // SQL crudo para probar el muro real, no la validacion del service.
    // -------------------------------------------------------------------------

    public function test_check_constraint_bloquea_stock_negativo_directo_en_sql(): void
    {
        $bodega = Bodega::factory()->create();
        $lote = Lot::factory()->create();
        $existencia = Inventory::factory()->create([
            'warehouse_id' => $bodega->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'quantity' => 5,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \DB::table('inventories')
            ->where('id', $existencia->id)
            ->update(['quantity' => -10]);
    }

    // -------------------------------------------------------------------------
    // CA-18: ENTRADA con expiration_date = today() (el lote vence hoy)
    // se acepta. La regla es expiration_date >= today(), no > today().
    // El status de vencimiento debe ser "critico" porque dias = 0 cae en
    // el rango <= 30.
    // -------------------------------------------------------------------------

    public function test_entrada_con_expiracion_hoy_se_acepta(): void
    {
        $regente = $this->crearRegente();
        $bodega = Bodega::factory()->create();
        // Lote que vence HOY: Carbon::today() == 2026-10-10 == expiration_date.
        $lote = Lot::factory()->create([
            'expiration_date' => \Illuminate\Support\Carbon::today(),
        ]);

        $response = $this->actingAs($regente, 'sanctum')
            ->postJson('/api/movements', [
                'lot_id' => $lote->id,
                'warehouse_id' => $bodega->id,
                'type' => 'ENTRY',
                'quantity' => 5,
                'reason' => 'Lote vence hoy, acepto la entrada',
            ]);

        $response->assertStatus(201);

        $existencia = Inventory::where('lot_id', $lote->id)
            ->where('warehouse_id', $bodega->id)
            ->first();
        $this->assertSame(5, $existencia->quantity);

        // La regla del service dice expiration_date >= today(): hoy es >= hoy.
        // Verificamos que el modelo calcula diasParaVencer = 0.
        $this->assertSame(0, $existencia->diasParaVencer());
        $this->assertSame('critico', $existencia->estadoVencimiento());
    }
}
