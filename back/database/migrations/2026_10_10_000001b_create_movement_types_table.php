<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Crea el catalogo de tipos de movimiento del Kardex (RN-06).
 *
 * Catalogo de datos y no enum en codigo (decision D3): agregar un tipo debe ser
 * un INSERT y no un deploy. El grammar de sqlsrv tampoco implementa typeEnum.
 *
 * RN-06 nombra cinco: entrada, salida por dispensacion, salida por traslado,
 * entrada por traslado y ajuste. Se agrega WITHDRAWAL para que la retirada de
 * mercaderia por merma, rotura o vencimiento sea un tipo propio y no un ajuste
 * con signo menos: un numero negativo en la columna quantity es ambiguo para
 * quien audita el kardex.
 *
 * CONVENCION DE NOMBRES (AI_USAGE.md 4.1): name y direction son identificadores
 * tecnicos y van en ingles; description es texto que ve una persona y va en
 * espanol.
 *
 * direction define el signo que KardexService aplica a quantity:
 *   IN      siempre suma
 *   OUT     siempre resta
 *   IN_OUT  suma o resta segun el signo enviado
 *
 * Los tipos se siembran aca y no en un seeder a proposito: una base
 * reconstruida con migrate los tiene disponibles sin correr db:seed.
 *
 * NO hay constantes en MovementType: el catalogo vive unicamente en esta
 * tabla, para no mantener dos listas que puedan divergir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 30)->unique();
            $table->string('description', 120);
            $table->string('direction', 6);
            $table->timestamps();
        });

        $ahora = now();
        $tipos = [
            ['name' => 'ENTRY', 'description' => 'Ingreso de mercancia', 'direction' => 'IN'],
            ['name' => 'ADJUSTMENT', 'description' => 'Correccion de inventario', 'direction' => 'IN_OUT'],
            ['name' => 'TRANSFER_OUT', 'description' => 'Salida por traslado a otra bodega', 'direction' => 'OUT'],
            ['name' => 'TRANSFER_IN', 'description' => 'Entrada por traslado desde otra bodega', 'direction' => 'IN'],
            ['name' => 'DISPENSATION', 'description' => 'Salida por dispensacion a paciente', 'direction' => 'OUT'],
            ['name' => 'WITHDRAWAL', 'description' => 'Retiro de mercaderia por merma o rotura', 'direction' => 'OUT'],
        ];

        foreach ($tipos as $tipo) {
            Schema::getConnection()->table('movement_types')->insert([
                'id' => (string) Str::uuid(),
                'name' => $tipo['name'],
                'description' => $tipo['description'],
                'direction' => $tipo['direction'],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_types');
    }
};