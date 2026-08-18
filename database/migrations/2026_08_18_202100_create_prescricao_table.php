<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescricao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('atendimento_id');
            $table->unsignedBigInteger('prescrito_por');
            $table->enum('status', ['VIGENTE', 'SUSPENSA', 'CONCLUIDA'])->default('VIGENTE');
            $table->dateTime('vigencia_inicio');
            $table->dateTime('vigencia_fim')->nullable();
            $table->text('observacao')->nullable();
            $table->unsignedBigInteger('suspensa_por')->nullable();
            $table->dateTime('suspensa_em')->nullable();
            $table->text('motivo_suspensao')->nullable();
            $table->dateTime('criado_em', 6);

            $table->index(['atendimento_id', 'status'], 'ix_prescricao_atendimento');

            $table->foreign('atendimento_id', 'fk_presc_atendimento')
                ->references('id')->on('atendimento');
            $table->foreign('prescrito_por', 'fk_presc_prescritor')
                ->references('user_id')->on('profissional');
            $table->foreign('suspensa_por', 'fk_presc_suspensor')
                ->references('user_id')->on('profissional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescricao');
    }
};
