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
        Schema::create('number_indications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('target_contest_number')->unique()
                ->comment('Concurso para o qual a indicação foi registrada (antes do sorteio)');
            $table->unsignedInteger('based_on_contest_number')
                ->comment('Último concurso conhecido no momento do registro');
            $table->json('scores')->comment('Pontuação heurística de cada dezena (1 a 25)');
            $table->json('most_indicated')->comment('Dezenas de maior pontuação');
            $table->json('least_indicated')->comment('Dezenas de menor pontuação');
            $table->unsignedTinyInteger('most_hits')->nullable()->comment('Quantas das mais indicadas foram sorteadas');
            $table->unsignedTinyInteger('least_hits')->nullable()->comment('Quantas das menos indicadas foram sorteadas');
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_indications');
    }
};
