<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona o vínculo entre apostas e fechamentos.
     */
    public function up(): void
    {
        Schema::table('bets', function (Blueprint $table) {
            $table
                ->foreignId('closing_id')
                ->nullable()
                ->after('user_id')
                ->constrained('closings')
                ->nullOnDelete();

            $table->index('closing_id');
        });
    }

    /**
     * Remove o vínculo entre apostas e fechamentos.
     */
    public function down(): void
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->dropForeign(['closing_id']);
            $table->dropIndex(['closing_id']);
            $table->dropColumn('closing_id');
        });
    }
};
