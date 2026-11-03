<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user', function (Blueprint $table) {
            $table->id('id_user');
            $table->string('nama_user', 150);
            $table->string('email_user', 150)->unique();
            $table->string('password_user');
            $table->string('role_user', 50)->default('user');
            // Menghapus $table->timestamps(); karena lu tidak memakainya
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user');
    }
};
