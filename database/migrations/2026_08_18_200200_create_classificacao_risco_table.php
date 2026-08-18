<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-03: a classificacao de risco e tabela de dominio, nao ENUM, porque tem atributos
 * proprios (cor, tempo-alvo, peso de ordenacao). Um hospital que adote outro protocolo
 * altera dados, nao codigo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classificacao_risco', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('nome', 40);
            $table->enum('cor_nome', ['VERMELHO', 'LARANJA', 'AMARELO', 'VERDE', 'AZUL']);
            $table->char('cor_hex', 7);
            $table->unsignedSmallInteger('tempo_alvo_minutos');
            $table->unsignedTinyInteger('peso_ordenacao')->comment('Menor = mais prioritario');
            $table->boolean('exige_atendimento_imediato')->default(false);
            $table->string('descricao', 255)->nullable();

            $table->unique('cor_nome', 'uk_risco_cor');
            $table->unique('peso_ordenacao', 'uk_risco_peso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classificacao_risco');
    }
};
