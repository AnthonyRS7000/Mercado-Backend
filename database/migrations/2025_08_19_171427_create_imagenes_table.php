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
        Schema::create('imagenes', function (Blueprint $table) {
            $table->id('id_imagen');
            $table->string('nombre');
            $table->string('url');                // ruta o URL de la imagen
            $table->string('descripcion')->nullable();
            $table->timestamp('fecha_subida')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imagenes');
    }
};
