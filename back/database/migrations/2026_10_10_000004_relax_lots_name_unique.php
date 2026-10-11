<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Relaja la unicidad global del nombre de lote.
 *
 * Antes: lots.name era UNIQUE a secas, o sea que un lote era unico en todo el
 * sistema. Eso implica que un lote pertenece a una sola bodega, porque no
 * puede haber dos filas con el mismo nombre.
 *
 * Eso contradice RN-01 y bloquea los traslados. Para mover un lote de la
 * Farmacia Central a la de Urgencias hacen falta dos filas de inventory con el
 * mismo lot_id y distinta bodega, lo cual es legitimo. Pero si el nombre del
 * lote fuera unico global, el lote mismo no podria registrarse dos veces y el
 * traslado quedaria sin representacion posible.
 *
 * El nombre del lote es un atributo del producto (el lote que imprimio el
 * fabricante), no un lugar. Por eso la unicidad correcta es por producto:
 * dos productos distintos pueden tener legítimamente un lote "L-001".
 *
 * El UNIQUE (product_id, name) se agrega DESPUES de tirar el global para no
 * tener las dos restricciones activas a la vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Laravel crea $table->string('name')->unique() como un indice unico
        // (NONCLUSTERED), no como un constraint. En SQL Server un indice se
        // tira con DROP INDEX y un constraint con DROP CONSTRAINT: usar el
        // comando equivocado falla con "'lots_name_unique' is not a
        // constraint". Por eso el nombre se busca en sys.indexes en vez de
        // hardcodearlo.
        $existe = DB::selectOne(
            "SELECT name FROM sys.indexes WHERE object_id = OBJECT_ID('lots') AND is_unique = 1 AND name = 'lots_name_unique'"
        );

        if ($existe !== null) {
            DB::statement('DROP INDEX lots_name_unique ON lots');
        }

        DB::statement('ALTER TABLE lots ADD CONSTRAINT uq_lots_product_name UNIQUE (product_id, name)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE lots DROP CONSTRAINT uq_lots_product_name');

        // Volver al UNIQUE global puede fallar si meanwhile dos productos
        // distintos compartieron nombre de lote. Es la consecuencia de perder
        // la restriccion, y es preferible que el down falle explicitamente a
        // que se borren filas silenciosamente.
        DB::statement('CREATE UNIQUE INDEX lots_name_unique ON lots (name)');
    }
};