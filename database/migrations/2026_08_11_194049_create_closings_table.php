<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela de fechamentos.
     */
    public function up(): void
    {
        Schema::create('closings', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
             * Nome definido pelo usuário para identificar o fechamento.
             */
            $table->string('name');

            /*
             * Método planejado para o fechamento.
             *
             * Exemplos:
             * integral, reduced, wheel, random, balanced
             */
            $table
                ->string('method')
                ->default('reduced');

            /*
             * Dezenas do grupo-base armazenadas em JSON.
             *
             * Exemplo:
             * [1, 2, 3, ..., 20]
             */
            $table->json('base_numbers');

            /*
             * Quantidade de dezenas em cada aposta gerada.
             * Na Lotofácil, o valor mínimo é 15.
             */
            $table
                ->unsignedTinyInteger('bet_size')
                ->default(15);

            /*
             * Quantidade planejada de apostas no fechamento.
             */
            $table
                ->unsignedInteger('planned_bets')
                ->nullable();

            /*
             * Garantia desejada, quando informada.
             *
             * Permanece nula até que a garantia seja definida
             * ou calculada por um serviço específico.
             */
            $table
                ->unsignedTinyInteger('guarantee')
                ->nullable();

            /*
             * Orçamento estimado para o fechamento.
             */
            $table
                ->decimal('budget', 10, 2)
                ->nullable();

            /*
             * Parâmetros adicionais para futura evolução dos métodos.
             */
            $table
                ->json('parameters')
                ->nullable();

            /*
             * Status do fechamento.
             *
             * draft: rascunho
             * processing: em processamento
             * completed: concluído
             * failed: falhou
             */
            $table
                ->string('status')
                ->default('draft');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('method');
        });
    }

    /**
     * Remove a tabela de fechamentos.
     */
    public function down(): void
    {
        Schema::dropIfExists('closings');
    }
};
