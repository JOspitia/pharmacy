<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UnitResource;
use App\Http\Resources\GroupResource;
use Illuminate\Support\Carbon;

/*
 * Clase para definir los campos que se van a devolver en el JSON al frontend, ocultando información sensible
 */
class LotResource extends JsonResource
{
    /**
     * Transformar el recurso en un array.
     */
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'expiration_date' => $this->expiration_date,
            'stock' => $this->stock,
            'product' => $this->relationLoaded('product') && $this->product
                ? (new ProductResource($this->product))->only(['id', 'name'])
                : null,
            'warehouse' => $this->relationLoaded('warehouse') && $this->warehouse
            ? (new BodegaResource($this->warehouse))->only(['id', 'name'])
            : null,
            'derived_status' => $this->calculateDerivedStatus(),
            'status'      => $this->status,
            'description' => $this->description,
            'created_by'  => $this->created_by,
            'updated_by'  => $this->updated_by,
            'created_at'  => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at'  => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }

    /**
     * Lógica de cálculo basada en las reglas de la HU-05
     */
    private function calculateDerivedStatus(): string
    {

        /*
        * En caso de tener stock = 0, el estado del registro debe ser "Sin stock".
        * Por el contrario, si tiene stock, se calcula el estado según su fecha de vencimiento.
        * Si la fecha es menor a la fecha actual, el estado es "Vencido".
        * Si la fecha está dentro de los 30 días siguientes, el estado es "Por vencer (30 días)".
        * Si la fecha está después de los 30 días, el estado es "Con stock".
        */
        if ($this->stock === 0) {
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