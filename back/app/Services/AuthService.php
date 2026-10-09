<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Verificar credenciales y emitir un token de acceso
     */
    public function login(array $data): array
    {
        $user = User::where('name', $data['name'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'name' => ['Las credenciales de acceso son incorrectas.'],
            ]);
        }

        return [
            'user'  => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
        ];
    }

    /**
     * Revocar el token de la sesión actual
     */
    public function logout($user): void
    {
        $user->currentAccessToken()->delete();
    }
}
