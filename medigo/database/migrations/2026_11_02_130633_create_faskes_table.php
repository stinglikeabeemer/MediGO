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
        Schema::create('faskes', function (Blueprint $table) {
            $table->id('id_faskes');
            $table->string('nama_faskes', 255);
            $table->string('jenis_faskes', 100);
            $table->string('kota_kabupaten', 100);
            $table->text('alamat_faskes');
            $table->string('provinsi', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('kelas', 50)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('link_maps')->nullable();
            $table->boolean('_support_bpjs')->default(false);
            // Menghapus $table->timestamps();
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
