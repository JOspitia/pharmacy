<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea un usuario por cada rol de la seccion 3 del enunciado.
 *
 * Todos comparten la contrasena "password" para que la evaluacion pueda entrar
 * a cada rol sin tener que descifrar hashes. Son datos sinteticos: el enunciado
 * prohibe expresamente usar datos reales.
 *
 * updateOrCreate y no create para que el seeder sea idempotente y se pueda
 * correr mas de una vez sobre la misma base, que es el requisito de "migraciones
 * y datos semilla automaticos y repetibles".
 */
class UserSeeder extends Seeder
{
    public function run()
    {
        $usuarios = [
            [
                'name' => 'admin',
                'email' => 'admin@test.com',
                'role' => 'admin',
            ],
            [
                'name' => 'regente',
                'email' => 'regente@test.com',
                'role' => 'regente_farmacia',
            ],
            [
                'name' => 'auxiliar',
                'email' => 'auxiliar@test.com',
                'role' => 'auxiliar_farmacia',
            ],
            [
                'name' => 'medico',
                'email' => 'medico@test.com',
                'role' => 'medico',
            ],
            [
                'name' => 'auditor',
                'email' => 'auditor@test.com',
                'role' => 'auditor',
            ],
        ];

        foreach ($usuarios as $usuario) {
            User::updateOrCreate(
                ['email' => $usuario['email']],
                [
                    'name' => $usuario['name'],
                    'password' => Hash::make('password'),
                    'role' => $usuario['role'],
                ]
            );
        }
    }
}