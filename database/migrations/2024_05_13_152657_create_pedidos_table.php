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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedTinyInteger('estado'); // estados pequeños (ej: 1=pending, 2=aceptado)
            $table->string('direccion_entrega', 255);
            $table->decimal('total', 10, 2)->default(0);

            // Relación con users (no con clientes)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Método de pago
            $table->foreignId('metodo_pago_id')->constrained()->onDelete('cascade');

            // Opcional: delivery asignado
            $table->foreignId('delivery_id')
                  ->nullable()
                  ->constrained('deliveries')
                  ->onDelete('set null');

            // Opcional: personal del sistema asignado
            $table->foreignId('personal_sistema_id')
                  ->nullable()
                  ->constrained('personal_sistemas')
                  ->onDelete('set null');

            // Campos de programación
            $table->date('fecha_programada')->nullable();
            $table->time('hora_programada')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
