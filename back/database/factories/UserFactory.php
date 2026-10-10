<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
            // Por defecto los usuarios de prueba no son admin, para que un test
            // no pase sin querer por el Gate::before.
            //
            // firstOrCreate y no un id fijo: los tests corren con
            // RefreshDatabase, que aplica las migraciones pero no los seeders.
            // Buscar el rol por id fijo fallaria en toda prueba, y buscarlo por
            // nombre crea el que falta.
            'role_id' => fn () => Role::firstOrCreate([
                'name' => 'auxiliar_farmacia',
            ])->id,
        ];
    }

    /**
     * Usuario con un rol concreto.
     *
     * @param  string  $role  Nombre del rol, por ejemplo 'regente_farmacia'.
     */
    public function conRol(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::firstOrCreate(['name' => $role])->id,
        ]);
    }

    public function admin(): static
    {
        return $this->conRol('admin');
    }

    public function regente(): static
    {
        return $this->conRol('regente_farmacia');
    }

    public function auxiliar(): static
    {
        return $this->conRol('auxiliar_farmacia');
    }

    public function medico(): static
    {
        return $this->conRol('medico');
    }

    public function auditor(): static
    {
        return $this->conRol('auditor');
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}