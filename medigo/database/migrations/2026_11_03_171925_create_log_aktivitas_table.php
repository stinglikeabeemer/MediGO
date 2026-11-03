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
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id('id_log');
            $table->unsignedBigInteger('id_user');
            $table->string('tipe_aksi', 255);
            $table->text('deskripsi');
            $table->timestamps();

            $table->foreign('id_user')->references('id_user')->on('user')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
    }
};
