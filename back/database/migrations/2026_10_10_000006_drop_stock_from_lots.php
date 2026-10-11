<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina lots.stock y su CHECK.
 *
 * RN-01 dice que el stock pertenece a la existencia (bodega x producto x
 * lote), no al lote. Con la columna en lots, un lote era una sola existencia
 * en una sola bodega, y por eso no se podia representar un traslado: no
 * habia forma de tener el mismo lote con stock en dos bodegas a la vez.
 *
 * El CHECK chk_lots_stock_non_negative se creo en la migracion 000001. Hay
 * que tirar la constraint antes de la columna: SQL Server no permite quitar
 * una columna que una constraint activa referencia.
 *
 * Guardar el stock en dos lugares a la vez (inventories.quantity y
 * lots.stock) fue una alternativa para no romper el ReportService de una.
 * Se descarto porque dos fuentes de verdad para el mismo dato divergen en
 * silencio, y el que diverga no se sabe cual es el correcto. El ReportService
 * se actualizo a inventories en el mismo PR.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Red de seguridad: si alguien corrio el backfill a mano pero la
        // tabla no tiene los datos, esta migracion eliminaria el unico
        // respaldo del stock.
        $existeInventories = Schema::hasTable('inventories');

        if (!$existeInventories) {
            throw new RuntimeException(
                'No existe la tabla inventories. Ejecuta primero la migracion de backfill.'
            );
        }

        $stockAntes = (int) DB::table('lots')->sum('stock');
        $stockInventories = (int) DB::table('inventories')->sum('quantity');

        if ($stockAntes !== $stockInventories) {
            throw new RuntimeException(sprintf(
                'No se puede eliminar lots.stock: lots suma %d pero inventories suma %d. '
                    . 'El backfill esta incompleto.',
                $stockAntes,
                $stockInventories
            ));
        }

        DB::statement('ALTER TABLE lots DROP CONSTRAINT chk_lots_stock_non_negative');

        // SQL Server crea un DEFAULT constraint autogenerado por cada
        // ->default() de la migration original (DF__lots__stock__...). Una
        // columna con un DEFAULT colgado no se puede tirar: el motor responde
        // "The object 'DF_...' is dependent on column 'stock'". El nombre
        // lleva un hash que cambia segun el motor, asi que se busca en
        // sys.default_constraints en vez de hardcodearlo.
        $defaults = DB::select(
            "SELECT name FROM sys.default_constraints WHERE parent_object_id = OBJECT_ID('lots') AND parent_column_id = COLUMNPROPERTY(OBJECT_ID('lots'), 'stock', 'ColumnId')"
        );

        foreach ($defaults as $default) {
            DB::statement("ALTER TABLE lots DROP CONSTRAINT {$default->name}");
        }

        DB::statement('ALTER TABLE lots DROP COLUMN stock');
    }

    public function down(): void
    {
        // Irreversible: el stock se movio a inventories y no hay manera de
        // reconstruir el valor original de cada fila sin un backup.
        // Si alguien necesita revertir, debe restaurar desde un backup de la DB.
    }
};