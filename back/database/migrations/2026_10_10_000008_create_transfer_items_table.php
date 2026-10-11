<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Sin FK a transfers todavia: la tabla transfers se crea en HU-09.
            // Cuando se implemente HU-09 se agregara la FK aqui o en la migration
            // de transfers con alter table.
            $table->uuid('transfer_id');
            $table->uuid('inventory_id');
            $table->integer('quantity')->unsignedInteger();
            $table->integer('received_quantity')->unsignedInteger()->default(0);
            $table->timestamps();

            // UNIQUE: un inventario no puede aparecer dos veces en el mismo traslado.
            // Si el mismo lote en la misma bodega aparece dos veces, se mergean
            // en una sola linea en el frontend; no se crean dos filas.
            $table->unique(['transfer_id', 'inventory_id'], 'uq_transfer_item_inventory');

            // FK a inventories: la integridad referencial contra la existencia es
            // mandatoria. No se puede tener un item de traslado referenciando
            // una existencia que no existe.
            $table->foreign('inventory_id')
                ->references('id')
                ->on('inventories')
                ->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
    }
};
