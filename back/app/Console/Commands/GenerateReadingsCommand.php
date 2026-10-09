<?php

namespace App\Console\Commands;

use App\Events\ReadingRecorded;
use App\Models\Reading;
use App\Models\Sensor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

/**
 * Comando que genera lecturas aleatorias de temperatura y humedad para sensores activos con bodega asignada.
 *
 * Distribución:
 *   - 90% de las lecturas: valores dentro del rango normal (sin alerta)
 *   - 10% de las lecturas: valores que disparan alerta (pueden ser temp, humid, o ambas)
 */
class GenerateReadingsCommand extends Command
{
    // Firma del comando
    protected $signature = 'readings:generate';

    // Descripción del comando
    protected $description = 'Genera lecturas de temperatura y humedad para sensores activos';

    // Constantes de probabilidad
    private const ALERT_PROBABILITY_DENOMINATOR = 10;   // 1 de cada 10 lecturas puede tener alerta
    private const TEMP_NORMAL_MIN = 1500;              // 15.00°C
    private const TEMP_NORMAL_MAX = 2499;              // 24.99°C (justo bajo el umbral)
    private const TEMP_ALERT_MIN = 2501;               // 25.01°C (justo sobre el umbral)
    private const TEMP_ALERT_MAX = 3500;               // 35.00°C
    private const HUMID_NORMAL_MIN = 3000;             // 30.00%
    private const HUMID_NORMAL_MAX = 6999;             // 69.99% (justo bajo el umbral)
    private const HUMID_ALERT_MIN = 7001;              // 70.01% (justo sobre el umbral)
    private const HUMID_ALERT_MAX = 9000;              // 90.00%

    // Ejecutar el comando
    public function handle(): void
    {
        // Obtener sensores activos con bodega asignada
        $sensors = Sensor::where('status', true)
            ->whereNotNull('warehouse_id')
            ->get();

        if ($sensors->isEmpty()) {
            $this->info('No hay sensores activos con bodega asignada.');
            return;
        }

        $count = 0;

        foreach ($sensors as $sensor) {
            try {
                [$temperature, $humidity] = $this->generateReading();

                // Crear la lectura
                $reading = Reading::create([
                    'id'          => (string) Str::uuid(),
                    'sensor_id'   => $sensor->id,
                    'temperature' => $temperature,
                    'humidity'    => $humidity,
                    'recorded_at' => now(),
                ]);

                // Despachar evento para evaluación de alertas
                ReadingRecorded::dispatch($reading);

                $count++;

            } catch (Exception $e) {
                Log::error('Error al generar lectura para sensor ' . $sensor->id . ': ' . $e->getMessage());
            }
        }

        $this->info("Lecturas generadas: {$count}");
    }

    /**
     * Genera una lectura con distribución de alertas ~10%.
     *  - 90% de las veces: ambos valores dentro del rango normal
     *  - 10% de las veces: al menos un valor cruza el umbral
     *
     * @return array{0: float, 1: float} [temperature, humidity]
     */
    private function generateReading(): array
    {
        // 1 de cada N lecturas tendrá alerta
        $shouldAlert = mt_rand(1, self::ALERT_PROBABILITY_DENOMINATOR) === 1;

        if (! $shouldAlert) {
            // Caso normal: ambos valores seguros
            return [
                $this->randomInRange(self::TEMP_NORMAL_MIN, self::TEMP_NORMAL_MAX),
                $this->randomInRange(self::HUMID_NORMAL_MIN, self::HUMID_NORMAL_MAX),
            ];
        }

        // Caso con alerta: cada variable tiene 50% de probabilidad de cruzar el umbral
        $tempAlert  = mt_rand(0, 1) === 1;
        $humidAlert = mt_rand(0, 1) === 1;

        // Si por azar ambas salieron "false" (probabilidad 25%), forzar al menos una
        if (! $tempAlert && ! $humidAlert) {
            $tempAlert = (bool) mt_rand(0, 1);
        }

        return [
            $tempAlert
                ? $this->randomInRange(self::TEMP_ALERT_MIN, self::TEMP_ALERT_MAX)
                : $this->randomInRange(self::TEMP_NORMAL_MIN, self::TEMP_NORMAL_MAX),
            $humidAlert
                ? $this->randomInRange(self::HUMID_ALERT_MIN, self::HUMID_ALERT_MAX)
                : $this->randomInRange(self::HUMID_NORMAL_MIN, self::HUMID_NORMAL_MAX),
        ];
    }

    /**
     * Genera un entero aleatorio entre $min y $max (inclusive) y lo divide entre 100.
     */
    private function randomInRange(int $min, int $max): float
    {
        return mt_rand($min, $max) / 100;
    }
}
