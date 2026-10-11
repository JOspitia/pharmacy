<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla padre del modulo de traslados (HU-09, RN-07).
 *
 * Un traslado es un movimiento planificado de N existencias desde una bodega
 * origen a una bodega destino, con una maquina de 7 estados. La fila padre
 * registra el documento; los items concretos viven en `transfer_items`
 * (creada en la migracion 000008).
 *
 * Decisiones de modelado:
 *
 *   - Una columna por actor (requested_by, approved_by, dispatched_by,
 *     received_by, cancelled_by). Cada transicion registra QUIEN la hizo y
 *     CUANDO (dispatched_at, received_at, cancelled_at), con FK a users. Esto
 *     le da a la auditoria la respuesta directa sin JOIN a una tabla de
 *     eventos.
 *
 *   - status_id apunta a `transfer_statuses` (catalogo sembrado en la
 *     migracion 000003). Los nombres tecnicos estan en ingles (DRAFT,
 *     REQUESTED, APPROVED, etc.) y el label visible al usuario en espanol.
 *     Es la convencion de AI_USAGE 4.1: nombre tecnico en ingles, label
 *     de UI en espanol.
 *
 *   - warehouses son FK con onDelete no action: no se puede eliminar una
 *     bodega que tenga traslados, ni siquiera cancelados. La bodega es
 *     informacion historica que la farmacia puede necesitar para una
 *     auditoria de 5 anos. En SQL Server "no action" es el default para
 *     FKs y es equivalente a "RESTRICT" en MySQL/PostgreSQL; el
 *     grammar de SQL Server rechaza la palabra "RESTRICT" en la
 *     declaracion de la FK.
 *
 *   - CHECK constraint (warehouse_origin_id != warehouse_destiny_id): un
 *     traslado de la bodega A a la misma bodega A no tiene sentido y
 *     ademas romperia la contabilizacion del Kardex (entrarian y saldrian
 *     cantidades iguales sin efecto neto, pero harian ruido en el
 *     historial). El backend igual valida esto en TransferRequest para
 *     devolver 422 con un mensaje claro.
 *
 *   - performed_by (que es users.id, bigint) justifica usar foreignId en
 *     vez de uuid: una FK exige tipos identicos en ambos lados. La
 *     misma justificacion se aplica en `movements` (HU-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('warehouse_origin_id');
            $table->uuid('warehouse_destiny_id');
            $table->uuid('status_id');

            // Los cinco actores. Todos nullable porque cada uno se llena en
            // una transicion distinta de la maquina de estados. requested_by
            // SIEMPRE se llena al crear el traslado; el resto depende del
            // avance del flujo.
            $table->foreignId('requested_by')->constrained('users')->onDelete('no action');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('no action');
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->onDelete('no action');
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('no action');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('no action');

            // Timestamps de transicion. Se llenan al pasar por la accion
            // correspondiente (no se llenan solos). created_at/updated_at
            // se manejan con timestamps() de Eloquent abajo.
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('warehouse_origin_id')
                ->references('id')->on('bodegas')
                ->onDelete('no action');
            $table->foreign('warehouse_destiny_id')
                ->references('id')->on('bodegas')
                ->onDelete('no action');
            $table->foreign('status_id')
                ->references('id')->on('transfer_statuses')
                ->onDelete('no action');

            // Indice principal de los listados: por estado, los mas recientes
            // primero. Coincide con el orden por defecto de /api/transfers.
            $table->index(['status_id', 'created_at']);

            // Busquedas por bodega: "todos los traslados donde participa
            // la bodega X (sea origen o destino)". La consulta usa OR
            // sobre estas dos columnas; el indice compuesto cubre la
            // mitad izquierda de la condicion.
            $table->index('warehouse_origin_id');
            $table->index('warehouse_destiny_id');
        });

        // CHECK constraint a nivel de SQL Server. Laravel no tiene un
        // helper directo para CHECKs en SQL Server (el metodo Blueprint
        // correspondiente es de MySQL/PostgreSQL), asi que va con SQL
        // crudo. Si el origen y el destino coinciden la insercion falla
        // antes de pasar al TransferRequest del backend.
        \DB::statement(
            'ALTER TABLE transfers ADD CONSTRAINT chk_transfers_origin_ne_destiny
             CHECK (warehouse_origin_id <> warehouse_destiny_id)'
        );
    }

    public function down(): void
    {
        \DB::statement('ALTER TABLE transfers DROP CONSTRAINT IF EXISTS chk_transfers_origin_ne_destiny');
        Schema::dropIfExists('transfers');
    }
};
