<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de transfer_number para los traslados que ya existen (en
 * caso de que se haya cargado data de demo antes de la migracion
 * 000014) y vuelve la columna NOT NULL.
 *
 * El formato TRF-YYYYMMDD-XXXX se genera con la misma logica que
 * Transfer::generarNumero(): secuencial diario, con padding a 4
 * digitos.
 *
 * Si no hay filas existentes, simplemente se vuelve NOT NULL sin
 * backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('transfers')
            ->whereNull('transfer_number')
            ->orderBy('created_at')
            ->get(['id', 'created_at']);

        if ($rows->isEmpty()) {
            // No hay nada que backfillear.
        } else {
            // Agrupar por dia para llevar la secuencia por dia.
            $byDay = [];
            foreach ($rows as $row) {
                $day = substr((string) $row->created_at, 0, 10); // YYYY-MM-DD
                $dayYmd = str_replace('-', '', $day);
                $byDay[$dayYmd][] = $row->id;
            }

            foreach ($byDay as $dayYmd => $ids) {
                $seq = 1;
                foreach ($ids as $id) {
                    $number = 'TRF-' . $dayYmd . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
                    DB::table('transfers')->where('id', $id)->update(['transfer_number' => $number]);
                    $seq++;
                }
            }
        }

        // Volver NOT NULL: en SQL Server hay que dropear el UNIQUE
        // primero porque depende de la columna, alterar, y re-crear.
        // El metodo change() de Blueprint requiere doctrine/dbal que
        // rompe contra Laravel 10, asi que va con SQL crudo.
        DB::statement('DROP INDEX uq_transfers_transfer_number ON transfers');
        DB::statement('ALTER TABLE transfers ALTER COLUMN transfer_number NVARCHAR(20) NOT NULL');
        DB::statement('CREATE UNIQUE INDEX uq_transfers_transfer_number ON transfers (transfer_number)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX uq_transfers_transfer_number ON transfers');
        DB::statement('ALTER TABLE transfers ALTER COLUMN transfer_number NVARCHAR(20) NULL');
        DB::statement('CREATE UNIQUE INDEX uq_transfers_transfer_number ON transfers (transfer_number) WHERE transfer_number IS NOT NULL');
    }
};
