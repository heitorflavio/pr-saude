<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RN-10: nao existe coluna `posicao`. A posicao e calculada na leitura por
 * ROW_NUMBER() na view `vw_fila_ordenada` -- persistir posicao significaria
 * reordenar N linhas a cada chegada, e a fila ficaria errada entre uma escrita e outra.
 *
 * `entrou_em` e DATETIME(6): o desempate entre dois verdes e por ordem de chegada, e
 * dois cadastros no mesmo segundo precisam desempatar de forma estavel.
 * Reclassificacao e transferencia preservam `entrou_em` (doc 7.5) -- o paciente nao
 * volta ao fim da fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fila_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('atendimento_id');
            $table->unsignedBigInteger('profissional_id')->nullable()
                ->comment('NULL = fila geral, sem atribuicao');
            $table->unsignedTinyInteger('classificacao_risco_id');
            $table->enum('situacao', [
                'AGUARDANDO', 'CHAMADO', 'EM_ATENDIMENTO',
                'CONCLUIDO', 'TRANSFERIDO', 'DESISTENCIA',
            ])->default('AGUARDANDO');
            $table->dateTime('entrou_em', 6);
            $table->dateTime('chamado_em')->nullable();
            $table->dateTime('saiu_em')->nullable();
            $table->unsignedBigInteger('transferido_de_id')->nullable();
            $table->text('justificativa_transferencia')->nullable();
            $table->unsignedBigInteger('criado_por');

            $table->index(
                ['profissional_id', 'situacao', 'classificacao_risco_id', 'entrou_em'],
                'ix_fila_ordenacao'
            );
            $table->index('atendimento_id', 'ix_fila_atendimento');

            $table->foreign('atendimento_id', 'fk_fila_atendimento')
                ->references('id')->on('atendimento');
            $table->foreign('profissional_id', 'fk_fila_profissional')
                ->references('user_id')->on('profissional');
            $table->foreign('classificacao_risco_id', 'fk_fila_risco')
                ->references('id')->on('classificacao_risco');
            $table->foreign('transferido_de_id', 'fk_fila_anterior')
                ->references('id')->on('fila_item');
            $table->foreign('criado_por', 'fk_fila_criador')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fila_item');
    }
};
