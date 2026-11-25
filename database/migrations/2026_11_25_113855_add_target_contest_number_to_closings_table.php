<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('closings', function (Blueprint $table) {
            $table->unsignedInteger('target_contest_number')->nullable()->after('contest_number')
                ->comment('Concurso para o qual o fechamento foi montado (antes de ser apostado)');
            $table->index(['user_id', 'target_contest_number']);
        });

        // Fechamentos já apostados: o concurso alvo é o concurso em que foram apostados
        DB::table('closings')
            ->whereNotNull('contest_number')
            ->update(['target_contest_number' => DB::raw('contest_number')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('closings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'target_contest_number']);
            $table->dropColumn('target_contest_number');
        });
    }
};
