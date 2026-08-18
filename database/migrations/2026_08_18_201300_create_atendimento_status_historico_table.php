<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RN-15: o historico e acrescentado, nunca sobrescrito. Tabela append-only -- ver
 * docs/privilegios.sql para o REVOKE UPDATE, DELETE de implantacao (doc 9.1).
 *
 * `alterado_por` referencia `users` (e nao `profissional`) porque a mudanca de status
 * pode partir de qualquer usuario da equipe, inclusive admin sem registro profissional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimento_status_historico', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('atendimento_id');
            $table->string('status_anterior', 30)->nullable();
            $table->string('status_novo', 30);
            $table->unsignedBigInteger('alterado_por');
            $table->text('observacao')->nullable();
            $table->unsignedInteger('permanencia_segundos')->nullable()
                ->comment('RF-39: tempo no status anterior');
            $table->dateTime('criado_em', 6);

            $table->index(['atendimento_id', 'criado_em'], 'ix_hist_atendimento');

            $table->foreign('atendimento_id', 'fk_hist_atendimento')
                ->references('id')->on('atendimento');
            $table->foreign('alterado_por', 'fk_hist_autor')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimento_status_historico');
    }
};
