<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSolicitudRegistrosTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
            Schema::create('solicitud_registros', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Campos generales
            $table->string('nombre')->nullable();
            $table->string('dni')->nullable();
            $table->string('celular')->nullable();
            $table->string('direccion')->nullable();
            $table->string('preferencias_compra')->nullable(); // Solo clientes
            $table->string('nombre_empresa')->nullable();       // Proveedor y delivery

            $table->string('email')->nullable();
            $table->string('password')->nullable(); // Puede ir hasheada o solo para validación inicial

            $table->unsignedBigInteger('user_id')->nullable();

            $table->enum('tipo', ['cliente', 'proveedor', 'delivery', 'personal_sistema']);
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');

            // NUEVO: Categorías (solo para proveedor)
            $table->json('ids')->nullable(); // Array de IDs de categorías

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_registros');
    }
}
