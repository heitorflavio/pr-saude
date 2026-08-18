<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostico', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('atendimento_id');
            $table->char('cid10_codigo', 7);
            $table->enum('natureza', ['SUSPEITA', 'DEFINITIVO', 'DIFERENCIAL'])
                ->default('SUSPEITA');
            $table->boolean('principal')->default(false);
            $table->text('observacao')->nullable();
            $table->unsignedBigInteger('registrado_por');
            $table->dateTime('criado_em');

            $table->index('atendimento_id', 'ix_diagnostico_atendimento');
            $table->index('cid10_codigo', 'ix_diagnostico_cid');

            $table->foreign('atendimento_id', 'fk_diag_atendimento')
                ->references('id')->on('atendimento');
            $table->foreign('cid10_codigo', 'fk_diag_cid')
                ->references('codigo')->on('cid10');
            $table->foreign('registrado_por', 'fk_diag_registrador')
                ->references('user_id')->on('profissional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostico');
    }
};
