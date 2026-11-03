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
        Schema::create('laporan_diskusi', function (Blueprint $table) {
            $table->id('id_laporan_diskusi');
            $table->unsignedBigInteger('id_diskusi');
            $table->unsignedBigInteger('id_user'); // User yang ngelapor
            $table->text('alasan_laporan');

            $table->foreign('id_diskusi')->references('id_diskusi')->on('diskusi')->onDelete('cascade');
            $table->foreign('id_user')->references('id_user')->on('user')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_diskusi');
    }
};
