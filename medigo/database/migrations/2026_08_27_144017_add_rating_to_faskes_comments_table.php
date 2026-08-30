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
    Schema::table('faskes_comments', function (Blueprint $table) {
        $table->tinyInteger('rating')->unsigned()->nullable()->after('faskes_id');
    });
}

public function down(): void
{
    Schema::table('faskes_comments', function (Blueprint $table) {
        $table->dropColumn('rating');
    });
}
};
