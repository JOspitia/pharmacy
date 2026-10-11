<?php

use App\Models\Lot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Mueve el stock de lots.stock a inventories.
 *
 * Esta es la migracion de datos de la HU-08. Es la unica irreversible en el
 * sentido de que su down() no puede restaurar el dato: si se tira la tabla
 * inventories, el stock historico se pierde. Por eso el down() se niega a
 * correr en vez de fingir que sabe reconstruirlo.
 *
 * La verificacion de suma al final no es decorativa. Si el INSERT falla a
 * medias, o si habia lotes con stock que no se copiaron, la columna stock se
 * va a tirar en la migracion siguiente y el dato desaparece sin que nadie se
 * entere. Comparar SUM antes de continuar convierte un error silencioso en un
 * fallo ruidoso y temprano.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stockAntes = (int) DB::table('lots')->sum('stock');

        // Se copian TODOS los lotes, incluso los soft-deleted. Un lote borrado
        // con stock deberia tener stock 0 (LotService impide borrar con stock
        // activo), pero si algo quedo a medias, copiarlo mantiene la suma
        // cuadrada y no pierde informacion. La limpieza es de los datos, no
        // del esquema.
        Lot::withTrashed()
            ->orderBy('id')
            ->chunk(200, function ($lotes) {
                $filas = [];
                $ahora = now();

                foreach ($lotes as $lote) {
                    $filas[] = [
                        'id' => (string) Str::uuid(),
                        'warehouse_id' => $lote->warehouse_id,
                        'product_id' => $lote->product_id,
                        'lot_id' => $lote->id,
                        'quantity' => (int) $lote->stock,
                        'min_stock' => null,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                if ($filas !== []) {
                    DB::table('inventories')->insert($filas);
                }
            });

        $stockDespues = (int) DB::table('inventories')->sum('quantity');

        if ($stockAntes !== $stockDespues) {
            throw new RuntimeException(sprintf(
                'Backfill de inventories descuadrado: lots.stock suma %d pero inventories suma %d. '
                    . 'Se aborta la migracion antes de eliminar lots.stock para no perder el dato.',
                $stockAntes,
                $stockDespues
            ));
        }

        // Inventarios duplicados sobre la misma terna no deberian existir (la
        // constraint uq_inventories_existencia ya lo impide), pero si la tabla
        // traia datos de una corrida anterior es mejor saberlo.
        //
        // ElHaving va con COUNT(*) > 1 y no con un alias: SQL Server no
        // permite referenciar el alias de la columna agregada dentro del
        // HAVING de la misma consulta.
        $duplicados = DB::table('inventories')
            ->select('warehouse_id', 'product_id', 'lot_id')
            ->groupBy('warehouse_id', 'product_id', 'lot_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicados > 0) {
            throw new RuntimeException(
                "Backfill genero {$duplicados} ternas duplicadas en inventories, lo que viola RN-01."
            );
        }
    }

    public function down(): void
    {
        // El stock original ya no existe en ningun lado: se copio a inventories
        // y la columna se elimino. Reconstruirlo seria inventar el valor de
        // partida de cada lote, que no se guardo en ningun sitio.
        throw new RuntimeException(
            'Esta migracion no se puede revertir. Para volver atras habria que restaurar '
                . 'la tabla lots con su columna stock desde un backup previo a HU-08.'
        );
    }
};