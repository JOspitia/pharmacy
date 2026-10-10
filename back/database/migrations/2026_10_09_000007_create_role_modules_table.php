<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Matriz de permisos: que puede hacer cada rol en cada modulo. Una fila
     * ausente significa "sin acceso a ese modulo"; una fila presente con los
     * cuatro permisos en false significa "puede ver el modulo pero no hacer
     * nada en el", que es un estado inutil y por eso la matriz no lo usa.
     *
     * Los cuatro booleanos se nombran por el verbo HTTP que exigen:
     * GET=can_read, POST=can_create, PUT/PATCH=can_update, DELETE=can_delete.
     * Esa correspondencia es la que lee EnsureModule.
     *
     * Es tabla intermedia: no lleva timestamps ni softDeletes porque sus filas
     * no tienen valor propio, solo expresa la relacion. Por la regla
     * global de onDelete del proyecto (AI_USAGE.md seccion 4.2) las FK van en
     * CASCADE: si se elimina un rol o un modulo, sus permisos desaparecen
     * solos en vez de dejar filas apuntando a algo que ya no existe.
     */
    public function up(): void
    {
        Schema::create('role_modules', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();

            $table->boolean('can_read')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);

            // PK compuesta: un rol tiene como mucho una fila por modulo, y es
            // esa restriccion la que hace valida la matriz.
            $table->primary(['role_id', 'module_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_modules');
    }
};