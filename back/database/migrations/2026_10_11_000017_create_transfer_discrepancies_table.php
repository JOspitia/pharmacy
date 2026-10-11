<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `transfer_discrepancies` (HU-09 §2).
 *
 * RN-07: "Lo no recibido queda como discrepancia pendiente de
 * resolucion". La tabla materializa esa obligacion de forma
 * consultable: cada vez que se ejecuta receive() con delivered <
 * approved, se persiste una fila aqui con la diferencia y un
 * status PENDING. El traslado pasa a RECIBIDO_PARCIAL si hay al
 * menos una discrepancia abierta; a RECIBIDO si no.
 *
 * Resolver una discrepancia es una operacion separada y NO devuelve
 * stock automaticamente: la correccion stock se hace por ajuste en
 * Kardex (RN-06). Esta tabla solo registra QUE hay una diferencia,
 * no la corrige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_discrepancies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transfer_id');
            $table->uuid('transfer_item_id');
            $table->integer('approved_quantity');
            $table->integer('delivered_quantity');
            $table->integer('difference'); // CHECK >= 0 lo agrega la BD

            // PENDING, RESOLVED, WRITTEN_OFF. La HU no exige un catalogo
            // de datos; son tres valores fijos que viven en codigo.
            $table->string('status', 20)->default('PENDING');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('no action');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('transfer_id')
                ->references('id')->on('transfers')
                ->onDelete('cascade');
            // transfer_item_id va con no action: SQL Server rechaza
            // CASCADE porque ya hay un CASCADE de transfer_id hacia
            // transfer_items, y eso generaria un ciclo de cascadas.
            // Para la operacion del HU-09 (borrar un traslado borra
            // items por CASCADE; los discrepancies quedan como
            // huerfanos pero no se borran), es comportamiento aceptable.
            // Si en el futuro se necesita, hay que cambiar a NO ACTION
            // o romper la cadena con un trigger.
            $table->foreign('transfer_item_id')
                ->references('id')->on('transfer_items')
                ->onDelete('no action');

            $table->index(['transfer_id', 'status']);
        });

        DB::statement(
            'ALTER TABLE transfer_discrepancies ADD CONSTRAINT chk_discrepancy_diff_non_negative
             CHECK (difference >= 0)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE transfer_discrepancies DROP CONSTRAINT IF EXISTS chk_discrepancy_diff_non_negative');
        Schema::dropIfExists('transfer_discrepancies');
    }
};
