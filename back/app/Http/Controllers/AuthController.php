<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    protected AuthService $authService;

    /**
     * Dependencia del AuthService
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Iniciar sesión y emitir un token
     */
    public function login(LoginRequest $request)
    {
        $authData = $this->authService->login($request->validated());

        return response()->json([
            'message' => 'Login exitoso',
            'user'    => new UserResource($authData['user']),
            'token'   => $authData['token'],
        ]);
    }

    /**
     * Cerrar sesión y revocar el token actual
     */
    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}
