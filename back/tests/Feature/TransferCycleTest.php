<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\TransferItem;
use App\Models\TransferStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del ciclo principal de un traslado (HU-09, RN-07).
 *
 * Cubre el flujo end-to-end via HTTP:
 *   auxiliar crea -> regente aprueba -> regente despacha ->
 *   auxiliar recibe -> traslado en RECEIVED.
 *
 * El test del happy path vive en test_ciclo_completo_via_http.
 * Las reglas de negocio (RN-01, RN-08, stock insuficiente) tienen
 * sus propios tests abajo.
 */
class TransferCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Sin los seeders de roles y permisos, los helpers regente/auxiliar
        // asignan role_id = null y el middleware module:transfers devuelve
        // 403 para todo, aunque el codigo de negocio este bien.
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

    private function auxiliar(): User
    {
        return User::factory()->create([
            'role_id' => \App\Models\Role::where('name', 'auxiliar_farmacia')->first()?->id,
        ]);
    }

    /**
     * Crea bodega origen y destino, y un lote con existencia en la
     * bodega origen con stock $cantidad. Devuelve un array con todo
     * lo necesario para construir un POST valido.
     */
    private function setupBodegasYInventario(int $cantidad = 100): array
    {
        $origen = Bodega::factory()->create();
        $destino = Bodega::factory()->create();
        $lote = Lot::factory()->create([
            'expiration_date' => now()->addDays(60)->toDateString(),
        ]);
        $inventario = Inventory::factory()->create([
            'warehouse_id' => $origen->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'quantity' => $cantidad,
        ]);

        return compact('origen', 'destino', 'lote', 'inventario');
    }

    // -------------------------------------------------------------------------
    // CA-01: Solicitar traslado (Camino Feliz)
    // -------------------------------------------------------------------------

    public function test_ciclo_completo_via_http(): void
    {
        $regente = $this->regente();
        $auxiliar = $this->auxiliar();
        $datos = $this->setupBodegasYInventario(100);

        // 1. Auxiliar crea el traslado (POST /api/transfers)
        $response = $this->actingAs($auxiliar, 'sanctum')->postJson('/api/transfers', [
            'to_warehouse_id' => $datos['destino']->id,
            'notes' => 'Ciclo de prueba',
            'items' => [
                ['inventory_id' => $datos['inventario']->id, 'requested_quantity' => 30],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status_name', 'DRAFT');
        $response->assertJsonPath('data.transfer_number', 'TRF-' . now()->format('Ymd') . '-0001');
        $response->assertJsonCount(1, 'data.items');
        $transferId = $response->json('data.id');

        // 2. Regente aprueba (PATCH /api/transfers/{id}/approve)
        $itemId = $response->json('data.items.0.id');
        $approveResponse = $this->actingAs($regente, 'sanctum')->patchJson(
            "/api/transfers/{$transferId}/approve",
            ['items' => [['transfer_item_id' => $itemId, 'approved_quantity' => 30]]]
        );
        $approveResponse->assertStatus(200);
        $approveResponse->assertJsonPath('data.status_name', 'APPROVED');
        $this->assertNotNull($approveResponse->json('data.approved_by'));

        // 3. Regente despacha (PATCH /api/transfers/{id}/dispatch)
        $dispatchResponse = $this->actingAs($regente, 'sanctum')->patchJson(
            "/api/transfers/{$transferId}/dispatch"
        );
        $dispatchResponse->assertStatus(200);
        $dispatchResponse->assertJsonPath('data.status_name', 'IN_TRANSIT');
        $this->assertNotNull($dispatchResponse->json('data.dispatched_at'));

        // El Kardex desconto 30 del inventario origen.
        $this->assertSame(70, $datos['inventario']->fresh()->quantity);
        $this->assertDatabaseHas('movements', [
            'inventory_id' => $datos['inventario']->id,
            'movement_type_id' => MovementType::porNombre('TRANSFER_OUT')?->id,
            'quantity' => -30,
        ]);

        // 4. Auxiliar recibe completo (PATCH /api/transfers/{id}/receive)
        $receiveResponse = $this->actingAs($auxiliar, 'sanctum')->patchJson(
            "/api/transfers/{$transferId}/receive",
            ['items' => [['transfer_item_id' => $itemId, 'delivered_quantity' => 30]]]
        );
        $receiveResponse->assertStatus(200);
        $receiveResponse->assertJsonPath('data.status_name', 'RECEIVED');
        $this->assertNotNull($receiveResponse->json('data.received_at'));

        // El Kardex sumo 30 a la bodega destino.
        $destinoInv = Inventory::where('warehouse_id', $datos['destino']->id)
            ->where('lot_id', $datos['lote']->id)
            ->first();
        $this->assertNotNull($destinoInv);
        $this->assertSame(30, $destinoInv->quantity);
        $this->assertDatabaseHas('movements', [
            'inventory_id' => $destinoInv->id,
            'movement_type_id' => MovementType::porNombre('TRANSFER_IN')?->id,
            'quantity' => 30,
        ]);

        // Sin discrepancias: se recibio todo lo aprobado.
        $this->assertSame(0, TransferDiscrepancy::where('transfer_id', $transferId)->count());
    }

    // -------------------------------------------------------------------------
    // CA-03: Rechazar approve por lote vencido (RN-01)
    // -------------------------------------------------------------------------

    public function test_aprobar_lote_vencido_es_rechazado_con_422(): void
    {
        $regente = $this->regente();
        $auxiliar = $this->auxiliar();
        $origen = Bodega::factory()->create();
        $destino = Bodega::factory()->create();
        $lote = Lot::factory()->create([
            'expiration_date' => now()->subDays(5)->toDateString(), // vencido
        ]);
        $inv = Inventory::factory()->create([
            'warehouse_id' => $origen->id,
            'lot_id' => $lote->id,
            'product_id' => $lote->product_id,
            'quantity' => 50,
        ]);

        // Crear
        $createResponse = $this->actingAs($auxiliar, 'sanctum')->postJson('/api/transfers', [
            'to_warehouse_id' => $destino->id,
            'items' => [['inventory_id' => $inv->id, 'requested_quantity' => 10]],
        ]);
        $createResponse->assertStatus(201);
        $transferId = $createResponse->json('data.id');
        $itemId = $createResponse->json('data.items.0.id');

        // Aprobar: 422 con mensaje de RN-01
        $approveResponse = $this->actingAs($regente, 'sanctum')->patchJson(
            "/api/transfers/{$transferId}/approve",
            ['items' => [['transfer_item_id' => $itemId, 'approved_quantity' => 10]]]
        );
        $approveResponse->assertStatus(422);
        $this->assertStringContainsString('lotes vencidos', $approveResponse->json('message'));
        $this->assertStringContainsString('RN-01', $approveResponse->json('message'));
    }

    // -------------------------------------------------------------------------
    // CA-04: Rechazar approve por segregacion (RN-08)
    // -------------------------------------------------------------------------

    public function test_aprobar_propio_traslado_es_rechazado_por_rn08(): void
    {
        $regente = $this->regente(); // el unico rol que aprueba
        $datos = $this->setupBodegasYInventario(50);

        // El regente crea y luego intenta aprobar su propio traslado.
        $createResponse = $this->actingAs($regente, 'sanctum')->postJson('/api/transfers', [
            'to_warehouse_id' => $datos['destino']->id,
            'items' => [['inventory_id' => $datos['inventario']->id, 'requested_quantity' => 5]],
        ]);
        $createResponse->assertStatus(201);
        $transferId = $createResponse->json('data.id');
        $itemId = $createResponse->json('data.items.0.id');

        // Si el itemId no llego (por un bug en el resource), la
        // asercion posterior daria un falso 422 por FormRequest. Lo
        // verificamos explicitamente.
        $this->assertNotEmpty($itemId, 'El POST no devolvio items en la respuesta.');

        $approveResponse = $this->actingAs($regente, 'sanctum')->patchJson(
            "/api/transfers/{$transferId}/approve",
            ['items' => [['transfer_item_id' => $itemId, 'approved_quantity' => 5]]]
        );
        $approveResponse->assertStatus(422);
        $this->assertStringContainsString('RN-08', $approveResponse->json('message'));
    }

    // -------------------------------------------------------------------------
    // CA-14: Stock no disponible para solicitud
    // -------------------------------------------------------------------------

    public function test_solicitar_mas_del_stock_disponible_es_rechazado(): void
    {
        $auxiliar = $this->auxiliar();
        $datos = $this->setupBodegasYInventario(10); // solo 10 unidades

        $response = $this->actingAs($auxiliar, 'sanctum')->postJson('/api/transfers', [
            'to_warehouse_id' => $datos['destino']->id,
            'items' => [['inventory_id' => $datos['inventario']->id, 'requested_quantity' => 50]],
        ]);
        $response->assertStatus(422);
        $this->assertStringContainsString('Stock insuficiente', $response->json('message'));
        $this->assertStringContainsString('solo hay 10', $response->json('message'));
    }

    // -------------------------------------------------------------------------
    // El mismo inventario no puede repetirse dentro de un traslado.
    //
    // Sin la regla `distinct` en StoreTransferRequest, el servicio
    // valida el stock linea por linea (5 > 8 es falso dos veces) y
    // luego el segundo INSERT viola el UNIQUE (transfer_id,
    // inventory_id): la peticion terminaba en HTTP 500 por
    // QueryException en vez de un 422 con mensaje util.
    // -------------------------------------------------------------------------

    public function test_inventario_repetido_en_un_traslado_es_rechazado(): void
    {
        $auxiliar = $this->auxiliar();
        $datos = $this->setupBodegasYInventario(50);

        $response = $this->actingAs($auxiliar, 'sanctum')->postJson('/api/transfers', [
            'to_warehouse_id' => $datos['destino']->id,
            // El mismo inventory_id dos veces. Cada linea por separado
            // tiene stock de sobra (5 <= 50), asi que sin `distinct` la
            // validacion de stock pasaria y reventaria el INSERT.
            'items' => [
                ['inventory_id' => $datos['inventario']->id, 'requested_quantity' => 5],
                ['inventory_id' => $datos['inventario']->id, 'requested_quantity' => 5],
            ],
        ]);

        // 422 y NO 500: el mensaje debe llegar al usuario.
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items.1.inventory_id');
    }

    // -------------------------------------------------------------------------
    // CA-12: Validar bodega origen y destino distintas
    // (El FormRequest no valida esto porque from_warehouse_id no viene
    // en el body: se deduce del inventory. La validacion vive en el
    // service y se hace dentro de la transaccion.)
    // -------------------------------------------------------------------------

    public function test_solicitar_a_la_misma_bodega_es_rechazado(): void
    {
        $auxiliar = $this->auxiliar();
        $datos = $this->setupBodegasYInventario(50);

        // Forzamos el caso: solo hay un inventario en una sola bodega,
        // y pedimos moverlo a la MISMA bodega. Como el body no trae
        // from_warehouse_id, el service deduce que la bodega origen es
        // la del inventario y la compara con to_warehouse_id.
        $response = $this->actingAs($auxiliar, 'sanctum')->postJson('/api/transfers', [
            'to_warehouse_id' => $datos['origen']->id, // misma bodega
            'items' => [['inventory_id' => $datos['inventario']->id, 'requested_quantity' => 5]],
        ]);
        $response->assertStatus(422);
        $this->assertStringContainsString('origen y destino no pueden ser la misma', $response->json('message'));
    }
}
