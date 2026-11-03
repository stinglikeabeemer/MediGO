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
        Schema::create('laporan_komentar', function (Blueprint $table) {
            $table->id('id_laporan_komentar');
            $table->unsignedBigInteger('id_komentar');
            $table->unsignedBigInteger('id_user');
            $table->text('alasan_laporan');
            $table->text('catatan_tambahan')->nullable();
            $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
            $table->timestamps();

            $table->foreign('id_komentar')->references('id_komentar')->on('komentar')->onDelete('cascade');
            $table->foreign('id_user')->references('id_user')->on('user')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_komentar');
    }
};
