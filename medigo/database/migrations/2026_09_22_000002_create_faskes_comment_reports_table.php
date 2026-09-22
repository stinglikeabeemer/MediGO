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
        Schema::create('faskes_comment_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comment_id');
            $table->unsignedBigInteger('faskes_id');
            $table->unsignedBigInteger('user_id');
            $table->string('reason', 150); // kata_kasar, spam, sara, hoax, lainnya
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
            $table->timestamps();

            $table->foreign('comment_id')->references('id')->on('faskes_comments')->onDelete('cascade');
            $table->foreign('faskes_id')->references('id')->on('faskes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faskes_comment_reports');
    }
};
