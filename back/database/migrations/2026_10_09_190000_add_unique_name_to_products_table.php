<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-01 exige que products.name sea UNIQUE. La migracion original de la tabla
 * no declaro esa restriccion, por lo que el nombre no era unico ni en base de
 * datos ni en las capas de validacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unique('name', 'products_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_name_unique');
        });
    }
};