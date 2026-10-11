<?php

namespace App\Http\Resources;

use App\Models\Inventory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Serializa un lote hacia el frontend.
 *
 * CAMBIO HU-08 (RN-01): el lote ya no tiene stock ni bodega propias.
 *
 * stock pasa a ser la suma de las existencias del lote en todas las bodegas.
 * Se mantiene el nombre de la clave para no romper el contrato que ya consume
 * el frontend, pero el valor tiene otro origen.
 *
 * warehouse desaparece y se reemplaza por warehouses: la lista de bodegas
 * donde el lote tiene existencia. Un lote puede estar en varias, que es
 * justamente lo que un traslado produce.
 */
class LotResource extends JsonResource
{
    /**
     * Transformar el recurso en un array.
     */
    public function toArray($request): array
    {
        $stockTotal = $this->stockTotal();

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'expiration_date' => $this->expiration_date,

            // Total del lote sumando todas sus existencias. Si la relacion no
            // viene cargada se consulta: un resource no debe exigir que el
            // llamador se acuerde del with().
            'stock'       => $stockTotal,

            'product' => $this->relationLoaded('product') && $this->product
                ? (new ProductResource($this->product))->only(['id', 'name'])
                : null,

            'warehouses' => $this->warehouses(),

            'derived_status' => $this->calculateDerivedStatus($stockTotal),
            'status'      => $this->status,
            'description' => $this->description,
            'created_by'  => $this->created_by,
            'updated_by'  => $this->updated_by,
            'created_at'  => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at'  => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }

    /**
     * Bodegas donde el lote tiene existencia, con la cantidad en cada una.
     */
    private function warehouses(): array
    {
        if (!$this->relationLoaded('inventories')) {
            return [];
        }

        return $this->inventories
            ->filter(fn (Inventory $existencia) => $existencia->bodega !== null)
            ->map(fn (Inventory $existencia) => [
                'id'       => $existencia->bodega->id,
                'name'     => $existencia->bodega->name,
                'quantity' => $existencia->quantity,
            ])
            ->values()
            ->all();
    }

    /**
     * Lógica de cálculo basada en las reglas de la HU-05
     */
    private function calculateDerivedStatus(int $stock): string
    {
        /*
        * En caso de tener stock = 0, el estado del registro debe ser "Sin stock".
        * Por el contrario, si tiene stock, se calcula el estado según su fecha de vencimiento.
        * Si la fecha es menor a la fecha actual, el estado es "Vencido".
        * Si la fecha está dentro de los 30 días siguientes, el estado es "Por vencer (30 días)".
        * Si la fecha está después de los 30 días, el estado es "Con stock".
        *
        * El unico cambio es que el stock llega como parametro en vez de leerse
        * de $this->stock, que ya no existe en el modelo.
        */
        if ($stock === 0) {
            return 'Sin stock';       
        }

        $today = Carbon::today();
        $expirationDate = Carbon::parse($this->expiration_date)->startOfDay();

        if ($expirationDate->lessThan($today)) {
            return 'Vencido';
        }

        if ($expirationDate->between($today, $today->copy()->addDays(30))) {
            return 'Por vencer (30 días)';
        }

        return 'Con stock';
    }
}