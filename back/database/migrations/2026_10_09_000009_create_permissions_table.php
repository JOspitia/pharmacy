<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Catalogo de permisos.
     *
     * POR QUE UNA TABLA Y NO COLUMNAS: el primer diseño de esta HU metio los
     * permisos CRUD como cuatro booleanos en role_modules (can_read,
     * can_create, can_update, can_delete). Eso modela bien el CRUD y nada mas.
     * El proyecto tiene permisos que no son CRUD —aprobar-traslado,
     * autorizar-control-especial, dispensar— y con cuatro columnas no hay
     * donde ponerlos: o se agrega una columna por capacidad, con su
     * migracion y su deploy, o quedan escritos en el array CAPACIDADES de
     * AuthServiceProvider, que es el mismo problema que esta HU elimino al
     * sacar el ENUM de users.role.
     *
     * Por eso un permiso es una fila con nombre. Agregar uno es un INSERT.
     *
     * EL NAME GUARDA LA CLAVE COMPLETA, no un par con module_id: un permiso de
     * CRUD se llama "warehouses.can_read" y una capacidad "autorizar-control-especial",
     * que ademas tiene module_id NULL. Con un UNIQUE sobre (name, module_id) las
     * capacidades quedarian sin proteccion, porque en SQL Server dos filas con
     * module_id NULL se consideran distintas entre si y el UNIQUE las deja
     * pasar duplicadas. Con un solo name UNIQUE no hay caso raro.
     *
     * No lleva softDeletes: no hay CRUD de permisos, se administran por seeder.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();

            // Nullable a proposito: un permiso sin modulo es una capacidad
            // global, que no se pide por ruta sino con Gate::allows().
            $table->unsignedBigInteger('module_id')->nullable();

            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};