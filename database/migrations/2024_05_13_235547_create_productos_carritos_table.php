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

            $table->foreignId('carrito_id')
                ->constrained('carritos')
                ->onDelete('cascade');

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->onDelete('cascade');

            $table->decimal('cantidad', 10, 3);
            $table->timestamp('fecha_agrego')->useCurrent();
            $table->decimal('total', 10, 2);

            // <- AQUÍ el cambio: crear la columna directamente, sin change()
            $table->tinyInteger('estado')->default(1);

            $table->timestamps();

            // Evita duplicados del mismo producto en el mismo carrito
            $table->unique(['carrito_id', 'producto_id']);
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
