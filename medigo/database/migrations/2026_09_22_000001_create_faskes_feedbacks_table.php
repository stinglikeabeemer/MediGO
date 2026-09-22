<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('faskes_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('faskes_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('category', 100); // lokasi_salah, bpjs_tidak_sesuai, kontak_keliru, lainnya
            $table->text('notes');
            $table->text('suggested_correction')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'applied', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->foreign('faskes_id')->references('id')->on('faskes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faskes_feedbacks');
    }
};
