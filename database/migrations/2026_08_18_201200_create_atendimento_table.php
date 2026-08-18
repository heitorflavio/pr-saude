<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O coracao do modelo. Duas garantias moram aqui, e as duas sao do banco -- nao da
 * aplicacao, porque precisam sobreviver a condicao de corrida entre duas
 * recepcionistas e a bug de refatoracao:
 *
 *  - RN-07 / D-07: no maximo um atendimento nao finalizado por paciente por unidade,
 *    via coluna gerada `ativo_key` + indice unico.
 *  - RN-14: FINALIZADO e terminal e exige desfecho, via CHECK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimento', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('numero', 20)->comment('RF-21: ex. 2026-000148');
            $table->unsignedBigInteger('paciente_id');
            $table->unsignedBigInteger('unidade_id');
            $table->unsignedBigInteger('profissional_responsavel_id')->nullable();
            $table->unsignedTinyInteger('classificacao_risco_id')->nullable()
                ->comment('Classificacao vigente (RN-09)');
            $table->enum('status', [
                'AGUARDANDO_TRIAGEM',
                'AGUARDANDO_ATENDIMENTO',
                'EM_ATENDIMENTO',
                'AGUARDANDO_EXAME',
                'EM_EXAME',
                'AGUARDANDO_MEDICACAO',
                'EM_OBSERVACAO',
                'FINALIZADO',
                'CANCELADO',
            ])->default('AGUARDANDO_TRIAGEM');
            $table->enum('origem', ['ESPONTANEA', 'SAMU', 'ENCAMINHADO', 'TRANSFERENCIA'])
                ->default('ESPONTANEA');
            $table->text('sintomas_entrada')->nullable();
            $table->dateTime('admitido_em');
            $table->dateTime('primeiro_atendimento_em')->nullable();
            $table->dateTime('finalizado_em')->nullable();
            $table->enum('desfecho', [
                'ALTA', 'ENCAMINHAMENTO', 'INTERNACAO', 'EVASAO', 'OBITO', 'TRANSFERENCIA',
            ])->nullable();
            $table->text('desfecho_observacao')->nullable();
            $table->unsignedBigInteger('aberto_por')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('uuid', 'uk_atendimento_uuid');
            $table->unique('numero', 'uk_atendimento_numero');
            $table->index(['paciente_id', 'admitido_em'], 'ix_atendimento_paciente');
            $table->index(['unidade_id', 'status'], 'ix_atendimento_status');
            $table->index(['profissional_responsavel_id', 'status'], 'ix_atendimento_responsavel');

            $table->foreign('paciente_id', 'fk_atend_paciente')
                ->references('user_id')->on('paciente');
            $table->foreign('unidade_id', 'fk_atend_unidade')
                ->references('id')->on('unidade');
            $table->foreign('profissional_responsavel_id', 'fk_atend_responsavel')
                ->references('user_id')->on('profissional');
            $table->foreign('classificacao_risco_id', 'fk_atend_risco')
                ->references('id')->on('classificacao_risco');
            $table->foreign('aberto_por', 'fk_atend_aberto_por')
                ->references('id')->on('users');
        });

        // RN-07 / D-07: enquanto o atendimento esta aberto, `ativo_key` vale o
        // paciente_id e o indice unico impede um segundo registro aberto do mesmo
        // paciente na mesma unidade. Ao finalizar, passa a NULL -- e como o MySQL
        // admite multiplos NULL em indice unico, o historico acumula sem colidir.
        DB::statement("
            ALTER TABLE atendimento
            ADD COLUMN ativo_key BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                    CASE WHEN status IN ('FINALIZADO','CANCELADO') THEN NULL ELSE paciente_id END
                ) STORED,
            ADD UNIQUE KEY uk_atendimento_ativo (unidade_id, ativo_key)
        ");

        // RN-14: FINALIZADO exige desfecho. Sem isso, um atendimento encerrado sem
        // registro do que aconteceu com o paciente seria aceito pelo banco.
        DB::statement("
            ALTER TABLE atendimento
            ADD CONSTRAINT ck_atend_desfecho
            CHECK (status <> 'FINALIZADO' OR desfecho IS NOT NULL)
        ");

        DB::statement("
            ALTER TABLE atendimento
            ADD CONSTRAINT ck_atend_finalizado
            CHECK (status <> 'FINALIZADO' OR finalizado_em IS NOT NULL)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimento');
    }
};
