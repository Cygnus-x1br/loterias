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
        Schema::create('historical_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('contest_number')->unique()->comment('Número do concurso');
            $table->date('draw_date')->comment('Data do sorteio');
            $table->json('drawn_numbers')->comment('Dezenas sorteadas em formato JSON (sempre ordenadas)');
            $table->string('drawn_numbers_hash', 64)->unique()->comment('Hash SHA-256 das dezenas ordenadas para busca rápida');

            // Colunas de ganhadores e rateios
            $table->unsignedInteger('winners_15_hits')->nullable()->comment('Ganhadores com 15 acertos');
            $table->decimal('payout_15_hits', 15, 2)->nullable()->comment('Rateio para 15 acertos');
            $table->unsignedInteger('winners_14_hits')->nullable()->comment('Ganhadores com 14 acertos');
            $table->decimal('payout_14_hits', 15, 2)->nullable()->comment('Rateio para 14 acertos');
            $table->unsignedInteger('winners_13_hits')->nullable()->comment('Ganhadores com 13 acertos');
            $table->decimal('payout_13_hits', 15, 2)->nullable()->comment('Rateio para 13 acertos');
            $table->unsignedInteger('winners_12_hits')->nullable()->comment('Ganhadores com 12 acertos');
            $table->decimal('payout_12_hits', 15, 2)->nullable()->comment('Rateio para 12 acertos');
            $table->unsignedInteger('winners_11_hits')->nullable()->comment('Ganhadores com 11 acertos');
            $table->decimal('payout_11_hits', 15, 2)->nullable()->comment('Rateio para 11 acertos');

            $table->timestamps();

            // Índices adicionais para otimização de consultas
            $table->index('draw_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_results');
    }
};
