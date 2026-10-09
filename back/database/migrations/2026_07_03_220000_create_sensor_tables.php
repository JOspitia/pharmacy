<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up(): void {

        // Crear tabla sensores
        Schema::create('sensors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 60);
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->uuid('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('bodegas')->onDelete('no action');
            $table->foreignId('created_by')->constrained('users')->onDelete('no action');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index('name');
            $table->index('deleted_at');
            $table->index('warehouse_id');
        });

        // Crear tabla de lecturas
        Schema::create('readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sensor_id');            
            $table->decimal('temperature', 5, 2);
            $table->decimal('humidity', 5, 2);
            $table->timestamp('recorded_at');            
            $table->foreign('sensor_id')->references('id')->on('sensors')->onDelete('cascade');
            $table->index('sensor_id');
            $table->index('recorded_at');
        });

        // Crear tabla de alertas
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();            
            $table->uuid('reading_id');            
            $table->string('type', 20);
            $table->text('message');
            $table->timestamp('created_at')->nullable();            
            $table->foreign('reading_id')->references('id')->on('readings')->onDelete('cascade');
            $table->index('reading_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('readings');
        Schema::dropIfExists('sensors');
    }
};
