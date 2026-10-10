<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Retira role_modules, el modelo descartado.
     *
     * La tabla se creo en la migracion 000007 y no se borra esa migracion: ya
     * se ejecuto en la base de desarrollo, y reescribir el historico deja el
     * esquema instalado sin forma de converger con el del repositorio. Crear y
     * despues eliminar deja la historia explicita y reproducible con
     * migrate:fresh.
     *
     * El orden importa. role_modules.module_id apunta a modules y
     * role_modules.role_id a roles; ninguna de las dos tablas se va en esta
     * HU, asi que se puede borrar sin tocar constraints ajenas.
     */
    public function up(): void
    {
        Schema::dropIfExists('role_modules');
    }

    /**
     * Reverse the migrations.
     *
     * La reversa no se reconstruye. Devolver la tabla con sus datos seria
     * inventar informacion que no se guardo en ningun lado: el contenido
     * original vivia en role_modules y sus valores se trasladaron a
     * role_permissions en los seeders, no en una copia.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'La migracion 2026_10_09_000011 no es reversible: role_modules fue '
            . 'descartada y sus datos se trasladaron a role_permissions por '
            . 'seeder, no por copia. Para volver atras hay que reconstruirla '
            . 'desde una migracion nueva.'
        );
    }
};