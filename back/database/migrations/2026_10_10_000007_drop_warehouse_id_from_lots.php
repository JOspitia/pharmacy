<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina lots.warehouse_id.
 *
 * Decision documentada junto al usuario: eliminar la columna, no dejarla
 * nullable.
 *
 * El lote es un atributo del producto (numero de lote del fabricante + fecha
 * de vencimiento), no un lugar. La bodega es dimension de la existencia, y
 * esa dimension vive en inventories. Dejar la columna nullable significaba
 * mantener un campo que puede ser NULL y que ademas duplica una verdad que
 * ya esta en otra tabla, con dos fuentes que pueden divergir.
 *
 * El usuario de la UI ya no elige bodega al crear un lote: elige bodega al
 * registrar la entrada en el Kardex, que es donde la existencia nace. Por eso
 * StoreLotRequest deja de exigir warehouse_id.
 *
 * Se elimina el indice y la FK antes de la columna, por la misma razon que en
 * la migracion de stock: SQL Server no permite quitar una columna referenciada
 * por una constraint activa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tieneInventories = Schema::hasTable('inventories');

        if (!$tieneInventories) {
            throw new RuntimeException(
                'No existe la tabla inventories. Sin ella, quitar warehouse_id dejaria los lotes '
                    . 'sin ubicacion y sin stock, es decir, inservibles.'
            );
        }

        // Cada lote debe conservar su stock en inventories. No se consulta
        // lots.stock porque la migracion anterior ya elimino esa columna:
        // referenciarla aqui daria "columna invalida" en vez del mensaje util.
        // Cada lote con stock debe tener una existencia que lo cubra.
        //
        // No se valida SUM(quantity) > 0 porque en una base recien creada
        // migrate todavia no hay lotes ni stock: inventories legitimately
        // tiene cero filas y la migracion debe poder seguir. Lo que no se
        // permite es que haya lotes con stock que no estén cubiertos por
        // ninguna existencia, que es el caso de datos perdidos.
        $lotesHuerfanos = DB::table('lots')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('inventories')
                    ->whereColumn('inventories.lot_id', 'lots.id');
            })
            ->count();

        if ($lotesHuerfanos > 0) {
            throw new RuntimeException(
                "Hay {$lotesHuerfanos} lotes sin existencia en inventories. "
                    . 'El backfill esta incompleto; no se puede quitar warehouse_id.'
            );
        }

        // Laravel nombra las FKs <tabla>_<columna>_foreign, no FK_<tabla>_<columna>.
        // Con el nombre equivocado el motor responde "'FK_lots_warehouse_id_foreign'
        // is not a constraint".
        DB::statement('ALTER TABLE lots DROP CONSTRAINT lots_warehouse_id_foreign');
        DB::statement('DROP INDEX lots_warehouse_id_index ON lots');
        DB::statement('ALTER TABLE lots DROP COLUMN warehouse_id');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE lots ADD warehouse_id UNIQUEIDENTIFIER NULL');

        // La columna vuelve nullable a proposito: un lote ya no pertenece a
        // una bodega. Cualquier fila existente queda sin bodega, que es el
        // estado correcto del dominio nuevo.
        DB::statement('ALTER TABLE lots ADD CONSTRAINT lots_warehouse_id_foreign FOREIGN KEY (warehouse_id) REFERENCES bodegas(id)');
        DB::statement('CREATE INDEX lots_warehouse_id_index ON lots (warehouse_id)');
    }
};