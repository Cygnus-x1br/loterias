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
        Schema::create('consolidated_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('contest_number');
            $table->json('closing_ids');
            $table->json('base_numbers');
            $table->integer('total_bets');
            $table->string('status')->default('pending');
            $table->string('report_type'); // 'exact' or 'monte_carlo'
            $table->integer('guarantee_hits')->default(15);
            $table->integer('guarantee_points')->default(14);
            $table->json('coverage_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consolidated_audits');
    }
};
