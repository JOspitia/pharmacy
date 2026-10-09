<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * RN-11: el sistema debe alertar productos por debajo del stock minimo
     * definido por bodega.
     * Cada bodega tiene su propio umbral de alerta.
     */
    public function up(): void
    {
        Schema::table('bodegas', function (Blueprint $table) {
            $table->integer('stock_minimo')
                ->default(0)
                ->after('status')
                ->comment('Umbral de alerta: genera advertencia cuando el stock total de un producto en esta bodega cae por debajo de este valor.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bodegas', function (Blueprint $table) {
            $table->dropColumn('stock_minimo');
        });
    }
};
