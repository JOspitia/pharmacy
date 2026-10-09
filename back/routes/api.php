<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Ruta para buscar grupos por nombre
    Route::get('groups/search', [GroupController::class, 'search']);

    // Ruta para activar/inactivar un grupo
    Route::patch('groups/{group}/toggle-status', [GroupController::class, 'toggleStatus']);

    // Ruta para crud de grupos
    Route::apiResource('groups', GroupController::class);
});
