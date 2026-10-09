<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BodegaController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\ReportController;

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
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas por Sanctum
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Autenticación
    Route::post('/logout', [AuthController::class, 'logout']);

    // Listados para selects
    Route::get('groups/select', [GroupController::class, 'select']);
    Route::get('units/select', [UnitController::class, 'select']);

    // Grupos
    Route::patch('groups/{group}/toggle-status', [GroupController::class, 'toggleStatus']);
    Route::apiResource('groups', GroupController::class);

    // Bodegas
    Route::patch('bodegas/{bodega}/toggle-status', [BodegaController::class, 'toggleStatus']);
    Route::apiResource('bodegas', BodegaController::class);

    // Productos
    Route::get('products/form-data', [ProductController::class, 'formData']);
    Route::patch('products/{product}/toggle-status', [ProductController::class, 'toggleStatus']);
    Route::apiResource('products', ProductController::class);

    // Lotes
    Route::get('lots/form-data', [LotController::class, 'formData']);
    Route::patch('lots/{lot}/toggle-status', [LotController::class, 'toggleStatus']);
    Route::apiResource('lots', LotController::class);

    // Sensores
    Route::get('sensors/form-data', [SensorController::class, 'formData']);
    Route::patch('sensors/{sensor}/toggle-status', [SensorController::class, 'toggleStatus']);
    Route::apiResource('sensors', SensorController::class);

    // Lecturas
    Route::get('bodegas/{bodega}/readings', [ReadingController::class, 'index']);
    Route::get('bodegas/{bodega}/alerts', [ReadingController::class, 'alerts']);

    // Reportes
    Route::get('reportes/inventario', [ReportController::class, 'index']);
});
