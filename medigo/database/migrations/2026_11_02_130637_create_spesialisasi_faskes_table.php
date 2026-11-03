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
        Schema::create('spesialisasi_faskes', function (Blueprint $table) {
            $table->id('id_spesialisasi_faskes');

            $table->unsignedBigInteger('id_faskes');
            $table->unsignedBigInteger('id_spesialisasi');

            $table->foreign('id_faskes')->references('id_faskes')->on('faskes')->onDelete('cascade');
            $table->foreign('id_spesialisasi')->references('id_spesialisasi')->on('spesialisasi')->onDelete('cascade');
            // Hapus timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spesialisasi_faskes');
    }
};
