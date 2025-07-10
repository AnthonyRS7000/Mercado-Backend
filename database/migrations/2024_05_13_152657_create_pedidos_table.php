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
            $table->integer('estado');
            $table->string('direccion_entrega');
            $table->decimal('total', 10, 2)->default(0);
            $table->foreignId('cliente_id')->constrained()->onDelete('cascade');
            $table->foreignId('metodo_pago_id')->constrained()->onDelete('cascade');
            $table->foreignId('delivery_id')->nullable()->constrained('deliveries');
            $table->foreignId('personal_sistema_id')->nullable()->constrained('personal_sistema');
            $table->date('fecha_entrega')->nullable();
            $table->time('hora_entrega')->nullable();
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
