<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('flyer_productos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flyer_id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('precio_original', 10, 2);
            $table->timestamps();

            $table->foreign('flyer_id')->references('id')->on('flyers')->onDelete('cascade');
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flyer_productos');
    }
};
