<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-15: toda impressao e registrada, com motivo. RF-16: a reimpressao usa o MESMO
 * token -- o token da pulseira e permanente (RN-03), entao esta tabela registra
 * eventos de impressao, nunca tokens novos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pulseira_impressao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('paciente_id');
            $table->unsignedBigInteger('atendimento_id')->nullable();
            $table->unsignedTinyInteger('classificacao_risco_id')->nullable();
            $table->enum('motivo', [
                'PRIMEIRA', 'REIMPRESSAO', 'RECLASSIFICACAO', 'DANIFICADA', 'OUTRO',
            ])->default('PRIMEIRA');
            $table->string('observacao', 255)->nullable();
            $table->unsignedBigInteger('impressa_por');
            $table->dateTime('criado_em');

            $table->index(['paciente_id', 'criado_em'], 'ix_pulseira_paciente');
            $table->index('atendimento_id', 'ix_pulseira_atendimento');

            $table->foreign('paciente_id', 'fk_pulseira_paciente')
                ->references('user_id')->on('paciente');
            $table->foreign('atendimento_id', 'fk_pulseira_atendimento')
                ->references('id')->on('atendimento');
            $table->foreign('classificacao_risco_id', 'fk_pulseira_risco')
                ->references('id')->on('classificacao_risco');
            $table->foreign('impressa_por', 'fk_pulseira_impressor')
                ->references('user_id')->on('profissional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pulseira_impressao');
    }
};
