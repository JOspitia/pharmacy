<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la FK de transfer_items.transfer_id a transfers.id.
 *
 * La migracion 000008 (de HU-08) creo la tabla transfer_items pero dejo
 * transfer_id sin FK porque la tabla transfers todavia no existia. En
 * HU-09 creamos `transfers` (migracion 000011); ahora si podemos
 * declarar la integridad referencial.
 *
 * onDelete no action: un item de traslado no puede sobrevivir al
 * traslado. Si se intenta borrar un traslado con items, SQL Server
 * rechaza. Esto es coherente con la regla de negocio: cancelar un
 * traslado (RN-07) lo deja en estado CANCELLED, no lo borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_items', function (Blueprint $table) {
            $table->foreign('transfer_id')
                ->references('id')->on('transfers')
                ->onDelete('no action');
        });
    }

    public function down(): void
    {
        // Para tirar la FK en SQL Server hay que buscar el nombre
        // generado por Laravel. Si no se encuentra, no es error
        // (la migracion ya estaba bajada a un estado anterior).
        $fila = DB::selectOne(
            "SELECT name FROM sys.foreign_keys
             WHERE parent_object_id = OBJECT_ID('transfer_items')
               AND referenced_object_id = OBJECT_ID('transfers')"
        );

        if ($fila !== null) {
            DB::statement("ALTER TABLE transfer_items DROP CONSTRAINT {$fila->name}");
        }
    }
};
