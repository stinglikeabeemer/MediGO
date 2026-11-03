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
        Schema::create('spesialisasi', function (Blueprint $table) {
            $table->id('id_spesialisasi');
            $table->string('nama_spesialisasi', 100);
            // Hapus timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spesialisasi');
    }
};
