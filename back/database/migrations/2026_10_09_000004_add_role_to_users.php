<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * La seccion 3 del enunciado define cinco roles. Se guardan en una columna
     * de texto acotada mas un CHECK, en vez de texto libre, para que la base de
     * datos rechace cualquier rol inventado, que es la misma razon por la que el
     * stock tiene un CHECK.
     *
     * POR QUE NO ES UN ENUM: SQL Server no tiene ENUM y Laravel no lo emula;
     * el grammar de sqlsrv no implementa typeEnum y la migracion falla. El CHECK
     * cumple la misma funcion y ademas es lo que el enunciado pide al recomendar
     * SQL Server "por el bloqueo de filas y las restricciones CHECK".
     *
     * NOTA: la migracion 2026_10_09_000008 elimina esta columna y la sustituye
     * por una FK a la tabla roles. Esta migracion existe solo para mantener la
     * cadena completa de migraciones; el estado final del modelo ya no la tiene.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)
                ->default('auxiliar_farmacia')
                ->comment('Rol segun la seccion 3 del enunciado. Sustituido por role_id en la migracion 000008.');
        });

        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN "
            . "('auxiliar_farmacia', 'regente_farmacia', 'medico', 'auditor', 'admin'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_role');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};