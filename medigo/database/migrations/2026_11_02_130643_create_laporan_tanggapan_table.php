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
        Schema::create('laporan_tanggapan', function (Blueprint $table) {
            $table->id('id_laporan_tanggapan');
            $table->unsignedBigInteger('id_tanggapan');
            $table->unsignedBigInteger('id_user');
            $table->text('alasan_laporan');

            $table->foreign('id_tanggapan')->references('id_tanggapan')->on('tanggapan_diskusi')->onDelete('cascade');
            $table->foreign('id_user')->references('id_user')->on('user')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_tanggapan');
    }
};
