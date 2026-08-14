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
        Schema::create('lotofacil_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('numbers_count')->unique()->comment('Quantidade de dezenas (15 a 20)');
            $table->decimal('price', 10, 2)->comment('Preço da aposta em R$');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotofacil_settings');
    }
};
