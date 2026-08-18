<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-04, terceira entidade: o FATO ("dose das 12h aplicada as 12h37 por Joao,
 * COREN 123456").
 *
 * RN-20: `uk_adm_aprazamento` e a garantia de que a mesma dose aprazada nao e
 * administrada duas vezes. Nao e verificacao em PHP -- e indice unico, porque duas
 * tecnicas clicando ao mesmo tempo passariam por qualquer if().
 *
 * RN-22: `checado_por` implementa a dupla checagem de alta vigilancia. A regra de que
 * o conferente e distinto do executor fica na Action -- o banco garante a existencia
 * do segundo profissional, a aplicacao garante que sao pessoas diferentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administracao_medicamento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aprazamento_id')->nullable()
                ->comment('RN-20: unico -> impede dupla administracao da mesma dose');
            $table->unsignedBigInteger('prescricao_item_id');
            $table->unsignedBigInteger('atendimento_id');
            $table->decimal('dose_administrada', 10, 3)->nullable();
            $table->string('unidade_dose', 20)->nullable();
            $table->enum('via', [
                'ORAL', 'IV', 'IM', 'SC', 'TOPICO',
                'INALATORIO', 'RETAL', 'OFTALMICO', 'SL', 'OUTRA',
            ])->nullable();
            $table->dateTime('administrado_em', 6)
                ->comment('RN-29: horario de servidor');
            $table->unsignedBigInteger('administrado_por');
            $table->unsignedBigInteger('checado_por')->nullable()
                ->comment('RN-22: dupla checagem');
            $table->enum('resultado', ['ADMINISTRADA', 'NAO_ADMINISTRADA'])
                ->default('ADMINISTRADA');
            $table->enum('motivo_nao_administracao', [
                'RECUSA_PACIENTE', 'INDISPONIVEL', 'JEJUM', 'SUSPENSA_MEDICO',
                'INTERCORRENCIA', 'ACESSO_INDISPONIVEL', 'OUTRO',
            ])->nullable();
            $table->boolean('alerta_alergia_sobreposto')->default(false);
            $table->text('justificativa')->nullable();
            $table->text('observacao')->nullable();

            $table->unique('aprazamento_id', 'uk_adm_aprazamento');
            $table->index(['atendimento_id', 'administrado_em'], 'ix_adm_atendimento');
            $table->index('prescricao_item_id', 'ix_adm_item');
            $table->index(['administrado_por', 'administrado_em'], 'ix_adm_executor');

            $table->foreign('aprazamento_id', 'fk_adm_aprazamento')
                ->references('id')->on('aprazamento');
            $table->foreign('prescricao_item_id', 'fk_adm_item')
                ->references('id')->on('prescricao_item');
            $table->foreign('atendimento_id', 'fk_adm_atendimento')
                ->references('id')->on('atendimento');
            $table->foreign('administrado_por', 'fk_adm_executor')
                ->references('user_id')->on('profissional');
            $table->foreign('checado_por', 'fk_adm_checador')
                ->references('user_id')->on('profissional');
        });

        // RF-58: nao administrar e um evento clinico, nao um vazio. Exige motivo.
        DB::statement("
            ALTER TABLE administracao_medicamento
            ADD CONSTRAINT ck_adm_motivo
            CHECK (resultado = 'ADMINISTRADA' OR motivo_nao_administracao IS NOT NULL)
        ");

        // RN-21: sobrepor um alerta de alergia exige justificativa registrada. Sem ela,
        // o bloqueio nao pode ser vencido.
        DB::statement('
            ALTER TABLE administracao_medicamento
            ADD CONSTRAINT ck_adm_justificativa
            CHECK (alerta_alergia_sobreposto = FALSE OR justificativa IS NOT NULL)
        ');

        DB::statement("
            ALTER TABLE administracao_medicamento
            ADD CONSTRAINT ck_adm_dose
            CHECK (resultado = 'NAO_ADMINISTRADA' OR dose_administrada IS NOT NULL)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('administracao_medicamento');
    }
};
