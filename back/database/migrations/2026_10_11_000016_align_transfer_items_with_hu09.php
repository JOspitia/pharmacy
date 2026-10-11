<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinea `transfer_items` con la HU-09:
 *   - quantity          -> requested_quantity
 *   - received_quantity -> delivered_quantity
 *   - Agrega approved_quantity (nullable, lo llena el regente al aprobar)
 *
 * Tambien cambia la FK transfer_items.transfer_id de no action a
 * CASCADE, que es lo que exige la HU-09 (§2): si se borra un
 * traslado, se borran sus items. Sin CASCADE, un traslado sin
 * items no podria borrarse.
 *
 * Como SQL Server rechaza sp_rename sobre columnas en FKs, la
 * estrategia es: dropear la FK, dropear las columnas, recrear
 * con el nombre nuevo, recrear la FK con la regla nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) FK actual.
        $fk = DB::selectOne(
            "SELECT name FROM sys.foreign_keys
             WHERE parent_object_id = OBJECT_ID('transfer_items')
               AND referenced_object_id = OBJECT_ID('transfers')"
        );
        if ($fk !== null) {
            DB::statement("ALTER TABLE transfer_items DROP CONSTRAINT {$fk->name}");
        }

        // 2) CHECK si existe (no deberia pero por las dudas).
        DB::statement('ALTER TABLE transfer_items DROP CONSTRAINT IF EXISTS chk_transfer_items_qty_positive');

        // 3) En SQL Server, dropColumn NO dropea los UNIQUE constraints
        //    que referencian la columna. Hay que tirarlos a mano antes.
        $unique = DB::selectOne(
            "SELECT name FROM sys.indexes
             WHERE object_id = OBJECT_ID('transfer_items')
               AND is_unique = 1
               AND name = 'uq_transfer_item_inventory'"
        );
        if ($unique !== null) {
            DB::statement('DROP INDEX uq_transfer_item_inventory ON transfer_items');
        }

        // 4) Renombrar columnas: dropear y recrear.
        Schema::table('transfer_items', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'received_quantity']);
        });

        Schema::table('transfer_items', function (Blueprint $table) {
            $table->integer('requested_quantity');
            $table->integer('approved_quantity')->nullable();
            $table->integer('delivered_quantity')->default(0);
        });

        // 5) Re-crear la FK con CASCADE (HU-09 §2).
        Schema::table('transfer_items', function (Blueprint $table) {
            $table->foreign('transfer_id')
                ->references('id')->on('transfers')
                ->onDelete('cascade');
        });

        // 6) Re-crear el UNIQUE constraint (transfer_id, inventory_id).
        Schema::table('transfer_items', function (Blueprint $table) {
            $table->unique(['transfer_id', 'inventory_id'], 'uq_transfer_item_inventory');
        });
    }

    public function down(): void
    {
        $fk = DB::selectOne(
            "SELECT name FROM sys.foreign_keys
             WHERE parent_object_id = OBJECT_ID('transfer_items')
               AND referenced_object_id = OBJECT_ID('transfers')"
        );
        if ($fk !== null) {
            DB::statement("ALTER TABLE transfer_items DROP CONSTRAINT {$fk->name}");
        }

        Schema::table('transfer_items', function (Blueprint $table) {
            $table->dropColumn(['requested_quantity', 'approved_quantity', 'delivered_quantity']);
        });

        Schema::table('transfer_items', function (Blueprint $table) {
            $table->integer('quantity');
            $table->integer('received_quantity')->default(0);
        });

        Schema::table('transfer_items', function (Blueprint $table) {
            $table->foreign('transfer_id')
                ->references('id')->on('transfers')
                ->onDelete('no action');
        });
    }
};
