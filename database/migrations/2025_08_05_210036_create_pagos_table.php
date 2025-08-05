<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePagosTable extends Migration
{
    public function up()
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_id'); // Relación con el pedido
            $table->unsignedBigInteger('user_id');   // Por si lo necesitas para consultas rápidas
            $table->decimal('monto', 10, 2);
            $table->tinyInteger('metodo_pago')->default(2); // 2 = Mercado Pago

            // Campos de Mercado Pago
            $table->string('mp_payment_id')->nullable();
            $table->string('mp_preference_id')->nullable();
            $table->string('mp_status')->nullable();
            $table->string('mp_status_detail')->nullable();
            $table->string('mp_payment_type_id')->nullable();
            $table->integer('mp_installments')->nullable();
            $table->string('mp_card_issuer_id')->nullable();
            $table->string('mp_card_id')->nullable();
            $table->json('mp_raw_response')->nullable();

            $table->timestamps();

            // Relaciones
            $table->foreign('pedido_id')->references('id')->on('pedidos')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pagos');
    }
}
