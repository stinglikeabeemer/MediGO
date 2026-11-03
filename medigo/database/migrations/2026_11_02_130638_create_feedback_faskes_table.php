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
        Schema::create('feedback_faskes', function (Blueprint $table) {
            $table->id('id_feedback_faskes');
            $table->unsignedBigInteger('id_faskes');
            $table->unsignedBigInteger('id_user')->nullable();

            $table->string('nama_pengguna', 150);
            $table->string('email_pengguna', 150)->nullable();
            $table->string('kategori', 100);
            $table->text('catatan');
            $table->text('saran_koreksi')->nullable();

            $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
            $table->text('catatan_admin')->nullable();

            $table->timestamps();

            $table->foreign('id_faskes')->references('id_faskes')->on('faskes')->onDelete('cascade');
            $table->foreign('id_user')->references('id_user')->on('user')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_faskes');
    }
};