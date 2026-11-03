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
        Schema::create('diskusi', function (Blueprint $table) {
            $table->id('id_diskusi');
            $table->unsignedBigInteger('id_user');

            $table->string('judul_diskusi', 255);
            $table->text('isi_diskusi');
            $table->string('kategori')->default('Umum');
            $table->string('gambar_diskusi')->nullable();
            $table->json('images')->nullable();
            $table->string('status_diskusi')->default('active');

            $table->timestamps();

            $table->foreign('id_user')->references('id_user')->on('user')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diskusi');
    }
};