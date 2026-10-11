<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega soft deletes a la tabla transfers.
 *
 * Coherente con la migracion 000010 que ya agrego soft deletes a
 * transfer_items. La razon: cuando un traslado se cancela, el documento
 * historico se conserva (para auditoria y para responder "que movio X
 * el dia Y" cinco anos despues). El soft delete cubre el caso de
 * borrado logico por error del operador: el registro sigue en la
 * base, pero no aparece en los listados por defecto.
 *
 * onDelete no action de las FKs ya asegura que nadie pueda borrar la
 * fila via SQL directo: para "borrar" un traslado, hay que pasar por
 * el flujo CANCELLED del TransferService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
