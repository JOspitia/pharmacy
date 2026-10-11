<?php

namespace App\Http\Resources;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Serializa un lote CON el stock que tiene en una bodega especifica.
 *
 * Se usa en el endpoint GET /api/bodegas/{bodega}/lots, que alimenta el
 * selector de lote del formulario de Kardex (CA-12). A diferencia de
 * LotResource, aqui el stock es el de ESA bodega y no el global, porque
 * un mismo lote puede estar en N bodegas y el selector del formulario
 * de Kardex solo le interesa la bodega que el regente acaba de elegir.
 *
 * La bodega se resuelve con un eager load controlado por el service:
 * $lote->load(['inventories' => fn ($q) => $q->where('warehouse_id', $bodega->id)])
 * en BodegaService::lotesEnBodega. Este resource solo lee esa relacion
 * precargada y devuelve la cantidad de la primera fila (RN-01 garantiza
 * una sola fila por (warehouse, lot)).
 *
 * @property-read Lot $resource
 */
class LoteEnBodegaResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Inventory|null $existencia */
        $existencia = $this->resource->inventories->first();

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'expiration_date' => $this->resource->expiration_date?->toDateString(),
            'days_until_expiration' => $this->diasParaVencer(),
            'stock' => $existencia?->quantity ?? 0,
            'min_stock' => $existencia?->min_stock,
            'product' => $this->resource->relationLoaded('product') && $this->resource->product
                ? [
                    'id' => $this->resource->product->id,
                    'name' => $this->resource->product->name,
                    'cum' => $this->resource->product->cum,
                ]
                : null,
        ];
    }

    private function diasParaVencer(): ?int
    {
        if (! $this->resource->expiration_date) {
            return null;
        }

        return (int) Carbon::today()
            ->diffInDays(Carbon::parse($this->resource->expiration_date)->startOfDay(), false);
    }
}
