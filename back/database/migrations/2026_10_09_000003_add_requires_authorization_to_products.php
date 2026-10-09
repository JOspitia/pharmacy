<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * RN-05: los medicamentos de control especial requieren autorizacion
     * de un segundo usuario con rol regente_farmacia antes de dispensarse.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('requires_authorization')
                ->default(false)
                ->after('status')
                ->comment('Si es true, la dispensacion necesita autorizacion de un regente.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('requires_authorization');
        });
    }
};
