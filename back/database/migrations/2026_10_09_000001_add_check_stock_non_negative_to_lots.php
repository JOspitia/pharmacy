<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * RN-03: el stock nunca puede quedar negativo, ni siquiera con
     * solicitudes simultaneas por la ultima unidad.
     * La restriccion se defiende en la base de datos, no solo en codigo.
     *
     * MySQL 8 admite CHECK constraints y los evalua activamente,
     * a diferencia de MySQL 5.7 que los ignoraba.
     */
    public function up(): void
    {
        // MySQL no permite agregar CHECK con ALTER TABLE directamente
        // si la tabla ya existe y tiene datos. Se usa un CHECK directamente.
        // La sintaxis de expression constraint es soportada por MySQL 8.
        DB::statement("ALTER TABLE lots ADD CONSTRAINT chk_lots_stock_non_negative CHECK (stock >= 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE lots DROP CONSTRAINT chk_lots_stock_non_negative");
    }
};
