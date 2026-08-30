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
    Schema::create('faskes', function (Blueprint $table) {
        $table->id();
        $table->string('nama_faskes');
        $table->string('tipe', 100);
        $table->string('kelas', 10)->nullable();
        $table->string('provinsi', 100);
        $table->string('kota_kabupaten', 100);
        $table->string('kecamatan', 100)->nullable();
        $table->text('alamat');
        $table->string('link_maps')->nullable();
        $table->decimal('latitude', 10, 8)->nullable();
        $table->decimal('longitude', 11, 8)->nullable();
        $table->boolean('is_support_bpjs')->default(false);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faskes');
    }
};
