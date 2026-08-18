<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exame_solicitacao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('atendimento_id');
            $table->unsignedBigInteger('exame_id');
            $table->unsignedBigInteger('solicitado_por');
            $table->enum('carater', ['ROTINA', 'URGENTE'])->default('ROTINA');
            $table->text('indicacao_clinica')->nullable();
            $table->enum('situacao', [
                'SOLICITADO', 'COLETADO', 'EM_EXECUCAO', 'CONCLUIDO', 'LIBERADO', 'CANCELADO',
            ])->default('SOLICITADO');
            $table->dateTime('solicitado_em', 6);
            $table->dateTime('coletado_em')->nullable();
            $table->unsignedBigInteger('coletado_por')->nullable();
            $table->dateTime('cancelado_em')->nullable();
            $table->unsignedBigInteger('cancelado_por')->nullable();
            $table->text('motivo_cancelamento')->nullable();

            $table->index(['atendimento_id', 'situacao'], 'ix_solic_atendimento');
            // Fila do laboratorio: urgentes primeiro, depois ordem de solicitacao.
            $table->index(['situacao', 'carater', 'solicitado_em'], 'ix_solic_fila_lab');
            $table->index('exame_id', 'ix_solic_exame');

            $table->foreign('atendimento_id', 'fk_solic_atendimento')
                ->references('id')->on('atendimento');
            $table->foreign('exame_id', 'fk_solic_exame')
                ->references('id')->on('exame');
            $table->foreign('solicitado_por', 'fk_solic_solicitante')
                ->references('user_id')->on('profissional');
            $table->foreign('coletado_por', 'fk_solic_coletor')
                ->references('user_id')->on('profissional');
            $table->foreign('cancelado_por', 'fk_solic_cancelador')
                ->references('user_id')->on('profissional');
        });

        DB::statement("
            ALTER TABLE exame_solicitacao
            ADD CONSTRAINT ck_solic_cancelamento
            CHECK (situacao <> 'CANCELADO' OR motivo_cancelamento IS NOT NULL)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('exame_solicitacao');
    }
};
