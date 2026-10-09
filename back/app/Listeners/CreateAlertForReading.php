<?php

namespace App\Listeners;

use App\Events\ReadingRecorded;
use App\Models\Alert;
use Illuminate\Support\Str;

/**
 * Listener que evalúa lectura de temperatura y humedad, creando alertas cuando se superan.
 */
class CreateAlertForReading
{
    /**
     * Manejar el evento de lectura registrada.
     */
    public function handle(ReadingRecorded $event): void
    {
        $reading = $event->reading;

        $tempAlert = $reading->temperature > 25.0;
        $humidAlert = $reading->humidity > 70.0;

        // No se crea alerta si no se superan los umbrales
        if (!$tempAlert && !$humidAlert) {
            return;
        }

        // Determinar el tipo de alerta
        if ($tempAlert && $humidAlert) {
            $type = 'both';
            $message = 'Temperatura y humedad superan los umbrales. Temperatura: ' . $reading->temperature . '°C, Humedad: ' . $reading->humidity . '%';
        } elseif ($tempAlert) {
            $type = 'temperature';
            $message = 'Temperatura supera los 25°C. Valor registrado: ' . $reading->temperature . '°C';
        } else {
            $type = 'humidity';
            $message = 'Humedad supera el 70%. Valor registrado: ' . $reading->humidity . '%';
        }

        // Crear la alerta
        Alert::create([
            'id'         => Str::uuid(),
            'reading_id' => $reading->id,
            'type'       => $type,
            'message'    => $message,
            'created_at' => now(),
        ]);
    }
}
