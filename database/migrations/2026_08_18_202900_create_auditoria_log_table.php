<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RNF-11: tabela imutavel. Append-only por REVOKE UPDATE, DELETE na implantacao
 * (docs/privilegios.sql).
 *
 * Registra LEITURA, nao so escrita (doc 14.3) -- em dado de saude o dano tipico e
 * bisbilhotagem, nao alteracao.
 *
 * `perfis_no_momento` guarda o snapshot das roles no instante do evento. Se as roles
 * do usuario mudarem depois, o log continua dizendo com que papel ele agiu.
 *
 * Sem FK de proposito: o log precisa sobreviver a remocao logica de qualquer entidade
 * que ele referencia, e uma FK criaria dependencia de ordem na retencao (doc 14.4).
 * A coluna `usuario_id` do schema.sql virou `user_id` por consistencia com D-01.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('perfis_no_momento', 255)->nullable();
            $table->string('acao', 60);
            $table->string('entidade', 60)->nullable();
            $table->unsignedBigInteger('entidade_id')->nullable();
            $table->unsignedBigInteger('paciente_id')->nullable();
            $table->unsignedBigInteger('atendimento_id')->nullable();
            $table->text('justificativa')->nullable();
            $table->json('dados_antes')->nullable();
            $table->json('dados_depois')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('criado_em', 6);

            // "Quem acessou os dados deste paciente nos ultimos 90 dias?" (RF da Fase 12)
            $table->index(['paciente_id', 'criado_em'], 'ix_audit_paciente');
            $table->index(['user_id', 'criado_em'], 'ix_audit_usuario');
            $table->index(['acao', 'criado_em'], 'ix_audit_acao');
            $table->index(['entidade', 'entidade_id'], 'ix_audit_entidade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_log');
    }
};
