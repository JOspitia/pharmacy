<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla de existencias: el stock vive aca, no en lots.
 *
 * RN-01 dice que "cada existencia se identifica por bodega + producto + lote".
 * Eso es literalmente la constraint de esta tabla. Antes el stock vivia en
 * lots.stock, lo que silenciaba la dimension bodega: un lote era una
 * existencia, y no habia forma de representar el mismo lote en dos bodegas.
 *
 * Sin esa dimension, los traslados entre bodegas no tienen donde aterrizar
 * (ver HU-09), porque no se puede crear una existencia en destino para un
 * lote que ya existe en origen.
 *
 * El CHECK quantity >= 0 se agrega con DB::statement porque el grammar de
 * sqlsrv no implementa typeEnum ni una variante de check() que se aplique
 * sobre una tabla recien creada. El brief recomienda SQL Server
 * precisamente "por el bloqueo de filas y las restricciones CHECK".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('warehouse_id');
            $table->uuid('product_id');
            $table->uuid('lot_id');
            $table->integer('quantity')->default(0);
            $table->integer('min_stock')->nullable();
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('bodegas')->onDelete('no action');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('no action');
            $table->foreign('lot_id')->references('id')->on('lots')->onDelete('no action');

            // RN-01. Sin esto, dos filas podrian describir la misma terna y
            // el stock quedaria partido sin que nada lo impida.
            $table->unique(['warehouse_id', 'product_id', 'lot_id'], 'uq_inventories_existencia');

            // FEFO ordena por vencimiento a traves del lote; warehouse_id es
            // el filtro mas usado de la pantalla de inventario.
            $table->index('warehouse_id');
            $table->index('product_id');
        });

        DB::statement(
            'ALTER TABLE inventories ADD CONSTRAINT chk_inventories_quantity_non_negative CHECK (quantity >= 0)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inventories DROP CONSTRAINT chk_inventories_quantity_non_negative');

        Schema::dropIfExists('inventories');
    }
};