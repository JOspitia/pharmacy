<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea el Kardex: registro inmutable de todo cambio de existencias.
 *
 * RN-06: "Los movimientos son inmutables: nunca se editan ni se borran. Las
 * correcciones se hacen con movimientos de ajuste y motivo."
 *
 * Esa inmutabilidad se defiende en tres capas, y las tres importan:
 *
 * 1. No hay updated_at. No hay marca de que algo cambio. Laravel no puede
 *    actualizar una fila que no tiene donde dejar la hora.
 * 2. No hay deleted_at ni SoftDeletes. Borrar un movimiento es fisicamente
 *    imposible desde el ORM.
 * 3. Sin triggers en la base. Esta capa es la que evita que un UPDATE
 *    escrito a mano desde el sqlcmd del contenedor saltee las dos anteriores.
 *    Un trigger que lance excepcion ante UPDATE y DELETE es lo unico que
 *    protege contra el acceso directo a la base.
 *
 * quantity es el delta aplicado a la existencia: positivo suma, negativo
 * resta. Guardar el signo en la propia fila hace que el Kardex se pueda
 * releer y auditar sin volver a consultar el estado actual del inventario,
 * que para cuando se consulta ya habra cambiado.
 *
 * reference_type/reference_id permiten rastrear que documento origino el
 * movimiento (un Transfer, un Dispense) sin crear dependencia circular entre
 * las tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inventory_id');
            $table->uuid('movement_type_id');
            $table->integer('quantity');
            $table->string('reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            // users.id es bigint (schema estandar de Laravel), no uuid. Por eso
            // se usa foreignId y no uuid: una FK exige que ambos lados tengan
            // el mismo tipo, y declarar uuid aca hace fallar la migracion con
            // "Column 'users.id' is not the same data type as referencing
            // column". Las tablas de dominio usan uuid, pero users no.
            $table->foreignId('performed_by')->constrained('users')->onDelete('no action');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('no action');
            $table->foreign('movement_type_id')->references('id')->on('movement_types')->onDelete('no action');

            // El kardex se filtra por rango de fechas y por tipo, que es como
            // lo lee una auditoria ("que movements hubo en marzo").
            $table->index(['movement_type_id', 'created_at']);
            $table->index('inventory_id');
            $table->index(['reference_type', 'reference_id']);
        });

        // Capa 3: la base se niega a modificar o borrar su propio historico.
        // El TRIGGER va primero: si la tabla ya tiene movimientos, el CHECK se
        // evaluaria sobre el estado actual en lugar de sobre la fila que se
        // intenta escribir.
        DB::statement(
            "CREATE TRIGGER trg_movements_inmutable ON movements
             INSTEAD OF UPDATE, DELETE
             AS
             BEGIN
                 RAISERROR('RN-06: los movimientos del kardex son inmutables; se corrige con un movimiento de ajuste y motivo', 16, 1);
                 ROLLBACK TRANSACTION;
             END"
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_movements_inmutable');

        Schema::dropIfExists('movements');
    }
};