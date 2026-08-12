<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de apostas.
     */
    public function up(): void
    {
        Schema::create('bets', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name')->nullable();

            /*
             * Armazena as dezenas da aposta como JSON.
             *
             * Exemplo:
             * [1, 3, 5, 7, 9, 11, 13, 15, 17, 19, 21, 22, 23, 24, 25]
             */
            $table->json('numbers');

            /*
             * Origem da aposta:
             * manual, gerada, importada ou demonstrativa.
             */
            $table
                ->string('source')
                ->default('manual');

            /*
             * Método utilizado para criar a aposta.
             */
            $table
                ->string('method')
                ->default('manual');

            /*
             * Status inicial da aposta.
             */
            $table
                ->string('status')
                ->default('active');

            /*
             * Quantidade de acertos.
             * Permanecerá nula até existir uma conferência.
             */
            $table->unsignedTinyInteger('hits')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('source');
            $table->index('method');
        });
    }

    /**
     * Remove a tabela de apostas.
     */
    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};
