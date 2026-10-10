<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Que permisos tiene cada rol.
     *
     * La asignacion es fila, no columna: role_modules guardaba los cuatro
     * permisos CRUD como booleanos en la misma tabla quecia el modulo. Con
     * permissions el CRUD pasa a ser un permiso mas, y las capacidades dejan de
     * necesitar un sitio especial.
     *
     * Sin timestamps ni softDeletes porque sus filas no tienen valor propio:
     * expresan la relacion entre un rol y un permiso. Segun la regla global de
     * onDelete (AI_USAGE.md seccion 4.2), una tabla transaccional va en
     * CASCADE: si se elimina un rol, un modulo o un permiso, las asignaciones
     * desaparecen solas en vez de quedar apuntando a algo que ya no existe.
     */
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();

            // Un rol tiene como mucho una fila por permiso, y es esa restriccion
            // la que hace valida la matriz.
            $table->primary(['role_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};