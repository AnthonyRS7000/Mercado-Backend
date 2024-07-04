<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_xx_xx_create_users_table.php
// database/migrations/xxxx_xx_xx_create_users_table.php
    public function up()
    {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('user');
        $table->string('email')->unique();
        $table->string('imagen');
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->foreignId('role_id')->constrained()->onDelete('cascade'); // No nullable
        $table->rememberToken();
        $table->timestamps();
    });
}



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
