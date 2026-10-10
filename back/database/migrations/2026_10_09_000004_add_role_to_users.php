<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * La seccion 3 del enunciado define cinco roles. Se guardan como ENUM y
     * no como texto libre para que la base de datos rechace cualquier rol
     * inventado, que es la misma razon por la que el stock tiene un CHECK.
     *
     * El enum es util aqui porque el conjunto de roles es cerrado y no existe
     * un modulo de administracion de roles que obligue a cambiarlo.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'auxiliar_farmacia',
                'regente_farmacia',
                'medico',
                'auditor',
                'admin',
            ])
                ->default('auxiliar_farmacia')
                ->after('email')
                ->comment('Rol segun la seccion 3 del enunciado. Define que capacidades tiene el usuario.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};