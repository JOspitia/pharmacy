<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSensorRequest;
use App\Http\Resources\SensorResource;
use App\Models\Sensor;
use App\Services\SensorService;
use Exception;

/**
 * Controlador para los sensores de monitoreo ambiental.
 */
class SensorController extends Controller
{
    // Atributo para almacenar el servicio
    protected SensorService $sensorService;

    /**
     * Inyectar las dependencias necesarias.
     */
    public function __construct(SensorService $sensorService)
    {
        $this->sensorService = $sensorService;
    }

    /**
     * Mostrar los sensores existentes.
     */
    public function index()
    {
        return SensorResource::collection($this->sensorService->getAllSensors());
    }

    /**
     * Datos necesarios para cargar el formulario de sensores.
     */
    public function formData()
    {
        return response()->json($this->sensorService->getFormData());
    }

    /**
     * Crear un nuevo sensor.
     */
    public function store(StoreSensorRequest $request)
    {
        try {
            $sensor = $this->sensorService->createSensor($request->validated());
            return response()->json([
                'message' => 'Sensor creado con éxito',
                'data' => new SensorResource($sensor)
            ], 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Mostrar un sensor específico.
     */
    public function show(Sensor $sensor)
    {
        $sensor->load('warehouse:id,name');

        return response()->json([
            'data' => new SensorResource($sensor)
        ], 200);
    }

    /**
     * Actualizar un sensor.
     */
    public function update(StoreSensorRequest $request, Sensor $sensor)
    {
        try {
            $updatedSensor = $this->sensorService->updateSensor($sensor, $request->validated());

            return response()->json([
                'message' => 'Sensor actualizado con éxito',
                'data' => new SensorResource($updatedSensor)
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Activar o inactivar un sensor.
     */
    public function toggleStatus(Sensor $sensor)
    {
        try {
            $updatedSensor = $this->sensorService->toggleStatus($sensor);
            $accion = $updatedSensor->status ? 'activado' : 'inactivado';

            return response()->json([
                'message' => "Sensor {$accion} con éxito",
                'data' => new SensorResource($updatedSensor)
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Eliminar un sensor (soft delete).
     */
    public function destroy(Sensor $sensor)
    {
        try {
            $this->sensorService->deleteSensor($sensor);
            return response()->json(['message' => 'Sensor eliminado correctamente'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
