<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Catalogo de roles del sistema. Reemplaza el ENUM de la columna
     * users.role: el nombre del rol pasa a ser un dato (aqui y en el seeder)
     * en vez de una lista quemada en el esquema.
     *
     * No lleva softDeletes porque no hay CRUD de roles: no existe la operacion
     * "eliminar" contra la que un borrado logico tendria sentido. Un rol que
     * deja de usarse simplemente no se asigna a nadie.
     *
     * El PK es bigint y no uuid a diferencia de las tablas de dominio
     * (products, bodegas, lots): aqui no hay id expuesto en la API y users ya
     * usa bigint, de modo que el catalogo y su consumidor comparten tipo.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};