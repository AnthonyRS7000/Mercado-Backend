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
    Schema::table('clientes', function (Blueprint $table) {
        $table->string('nombre')->nullable()->change();
        $table->string('dni')->nullable()->change();
        $table->string('celular')->nullable()->change();
        $table->string('direccion')->nullable()->change();
        $table->string('preferencias_compra')->nullable()->change();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
