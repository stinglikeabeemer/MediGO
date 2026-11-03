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
        Schema::create('tanggapan_diskusi', function (Blueprint $table) {
            $table->id('id_tanggapan');
            $table->unsignedBigInteger('id_diskusi');
            $table->unsignedBigInteger('id_user');
            $table->text('isi_tanggapan');
            $table->unsignedBigInteger('id_parent')->nullable(); // Untuk reply comment
            $table->string('gambar_tanggapan')->nullable(); // Untuk upload gambar

            $table->foreign('id_parent')->references('id_tanggapan')->on('tanggapan_diskusi')->onDelete('cascade');
            $table->foreign('id_diskusi')->references('id_diskusi')->on('diskusi')->onDelete('cascade');
            $table->foreign('id_user')->references('id_user')->on('user')->onDelete('cascade');
            // Hapus timestamps()
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tanggapan_diskusi');
    }
};
