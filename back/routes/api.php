<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BodegaController;
use App\Http\Controllers\GroupController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
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

    // Grupos
    Route::patch('groups/{group}/toggle-status', [GroupController::class, 'toggleStatus']);
    Route::apiResource('groups', GroupController::class);

    // Bodegas
    Route::patch('bodegas/{bodega}/toggle-status', [BodegaController::class, 'toggleStatus']);
    Route::apiResource('bodegas', BodegaController::class);
});
