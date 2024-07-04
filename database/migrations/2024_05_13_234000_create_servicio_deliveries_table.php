<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('servicio_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_servicio'); 
            $table->string('descripcion'); 
            $table->decimal('precio', 8, 2);
            $table->foreignId('deliverie_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servicio_deliveries');
    }
};
