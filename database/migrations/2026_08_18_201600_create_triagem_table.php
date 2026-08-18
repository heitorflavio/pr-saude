<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reclassificacao e encadeada por `triagem_anterior_id`: a triagem anterior permanece
 * intacta e legivel (doc 7.5). Nada e sobrescrito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triagem', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('atendimento_id');
            $table->unsignedTinyInteger('classificacao_risco_id');
            $table->unsignedBigInteger('sinal_vital_id')->nullable();
            $table->unsignedBigInteger('realizada_por');
            $table->text('queixa_principal');
            $table->text('justificativa_classificacao')->nullable();
            $table->boolean('reclassificacao')->default(false);
            $table->unsignedBigInteger('triagem_anterior_id')->nullable();
            $table->dateTime('criado_em', 6);

            $table->index(['atendimento_id', 'criado_em'], 'ix_triagem_atendimento');

            $table->foreign('atendimento_id', 'fk_triagem_atendimento')
                ->references('id')->on('atendimento');
            $table->foreign('classificacao_risco_id', 'fk_triagem_risco')
                ->references('id')->on('classificacao_risco');
            $table->foreign('sinal_vital_id', 'fk_triagem_sinal')
                ->references('id')->on('sinal_vital');
            $table->foreign('realizada_por', 'fk_triagem_executor')
                ->references('user_id')->on('profissional');
            $table->foreign('triagem_anterior_id', 'fk_triagem_anterior')
                ->references('id')->on('triagem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triagem');
    }
};
