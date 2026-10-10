<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Restringe un grupo de rutas a un modulo, con el permiso deducido del verbo.
 *
 * Uso en rutas:
 *     Route::middleware('module:warehouses')->group(function () {
 *         Route::apiResource('bodegas', BodegaController::class);
 *     });
 *
 * El middleware no recibe una lista de roles ni de permisos: recibe el modulo
 * y consulta el verbo de la peticion. Por eso escribir una ruta nueva no
 * obliga a decidir de nuevo que puede hacer quien la llama.
 *
 *   GET            -> can_read
 *   POST           -> can_create
 *   PUT / PATCH    -> can_update
 *   DELETE         -> can_delete
 *
 * El permiso se compone como {modulo}.{sufijo}, por ejemplo
 * "warehouses.can_read", y se consulta contra la tabla permissions. El
 * middleware resuelve SOLO permisos CRUD: una capacidad como
 * "autorizar-control-especial" no pertenece a un verbo HTTP ni a una ruta, y
 * se consulta con Gate::allows().
 *
 * Cubre un caso que el middleware por rol no podia expresar: una ruta era
 * indivisible, el mismo grupo servia para el listado y para el borrado, y
 * limitar el borrado exigia abrir otro grupo con otra lista de roles. Con el
 * verbo como parte de la decision, listado y borrado conviven en el mismo
 * grupo con permisos distintos.
 */
class EnsureModule
{
    /**
     * Verbo HTTP -> sufijo del permiso CRUD que exige.
     *
     * VIVE ACA Y NO EN EL MODELO User por capas. El diccionario traduce una
     * cosa de transporte (el verbo de la peticion) a una cosa de dominio (el
     * sufijo del permiso). Esa traduccion pertenece a la capa web, que es la
     * unica que ve verbos HTTP, y no al modelo de usuario, que describe la
     * persona y su rol.
     *
     * Estuvo en User como constante durante un tiempo y no habia motivo: su unico
     * consumidor es este middleware, asi que no existia riesgo de que dos copias
     * se desincronizasen. Lo que si seria un error es que un segundo consumidor
     * aparezca y alguien copie el diccionario; por eso queda privado.
     *
     * Estos son sufijos, no permisos completos. El permiso real es
     * {modulo}.{sufijo}, por ejemplo "warehouses.can_read", y lo arma
     * Permission::crudName().
     *
     * @var array<string, string>
     */
    private const SUFIJO_POR_VERBO = [
        'GET' => 'can_read',
        'HEAD' => 'can_read',
        'POST' => 'can_create',
        'PUT' => 'can_update',
        'PATCH' => 'can_update',
        'DELETE' => 'can_delete',
    ];

    public function handle(Request $request, Closure $next, string ...$modules)
    {
        $module = $modules[0] ?? null;

        // Sin modulo no hay nada que comprobar. Es un error de configuracion
        // de la ruta, no un intento de acceso: se responde 403 plano porque el
        // usuario no tiene por que ver la diferencia.
        if ($module === null) {
            return response()->json([
                'message' => 'No tiene permisos para realizar esta accion.',
            ], 403);
        }

        // La ruta deberia ir dentro del grupo auth:sanctum. Si no hay usuario
        // se responde 403 y no 401 a proposito: la ausencia de token es un
        // problema de autenticacion que ya cubre Sanctum.
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'No tiene permisos para realizar esta accion.',
            ], 403);
        }

        $accion = self::SUFIJO_POR_VERBO[strtoupper($request->method())] ?? null;

        // Verbo sin sufijo asociado (OPTIONS, TRACE). Denegar es la respuesta
        // segura: no se concede nada por no haberlo pensado.
        if ($accion === null) {
            return response()->json([
                'message' => 'No tiene permisos para realizar esta accion.',
            ], 403);
        }

        $requerido = Permission::crudName($module, $accion);

        // Se consulta con can() y no llamando directo a hasPermission() para
        // que el middleware pase por el pipeline de autorizacion completo:
        // Gate::before, Policies y cualquier hook que se agregue despues
        // aplican tambien a las rutas. Llamar al modelo directo saltaba esa
        // cadena.
        if (! $user->can($requerido)) {
            return response()->json([
                'message' => 'No tiene permisos para realizar esta accion.',
                'module' => $module,
                'required_permission' => $requerido,
            ], 403);
        }

        return $next($request);
    }
}