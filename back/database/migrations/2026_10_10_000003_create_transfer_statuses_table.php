<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Crea el catalogo de estados de un traslado (RN-07, decision D3).
 *
 * Catalogo de datos y no enum en codigo (decision D3): agregar un estado, o
 * cambiar que estados se pueden anular, debe ser un INSERT o un UPDATE y no
 * un deploy. Ademas el grammar de sqlsrv no implementa typeEnum.
 *
 * CONVENCION DE NOMBRES (AI_USAGE.md 4.1): las referencias tecnicas van en
 * ingles. name es un identificador tecnico, asi que va en ingles; label es
 * texto que ve una persona en la pantalla de traslados, asi que va en espanol.
 * Por eso la tabla se llama transfer_statuses y no estados_traslado, y por eso
 * el estado es IN_TRANSITO y no EN_TRANSITO.
 *
 * Los estados se siembran aca y no en un seeder a proposito: una base
 * reconstruida con migrate los tiene disponibles sin que nadie tenga que
 * acordarse de correr db:seed, y sin ellos Transfer.status_id no tendria a que
 * apuntar.
 *
 * NO hay constantes en el modelo. Los estados se consultan por name contra esta
 * tabla cuando hace falta, no desde constantes en PHP: dos listas del mismo
 * catalogo divergen en silencio, y el estado que existe en el codigo pero no
 * en la base rompe el traslado en runtime con un error de FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Identificador tecnico, en ingles (AI_USAGE.md 4.1).
            $table->string('name', 30)->unique();
            // Texto de interfaz, en espanol.
            $table->string('label', 60);

            // La maquina de estados consulta estos flags en vez de codificarlos
            // en un switch: si vivieran en codigo habria dos fuentes de verdad
            // y divergirian sin avisar.
            $table->boolean('is_cancellable')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
        });

        $ahora = now();
        $estados = [
            ['name' => 'DRAFT', 'label' => 'Borrador', 'is_cancellable' => true, 'is_terminal' => false],
            ['name' => 'REQUESTED', 'label' => 'Solicitado', 'is_cancellable' => true, 'is_terminal' => false],
            ['name' => 'APPROVED', 'label' => 'Aprobado', 'is_cancellable' => false, 'is_terminal' => false],
            ['name' => 'IN_TRANSIT', 'label' => 'En transito', 'is_cancellable' => false, 'is_terminal' => false],
            ['name' => 'RECEIVED', 'label' => 'Recibido', 'is_cancellable' => false, 'is_terminal' => false],
            ['name' => 'PARTIALLY_RECEIVED', 'label' => 'Recibido parcial', 'is_cancellable' => false, 'is_terminal' => false],
            ['name' => 'CANCELLED', 'label' => 'Anulado', 'is_cancellable' => false, 'is_terminal' => true],
        ];

        foreach ($estados as $estado) {
            Schema::getConnection()->table('transfer_statuses')->insert([
                'id' => (string) Str::uuid(),
                'name' => $estado['name'],
                'label' => $estado['label'],
                'is_cancellable' => $estado['is_cancellable'],
                'is_terminal' => $estado['is_terminal'],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_statuses');
    }
};