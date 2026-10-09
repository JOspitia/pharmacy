<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\Sensor;
use App\Models\Alert;
use App\Models\Reading;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SensorService
{

    /**
     * Datos necesarios para cargar el formulario de creación/edición de un sensor
     */
    public function getFormData(): array
    {
        return [
            'bodegas' => Bodega::where('status', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
        ];
    }

    /**
     * Obtener todos los sensores, opcionalmente filtrados por bodega
     */
    public function getAllSensors(?string $warehouseId = null): Collection
    {
        $query = Sensor::latest()->with('warehouse:id,name');
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        return $query->get();
    }

    /**
     * Crear un nuevo sensor
     */
    public function createSensor(array $data): Sensor
    {
        // Validar restricciones de unicidad
        $this->checkUniquenessConstraints($data);

        // Validar que la bodega exista y esté activa
        if (array_key_exists('warehouse_id', $data) && !empty($data['warehouse_id'])) {
            $this->validateWarehouse($data['warehouse_id']);
        }

        // Crear el sensor
        $sensor = Sensor::create($data);
        return $sensor->load('warehouse:id,name');
    }

    /**
     * Actualizar un sensor existente
     */
    public function updateSensor(Sensor $sensor, array $data): Sensor
    {
        // Validar restricciones de unicidad excluyendo el sensor actual
        $this->checkUniquenessConstraints($data, $sensor->id);

        // Validar que la bodega exista y esté activa
        if (array_key_exists('warehouse_id', $data) && !empty($data['warehouse_id'])) {
            $this->validateWarehouse($data['warehouse_id']);
        }

        // Actualizar el sensor (warehouse_id está en $fillable)
        if (!empty($data)) {
            $sensor->update($data);
        }

        return $sensor->load('warehouse:id,name');
    }

    /**
     * Activar o inactivar un sensor
     */
    public function toggleStatus(Sensor $sensor): Sensor
    {
        $sensor->status = !$sensor->status;
        $sensor->save();

        return $sensor->load('warehouse:id,name');
    }

    /**
     * Eliminar sensor
     */
    public function deleteSensor(Sensor $sensor): void
    {
        DB::transaction(function () use ($sensor) {
            $this->cascadeDeleteRelated($sensor);

            $sensor->status = false;
            $sensor->save();
            $sensor->delete();
        });
    }

    /**
     * Validar que la bodega exista y esté activa.
     */
    private function validateWarehouse(string $warehouseId): void
    {
        $exists = Bodega::where('id', $warehouseId)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) throw new Exception('La bodega seleccionada no existe o está inactiva.');
    }

    /**
     * Verificar unicidad de nombre del sensor.
     */
    private function checkUniquenessConstraints(array $data, ?string $sensorId = null): void
    {
        // Validar que el nombre del sensor sea único
        if (empty($data['name'])) return;

        $query = Sensor::whereRaw('LOWER(name) = ?', [strtolower($data['name'])]);
        
        if ($sensorId) $query->where('id', '!=', $sensorId);

        if ($query->exists()) throw new Exception('El nombre del sensor ya existe.');
    }

    /**
     * Eliminar lecturas y alertas asociadas al sensor.
     */
    private function cascadeDeleteRelated(Sensor $sensor): void
    {
        // Obtener IDs de las lecturas del sensor
        $readingIds = Reading::where('sensor_id', $sensor->id)->pluck('id');

        // Si no hay lecturas, no hay nada que eliminar
        if ($readingIds->isEmpty()) return;

        // Eliminar alertas asociadas a las lecturas del sensor
        Alert::whereIn('reading_id', $readingIds)->delete();

        // Eliminar las lecturas del sensor
        Reading::whereIn('id', $readingIds)->delete();
    }
}
