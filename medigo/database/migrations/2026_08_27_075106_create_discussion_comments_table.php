<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discussion_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->string('image_path')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable(); // Untuk nested replies
            $table->timestamps();

            // Foreign keys (opsional jika relasi database diaktifkan)
            $table->foreign('discussion_id')->references('id')->on('discussions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('discussion_comments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_comments');
    }
};


?>