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
        Schema::create('productos_carritos', function (Blueprint $table) {
            $table->id();
            $table->decimal('cantidad', 8, 2);
            $table->date('fecha_agrego');
            $table->decimal('total', 8, 2);
            $table->integer('estado');
            $table->foreignId('cliente_id')->constrained()->onDelete('cascade');
            $table->foreignId('producto_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos_carritos');
    }
};
