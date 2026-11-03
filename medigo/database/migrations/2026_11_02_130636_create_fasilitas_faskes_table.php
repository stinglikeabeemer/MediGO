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
        Schema::create('fasilitas_faskes', function (Blueprint $table) {
            $table->id('id_fasilitas_faskes');

            // Definisikan kolom Foreign Key dengan tipe unsignedBigInteger
            $table->unsignedBigInteger('id_faskes');
            $table->unsignedBigInteger('id_fasilitas');

            // Hubungkan Foreign Key ke tabel asalnya
            $table->foreign('id_faskes')->references('id_faskes')->on('faskes')->onDelete('cascade');
            $table->foreign('id_fasilitas')->references('id_fasilitas')->on('fasilitas')->onDelete('cascade');
            // Hapus timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fasilitas_faskes');
    }
};
