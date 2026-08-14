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
        Schema::table('closings', function (Blueprint $table) {
            $table->unsignedInteger('contest_number')->nullable()->after('status');
            $table->date('draw_date')->nullable()->after('contest_number');
        });

        Schema::table('bets', function (Blueprint $table) {
            $table->unsignedInteger('contest_number')->nullable()->after('status');
            $table->date('draw_date')->nullable()->after('contest_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('closings', function (Blueprint $table) {
            $table->dropColumn(['contest_number', 'draw_date']);
        });

        Schema::table('bets', function (Blueprint $table) {
            $table->dropColumn(['contest_number', 'draw_date']);
        });
    }
};
