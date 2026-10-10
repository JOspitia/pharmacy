<?php

namespace App\Http\Controllers;

/**
 * Endpoints de observabilidad para load balancers y orquestadores.
 *
 * /health — liveness probe: el proceso está vivo.
 *            Devuelve 200 si PHP puede responder.
 *
 * /ready  — readiness probe: la aplicación puede atender tráfico.
 *            Verifica conexión a la base de datos. Si la DB no responde,
 *            el orquestador no debería mandarle tráfico.
 */
class HealthController extends Controller
{
    /**
     * Liveness probe. Si este endpoint responde 200, el contenedor se considera vivo.
     * No verifica nada externo: si PHP puede ejecutar código, está vivo.
     */
    public function health()
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * Readiness probe. Verifica que la base de datos responda.
     * Si este falla, el orquestador debe dejar de mandarle tráfico.
     */
    public function ready()
    {
        try {
            \DB::connection()->getPdo();
            return response()->json(['status' => 'ok', 'database' => 'connected']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'database' => 'disconnected',
                'message' => $e->getMessage(),
            ], 503);
        }
    }
}
