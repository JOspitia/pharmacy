<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinea la tabla `transfers` con la HU-09 (documentacion/HU-09-Traslados.md).
 *
 * La migracion 000011 creo `transfers` con nombres de columna
 * descriptivos (warehouse_origin_id, warehouse_destiny_id) que divergen
 * de la documentacion oficial. La HU-09 espera `from_warehouse_id` y
 * `to_warehouse_id`, en linea con la convencion del resto del proyecto
 * (los nombres de columna son en ingles y van al grano).
 *
 * Cambios:
 *   - transfers.warehouse_origin_id  -> transfers.from_warehouse_id
 *   - transfers.warehouse_destiny_id -> transfers.to_warehouse_id
 *   - Se agrega transfers.transfer_number UNIQUE NOT NULL con el formato
 *     TRF-YYYYMMDD-XXXX que ya genera el modelo Transfer.
 *   - El CHECK chk_transfers_origin_ne_destiny se renombra a
 *     chk_transfers_diff_warehouses.
 *
 * Para los renombres, SQL Server rechaza sp_rename sobre columnas
 * que participan en FKs o CHECKs enforced. La estrategia es: dropear
 * FKs y CHECK dependientes, dropear las columnas viejas, recrear las
 * columnas con el nombre nuevo, recrear FKs y CHECK.
 *
 * El orden importa: si dropeamos una FK que referencia una columna
 * y despues dropeamos la columna, la operacion es mas limpia que
 * intentar renombrar. Y recreamos todo al final con el nuevo
 * nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Tirar las FKs que dependen de las columnas a renombrar.
        $fks = DB::select(
            "SELECT name FROM sys.foreign_keys
             WHERE parent_object_id = OBJECT_ID('transfers')"
        );
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE transfers DROP CONSTRAINT {$fk->name}");
        }

        // 2) Tirar el CHECK que referencia las columnas.
        DB::statement('ALTER TABLE transfers DROP CONSTRAINT IF EXISTS chk_transfers_origin_ne_destiny');

        // 3) Tirar los indices que mencionan las columnas a dropear.
        //    En SQL Server, los indices automaticos creados por
        //    $table->index() viven en sys.indexes, no en sys.foreign_keys.
        $indexes = DB::select(
            "SELECT name FROM sys.indexes
             WHERE object_id = OBJECT_ID('transfers')
               AND is_unique = 0
               AND name <> 'uq_transfers_transfer_number'"
        );
        foreach ($indexes as $idx) {
            DB::statement("DROP INDEX {$idx->name} ON transfers");
        }

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['warehouse_origin_id', 'warehouse_destiny_id']);
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->uuid('from_warehouse_id');
            $table->uuid('to_warehouse_id');
        });

        // 4) Recreate FKs con el nombre nuevo.
        Schema::table('transfers', function (Blueprint $table) {
            $table->foreign('from_warehouse_id')
                ->references('id')->on('bodegas')
                ->onDelete('no action');
            $table->foreign('to_warehouse_id')
                ->references('id')->on('bodegas')
                ->onDelete('no action');
        });

        // 4b) Re-crear los indices.
        Schema::table('transfers', function (Blueprint $table) {
            $table->index('from_warehouse_id');
            $table->index('to_warehouse_id');
            $table->index(['status_id', 'created_at']);
        });

        // 5) Re-agregar el CHECK con el nombre nuevo.
        DB::statement(
            'ALTER TABLE transfers ADD CONSTRAINT chk_transfers_diff_warehouses
             CHECK (from_warehouse_id <> to_warehouse_id)'
        );

        // 6) Agregar transfer_number nullable + unique. La migracion
        //    000015 (siguiente) hace el backfill y lo vuelve NOT NULL.
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('transfer_number', 20)->nullable()->after('id');
            $table->unique('transfer_number', 'uq_transfers_transfer_number');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropUnique('uq_transfers_transfer_number');
            $table->dropColumn('transfer_number');
        });

        $fks = DB::select(
            "SELECT name FROM sys.foreign_keys
             WHERE parent_object_id = OBJECT_ID('transfers')"
        );
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE transfers DROP CONSTRAINT {$fk->name}");
        }

        $indexes = DB::select(
            "SELECT name FROM sys.indexes
             WHERE object_id = OBJECT_ID('transfers')
               AND is_unique = 0"
        );
        foreach ($indexes as $idx) {
            DB::statement("DROP INDEX {$idx->name} ON transfers");
        }

        DB::statement('ALTER TABLE transfers DROP CONSTRAINT IF EXISTS chk_transfers_diff_warehouses');

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['from_warehouse_id', 'to_warehouse_id']);
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->uuid('warehouse_origin_id');
            $table->uuid('warehouse_destiny_id');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->foreign('warehouse_origin_id')
                ->references('id')->on('bodegas')
                ->onDelete('no action');
            $table->foreign('warehouse_destiny_id')
                ->references('id')->on('bodegas')
                ->onDelete('no action');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->index('warehouse_origin_id');
            $table->index('warehouse_destiny_id');
        });

        DB::statement(
            'ALTER TABLE transfers ADD CONSTRAINT chk_transfers_origin_ne_destiny
             CHECK (warehouse_origin_id <> warehouse_destiny_id)'
        );
    }
};
