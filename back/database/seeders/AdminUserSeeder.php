<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crear el usuario administrador si no existe
     */
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'admin',
                'password' => Hash::make('password'),
            ]
        );
    }
}
