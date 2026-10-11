<?php

namespace Tests\Feature;

use App\Http\Resources\TransferDiscrepancyResource;
use App\Http\Resources\TransferItemResource;
use App\Http\Resources\TransferResource;
use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\TransferItem;
use App\Models\TransferStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de humo de los resources de traslados (HU-09).
 *
 * Verifica que los 3 resources cargan y producen la forma esperada
 * del JSON. Es unitario: no hace HTTP, instancia los models en
 * memoria con setRelation() y valida con assertArrayHasKey.
 *
 * El test de ciclo end-to-end (controller + service + BD) vive en
 * TransferCycleTest. Este solo verifica que el shape de la
 * respuesta es el que la HU-09 §10 (Contrato de API) documenta.
 */
class TransferResourceSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Necesario para que TransferStatus::porNombre() funcione:
        // los 7 estados se siembran en la migracion 000003.
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\ModuleSeeder::class,
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\RolePermissionSeeder::class,
        ]);
    }

    public function test_transfer_resource_carga(): void
    {
        $this->assertTrue(class_exists(TransferResource::class));
    }

    public function test_transfer_item_resource_carga(): void
    {
        $this->assertTrue(class_exists(TransferItemResource::class));
    }

    public function test_transfer_discrepancy_resource_carga(): void
    {
        $this->assertTrue(class_exists(TransferDiscrepancyResource::class));
    }

    public function test_transfer_resource_tiene_las_claves_esperadas(): void
    {
        $transfer = $this->armarTransferDePrueba();

        $array = (new TransferResource($transfer))->toArray(null);

        // Claves planas que la HU-09 §10 documenta.
        foreach ([
            'id', 'transfer_number', 'from_warehouse_id', 'to_warehouse_id',
            'from_warehouse_name', 'to_warehouse_name',
            'from_warehouse', 'to_warehouse',
            'status_id', 'status_name', 'status_label',
            'is_cancellable', 'is_terminal', 'status',
            'requested_by', 'requested_by_name',
            'approved_by', 'approved_by_name',
            'dispatched_by', 'dispatched_by_name',
            'received_by', 'received_by_name',
            'cancelled_by', 'cancelled_by_name',
            'dispatched_at', 'received_at', 'cancelled_at',
            'notes', 'items', 'discrepancies',
            'created_at', 'updated_at',
        ] as $key) {
            $this->assertArrayHasKey($key, $array, "Falta la clave {$key} en TransferResource");
        }
    }

    public function test_transfer_resource_status_decision_d3(): void
    {
        // La decision D3 del proyecto: name tecnico en ingles, label
        // en espanol. Esto es lo que la UI muestra.
        $transfer = $this->armarTransferDePrueba('APPROVED');
        $array = (new TransferResource($transfer))->toArray(null);

        $this->assertSame('APPROVED', $array['status_name']);
        $this->assertSame('Aprobado', $array['status_label']);
        $this->assertFalse($array['is_cancellable']);
        $this->assertFalse($array['is_terminal']);
    }

    public function test_transfer_item_resource_tiene_las_claves_esperadas(): void
    {
        $transfer = $this->armarTransferDePrueba();
        $item = $transfer->items->first();
        $array = (new TransferItemResource($item))->toArray(null);

        foreach ([
            'id', 'transfer_id', 'inventory_id',
            'lot_name', 'product_name', 'product_sku', 'warehouse_name',
            'lot_expiration_date',
            'requested_quantity', 'approved_quantity', 'delivered_quantity',
            'pending_quantity', 'is_fully_received', 'has_discrepancy',
            'lot', 'product', 'warehouse',
            'created_at',
        ] as $key) {
            $this->assertArrayHasKey($key, $array, "Falta la clave {$key} en TransferItemResource");
        }
    }

    public function test_transfer_item_flags_derivados(): void
    {
        $transfer = $this->armarTransferDePrueba();
        $item = $transfer->items->first();

        // 50 aprobado, 30 recibido: pendiente 20, no recibido completo, hay discrepancia.
        $item->approved_quantity = 50;
        $item->delivered_quantity = 30;
        $array = (new TransferItemResource($item))->toArray(null);

        $this->assertSame(20, $array['pending_quantity']);
        $this->assertFalse($array['is_fully_received']);
        $this->assertTrue($array['has_discrepancy']);
    }

    public function test_transfer_discrepancy_resource_tiene_las_claves_esperadas(): void
    {
        $disc = new TransferDiscrepancy([
            'transfer_id' => '11111111-2222-3333-4444-555555555555',
            'transfer_item_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'approved_quantity' => 50,
            'delivered_quantity' => 30,
            'difference' => 20,
            'status' => 'PENDING',
        ]);
        $disc->id = '99999999-8888-7777-6666-555555555555';

        $array = (new TransferDiscrepancyResource($disc))->toArray(null);

        foreach ([
            'id', 'transfer_id', 'transfer_item_id',
            'approved_quantity', 'delivered_quantity', 'difference',
            'status', 'resolution_notes',
            'resolved_by', 'resolved_at',
            'is_pending', 'is_resolved',
            'resolved_by_user',
            'created_at',
        ] as $key) {
            $this->assertArrayHasKey($key, $array, "Falta la clave {$key} en TransferDiscrepancyResource");
        }

        $this->assertTrue($array['is_pending']);
        $this->assertFalse($array['is_resolved']);
        $this->assertSame(20, $array['difference']);
    }

    /**
     * Construye un Transfer en memoria con bodegas, status e items
     * ya cargados via setRelation(), sin persistir en BD.
     */
    private function armarTransferDePrueba(string $statusName = 'DRAFT'): Transfer
    {
        $regente = User::factory()->create();
        $origen = Bodega::factory()->create(['name' => 'Bodega Origen Test']);
        $destino = Bodega::factory()->create(['name' => 'Bodega Destino Test']);
        $product = Product::factory()->create();
        $lote = Lot::factory()->create([
            'product_id' => $product->id,
            'expiration_date' => now()->addDays(60)->toDateString(),
        ]);
        $inventario = Inventory::factory()->create([
            'warehouse_id' => $origen->id,
            'product_id' => $product->id,
            'lot_id' => $lote->id,
            'quantity' => 100,
        ]);

        $status = TransferStatus::porNombre($statusName);

        $transfer = new Transfer();
        $transfer->id = '11111111-2222-3333-4444-555555555555';
        $transfer->transfer_number = 'TRF-TEST-0001';
        $transfer->from_warehouse_id = $origen->id;
        $transfer->to_warehouse_id = $destino->id;
        $transfer->status_id = $status?->id;
        $transfer->requested_by = $regente->id;
        $transfer->notes = 'Test';
        $transfer->setRelation('bodegaOrigen', $origen);
        $transfer->setRelation('bodegaDestino', $destino);
        $transfer->setRelation('status', $status);
        $transfer->setRelation('requestedBy', $regente);

        $item = new TransferItem([
            'transfer_id' => $transfer->id,
            'inventory_id' => $inventario->id,
            'requested_quantity' => 50,
            'delivered_quantity' => 0,
        ]);
        $item->id = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $item->setRelation('inventory', $inventario);
        $transfer->setRelation('items', collect([$item]));

        return $transfer;
    }
}
