<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Catalogo de modulos del sistema. El nombre del modulo es la cadena que
     * las rutas pasan al middleware: Route::middleware('module:warehouses').
     *
     * Los nombres coinciden con los prefijos de ruta de routes/api.php para
     * que escribir el grupo de middleware y escribir la ruta sean la misma
     * decision y no haya que traducir entre uno y otro.
     *
     * No lleva softDeletes por la misma razon que roles: no hay CRUD de
     * modulos.
     */
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};