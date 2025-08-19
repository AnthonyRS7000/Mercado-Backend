<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedTinyInteger('estado');
            $table->string('direccion_entrega', 255);
            $table->decimal('total', 10, 2)->default(0);

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('metodo_pago_id')->constrained()->onDelete('cascade');
            $table->foreignId('delivery_id')->nullable()->constrained('deliveries')->onDelete('set null');
            $table->foreignId('personal_sistema_id')->nullable()->constrained('personal_sistemas')->onDelete('set null');

            $table->date('fecha_programada')->nullable();
            $table->time('hora_programada')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
