<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Resource para el reporte de inventario de productos
 */
class ReportResource extends JsonResource
{
    /**
     * Transformar el recurso en un array.
     */
    public function toArray($request): array
    {
        return [
            'cum'                 => $this->cum,
            'product_name'        => $this->product_name,
            'group_name'          => $this->group_name,
            'barcode'             => $this->barcode,
            'invima'              => $this->invima_registration,
            'stock'               => (int) $this->stock,
            'unit'                => $this->unit_name . ($this->unit_abbr ? " ({$this->unit_abbr})" : ''),
            'lot_code'            => $this->lot_code,
            'bodega_name'         => $this->bodega_name,
            'expiration_date'     => $this->expiration_date ? Carbon::parse($this->expiration_date)->toDateString() : null,
            'alerta_exp'          => $this->alertStatus(),
            'alerta_color'        => $this->alertColor(),
        ];
    }

    /**
     * Estado de alerta de expiración (derivado).
     * - Verde:  expiration_date > today + 30 días
     * - Amarillo: expiration_date >= today AND <= today + 30 días
     * - Rojo:   expiration_date < today
     * - Gris:   expiration_date IS NULL
     */
    private function alertColor(): string
    {
        if ($this->expiration_date === null) {
            return 'gris';
        }

        $expires = Carbon::parse($this->expiration_date)->startOfDay();
        $today = Carbon::today();

        if ($expires->gt($today->copy()->addDays(30))) {
            return 'verde';
        }

        if ($expires->gte($today) && $expires->lte($today->copy()->addDays(30))) {
            return 'amarillo';
        }

        return 'rojo';
    }

    /**
     * Texto descriptivo para el tooltip/badge.
     */
    private function alertStatus(): string
    {
        if ($this->expiration_date === null) {
            return 'Sin fecha';
        }

        $expires = Carbon::parse($this->expiration_date)->startOfDay();
        $today = Carbon::today();

        if ($expires->gt($today->copy()->addDays(30))) {
            return 'Vigente';
        }

        if ($expires->gte($today) && $expires->lte($today->copy()->addDays(30))) {
            return 'Por vencer (' . $expires->diffInDays($today) . ' días)';
        }

        return 'Vencido (' . $expires->diffInDays($today) . ' días)';
    }
}
