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
            $table->integer('cycle_number')->nullable()->after('drawn_numbers_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('historical_results', function (Blueprint $table) {
            $table->dropColumn('cycle_number');
        });
    }
};
