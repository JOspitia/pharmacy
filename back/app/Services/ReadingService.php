<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Bodega;
use App\Models\Reading;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReadingService
{
    /**
     * Obtener las lecturas paginadas de una bodega, con filtros opcionales.
     */
    public function getReadingsByBodega(string $bodegaId, array $filters = []): LengthAwarePaginator
    {
        return Reading::with(['sensor.warehouse:id,name', 'alert'])
            ->whereHas('sensor', function ($q) use ($bodegaId) {
                $q->where('warehouse_id', $bodegaId);
            })
            ->when($filters['sensor_id'] ?? null, function ($query, $sensorId) {
                $query->where('sensor_id', $sensorId);
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->where('recorded_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->where('recorded_at', '<=', $dateTo);
            })
            ->when($filters['alert_only'] ?? null, function ($query) {
                $query->has('alert');
            })
            ->orderBy('recorded_at', 'desc')
            ->paginate($filters['limit'] ?? 30);
    }

    /**
     * Obtener las alertas paginadas de una bodega, con filtros opcionales.
     */
    public function getAlertsByBodega(string $bodegaId, array $filters = []): LengthAwarePaginator
    {
        return Alert::with(['reading.sensor.warehouse:id,name'])
            ->whereHas('reading.sensor', function ($q) use ($bodegaId) {
                $q->where('warehouse_id', $bodegaId);
            })
            ->when($filters['sensor_id'] ?? null, function ($query, $sensorId) {
                $query->whereHas('reading', fn($q) => $q->where('sensor_id', $sensorId));
            })
            ->when($filters['type'] ?? null, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($filters['created_after'] ?? null, function ($query, $createdAfter) {
                $query->where('created_at', '>', $createdAfter);
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->where('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->where('created_at', '<=', $dateTo);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($filters['limit'] ?? 30);
    }

}
