<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\Inventory;
use App\Models\Lot;
use Illuminate\Database\Eloquent\Collection;
use Exception;

/**
 * Clase para el manejo de la lógica de negocio de las bodegas.
 */
class BodegaService
{
    /**
     * Obtener todas las bodegas
     */
    public function getAllBodegas(): Collection
    {
        return Bodega::latest()->get();
    }

    /**
     * Crear una nueva bodega.
     */
    public function createBodega(array $data): Bodega
    {
        return Bodega::create($data);
    }

    /**
     * Actualizar una bodega existente.
     */
    public function updateBodega(Bodega $bodega, array $data): Bodega
    {   
        $bodega->update($data);
        return $bodega;
    }

    /**
     * Activar o inactivar una bodega.
     */
    public function toggleStatus(Bodega $bodega): Bodega
    {
        // Si la bodega esta activa y tiene lotes activos, no se puede inactivar
        if ($bodega->status && $this->hasActiveLots($bodega)) {
            throw new Exception('No se puede inactivar, tiene lotes activos');
        }

        $bodega->status = !$bodega->status;
        $bodega->save();

        return $bodega;
    }

    /** 
     * Eliminar una bodega.
     */
    public function deleteBodega(Bodega $bodega): void
    {
        // Si la bodega tiene lotes activos, no se puede eliminar
        if ($this->hasActiveLots($bodega)) {
            throw new Exception('No se puede eliminar, tiene lotes activos');
        }   

        $bodega->status = false;
        $bodega->save();
        $bodega->delete();
    }

    /**
     * Verificar si la bodega tiene lotes activos.
     */
    private function hasActiveLots(Bodega $bodega): bool
    {
        // HU-03: lotes con status = true y deleted_at IS NULL.
        return $bodega->lots()
            ->where('status', true)
            ->exists();
    }

    /**
     * Lotes con existencia en una bodega (HU-08, CA-12).
     *
     * El selector de lote del formulario de Kardex (selector de bodega ->
     * selector de lote) consulta esto: dado un warehouse_id, devuelve los
     * lotes que tienen al menos una fila en inventories para esa bodega.
     *
     * El campo `stock` aqui es la suma de quantity de TODAS las filas del
     * mismo lote en esta bodega, no en todas las bodegas: en HU-08 una
     * existencia es la terna (bodega, producto, lote), y un mismo lote
     * puede tener varias filas en la misma bodega solo si hay varios
     * productos... lo cual RN-01 prohibe via UNIQUE constraint. Asi que
     * la suma es, en la practica, una sola fila, pero se hace con SUM
     * para no acoplar el service a esa invariante.
     *
     * Se filtra `quantity > 0` para que el selector no muestre existencias
     * vacias: el regente que registra una ENTRADA quiere saber contra que
     * lote esta sumando, no ver opciones donde el stock es 0.
     *
     * El orden es FEFO (expiration_date ASC): primero el que vence antes,
     * que es la convencion del resto del proyecto (HU-08 §10).
     *
     * @return Collection<int, \App\Models\Lot>
     */
    public function lotesEnBodega(Bodega $bodega): Collection
    {
        // Join a inventories para ordenar por expiration_date (FEFO).
        // El join se hace sobre la misma bodega para que el orden respete
        // el stock en ESA bodega, no el stock global del lote.
        //
        // No se necesita GROUP BY: la UNIQUE constraint de RN-01 garantiza
        // que para una bodega dada, cada lote tiene a lo sumo una fila en
        // inventories (porque UNIQUE(warehouse_id, product_id, lot_id) y
        // un lote siempre tiene un solo product_id). SQL Server rechaza
        // SELECT * + GROUP BY solo por id, asi que JOIN simple es lo que
        // sirve.
        //
        // Se carga `inventories` filtrado por bodega para que el resource
        // pueda mostrar el stock en esta bodega sin disparar N+1.
        $lotes = Lot::query()
            ->select('lots.*')
            ->join('inventories', 'inventories.lot_id', '=', 'lots.id')
            ->where('inventories.warehouse_id', $bodega->id)
            ->where('inventories.quantity', '>', 0)
            ->with([
                'product:id,name,cum',
                'inventories' => function ($query) use ($bodega) {
                    $query->where('warehouse_id', $bodega->id);
                },
            ])
            ->orderBy('lots.expiration_date', 'asc')
            ->get();

        return $lotes;
    }
}
