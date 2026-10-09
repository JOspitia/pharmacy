<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void {

        //Crear tabla lots con sus respectivas restricciones
        Schema::create('lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
            $table->uuid('product_id');
            $table->uuid('warehouse_id');
            $table->integer('stock')->default(0);
            $table->date('expiration_date');
            $table->text('description')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('no action');
            $table->foreign('warehouse_id')->references('id')->on('bodegas')->onDelete('no action');
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('no action');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');       
            $table->timestamps();
            $table->softDeletes();            
            $table->index('product_id');
            $table->index('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('lots');
    }
};
