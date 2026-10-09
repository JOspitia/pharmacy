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
    public function up()
    {
        // Crear tabla unidades
        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50);
            $table->string('abbreviation', 10);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Crear tabla productos
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();            
            $table->string('name', 100);
            $table->string('cum', 30)->unique();
            $table->string('barcode', 60)->unique()->nullable();
            $table->string('invima_registration', 60)->unique();
            $table->foreignUuid('group_id')->constrained('groups')->onDelete('no action');
            $table->foreignUuid('unit_id')->constrained('units')->onDelete('no action');
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('no action');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');            
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index('cum');
            $table->index('deleted_at');
        });        
    }
    

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
    }
};
