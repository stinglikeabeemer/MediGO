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
        Schema::create('bookmark_faskes', function (Blueprint $table) {
            $table->id('id_bookmark');
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_faskes');
            $table->timestamps();

            $table->foreign('id_user')->references('id_user')->on('user')->onDelete('cascade');
            $table->foreign('id_faskes')->references('id_faskes')->on('faskes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmark_faskes');
    }
};
