<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Restringe una ruta a uno o varios roles.
 *
 * Uso en rutas:
 *   Route::patch('bodegas/{bodega}/toggle-status', ...)
 *       ->middleware('role:admin,regente_farmacia');
 *
 * Los roles se pasan separados por coma y la comprobacion es "alguno de estos".
 * Para exigir varios a la vez hay que encadenar el middleware varias veces.
 *
 * Este middleware comprueba el ROL. Para comprobar una CAPACIDAD concreta
 * conviene el Gate: Gate::allows('aprobar-traslado'), porque las capacidades
 * ya estan centralizadas en AuthServiceProvider.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        // La ruta deberia ir dentro del grupo auth:sanctum. Si no hay usuario
        // se responde 403 y no 401 a proposito: noTOKEN es un problema de
        // autenticacion que ya cubre el middleware de Sanctum.
        if ($user === null || ! $user->hasAnyRole($roles)) {
            return response()->json([
                'message' => 'No tiene permisos para realizar esta accion.',
                'required_roles' => $roles,
            ], 403);
        }

        return $next($request);
    }
}