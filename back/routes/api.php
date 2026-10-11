<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\BodegaController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\TransferController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
|
*/

// Rutas públicas
// Observabilidad: probes para orquestadores y load balancers. Sin auth.
Route::get('/health', [HealthController::class, 'health']);
Route::get('/ready', [HealthController::class, 'ready']);

Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas por Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // UserResource y no el modelo crudo: el rol ahora es una relacion, y
    // devolverlo sin serializar anidaria el objeto Role entero en la respuesta.
    Route::get('/user', function (Request $request) {
        return new \App\Http\Resources\UserResource($request->user());
    });

    // Autenticación
    Route::post('/logout', [AuthController::class, 'logout']);

    // units/select queda fuera de los grupos a proposito: es un catalogo de
    // referencia que alimenta los formularios de productos y de lotes, y no
    // tiene permisos propios. Protegerlo exigiria duplicar en un modulo
    // inexistente el permiso de productos y el de lotes, y el que se eligiera
    // dejaria al otro rol sin acceso a su propio formulario.
    Route::get('units/select', [UnitController::class, 'select']);

    // ------------------------------------------------------------------
    // Permisos por módulo
    //
    // El nombre del módulo coincide con el prefijo de ruta, y el permiso
    // concreto lo deduce EnsureModule del verbo HTTP. Por eso el listado y
    // el borrado de la misma entidad conviven en un único grupo: antes, con
    // un middleware por rol, Limitarlos pedía abrir un grupo aparte.
    // ------------------------------------------------------------------

    Route::middleware('module:groups')->group(function () {
        // Listados para selects
        Route::get('groups/select', [GroupController::class, 'select']);

        Route::patch('groups/{group}/toggle-status', [GroupController::class, 'toggleStatus']);
        Route::apiResource('groups', GroupController::class);
    });

    Route::middleware('module:products')->group(function () {
        Route::get('products/form-data', [ProductController::class, 'formData']);
        Route::patch('products/{product}/toggle-status', [ProductController::class, 'toggleStatus']);
        Route::apiResource('products', ProductController::class);
    });

    Route::middleware('module:warehouses')->group(function () {
        Route::patch('bodegas/{bodega}/toggle-status', [BodegaController::class, 'toggleStatus']);
        // HU-08, CA-12: selector de lote por bodega para el formulario de
        // Kardex. Va en el grupo de warehouses porque la URL es /bodegas/{id}/lots.
        Route::get('bodegas/{bodega}/lots', [BodegaController::class, 'lots']);
        Route::apiResource('bodegas', BodegaController::class);
    });

    Route::middleware('module:lots')->group(function () {
        Route::get('lots/form-data', [LotController::class, 'formData']);
        Route::patch('lots/{lot}/toggle-status', [LotController::class, 'toggleStatus']);
        Route::apiResource('lots', LotController::class);
    });

    // Sensores y lecturas van en el mismo grupo a propósito: las lecturas
    // cuelgan de una bodega en la URL (bodegas/{id}/readings) pero describen
    // el estado ambiental de un sensor, que es lo que el usuario viene a
    // mirar. Ponerlas en module:warehouses haría que un auxiliar pudiera leer
    // alertas de temperatura sin acceso a sensores, que es justo lo que la
    // matriz le niega.
    Route::middleware('module:sensors')->group(function () {
        Route::get('sensors/form-data', [SensorController::class, 'formData']);
        Route::patch('sensors/{sensor}/toggle-status', [SensorController::class, 'toggleStatus']);
        Route::apiResource('sensors', SensorController::class);

        // Lecturas
        Route::get('bodegas/{bodega}/readings', [ReadingController::class, 'index']);
        Route::get('bodegas/{bodega}/alerts', [ReadingController::class, 'alerts']);
    });

    Route::middleware('module:reports')->group(function () {
        Route::get('reportes/inventario', [ReportController::class, 'index']);
    });

    // ------------------------------------------------------------------
    // Kardex (HU-08) — modulo de existencias y movimientos
    //
    // Solo lectura: todos los roles autenticados pueden consultar (HU-08 §7).
    // Ajuste (crear movimiento): solo regente_farmacia y admin (HU-08 §7).
    // ------------------------------------------------------------------
    Route::middleware('module:kardex')->group(function () {
        Route::get('inventories', [InventoryController::class, 'index']);
        Route::get('movements', [MovementController::class, 'index']);

        // Ajuste de inventario: solo regente y admin tienen can_create.
        // El middleware module:kardex ya filtra por can_create para POST.
        Route::post('movements', [MovementController::class, 'store']);
    });

    // Kardex inmutable (RN-06, CA-05): PUT/PATCH/DELETE devuelven 405
    // explicito. Se registran AFUERA del middleware module:kardex a
    // proposito: si quedaran adentro, el middleware rechazaria con 403 al
    // verificar can_update/can_delete (que nadie tiene) ANTES de llegar al
    // controller, y el cliente nunca veria el 405 que documenta la
    // inmutabilidad.
    Route::put('movements/{id}', [MovementController::class, 'update']);
    Route::patch('movements/{id}', [MovementController::class, 'update']);
    Route::delete('movements/{id}', [MovementController::class, 'destroy']);

    // ------------------------------------------------------------------
    // Traslados (HU-09, RN-07) — maquina de 7 estados
    //
    // La matriz de permisos (RolePermissionSeeder) reparte CRUD entre
    // roles: regente tiene CRUD completo, auxiliar tiene C R U (sin
    // delete), admin/medico/auditor solo R. El middleware module:transfers
    // filtra segun la accion HTTP.
    //
    // Las acciones de la maquina de estados (approve, dispatch, receive,
    // cancel) son PATCH por convencion REST: modifican parcialmente un
    // recurso existente. No son CRUD sobre los items, asi que viven
    // como rutas explicitas en vez de apiResource.
    // ------------------------------------------------------------------
    Route::middleware('module:transfers')->group(function () {
        Route::get('transfers', [TransferController::class, 'index']);
        Route::post('transfers', [TransferController::class, 'store']);
        Route::get('transfers/{id}', [TransferController::class, 'show']);
        Route::patch('transfers/{id}/approve', [TransferController::class, 'approve']);
        Route::patch('transfers/{id}/dispatch', [TransferController::class, 'dispatchTransfer']);
        Route::patch('transfers/{id}/receive', [TransferController::class, 'receive']);
        Route::patch('transfers/{id}/cancel', [TransferController::class, 'cancel']);
        // RN-07: cerrar una discrepancia sin tocar stock.
        Route::patch(
            'transfers/{id}/discrepancies/{discrepancyId}/resolve',
            [TransferController::class, 'resolveDiscrepancy']
        );
    });

    // PUT/DELETE explicitos no se registran: un traslado no se
    // "edita" completo (la HU-09 trabaja con transiciones de estado
    // parciales) y la cancelacion es via PATCH /cancel, no DELETE.
    // Devolver 404 por defecto de Laravel es la respuesta correcta:
    // la operacion no existe, no es un 405.
});