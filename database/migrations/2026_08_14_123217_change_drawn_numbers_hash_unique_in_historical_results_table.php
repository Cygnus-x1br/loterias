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
        Schema::table('historical_results', function (Blueprint $table) {
            $table->dropUnique(['drawn_numbers_hash']);
            $table->index('drawn_numbers_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('historical_results', function (Blueprint $table) {
            $table->dropIndex(['drawn_numbers_hash']);
            $table->unique('drawn_numbers_hash');
        });
    }
};
