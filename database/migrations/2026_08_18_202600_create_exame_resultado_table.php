<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exame_resultado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exame_solicitacao_id');
            $table->text('laudo')->nullable();
            $table->text('conclusao')->nullable();
            $table->boolean('possui_valor_critico')->default(false)->comment('RN-25');
            $table->unsignedBigInteger('executado_por');
            $table->dateTime('executado_em');
            $table->unsignedBigInteger('liberado_por')->nullable();
            $table->dateTime('liberado_em')->nullable();
            $table->boolean('visivel_ao_paciente')->default(false)->comment('RN-24');
            $table->dateTime('criado_em', 6);

            $table->unique('exame_solicitacao_id', 'uk_resultado_solicitacao');
            $table->index(['possui_valor_critico', 'criado_em'], 'ix_resultado_critico');

            $table->foreign('exame_solicitacao_id', 'fk_result_solicitacao')
                ->references('id')->on('exame_solicitacao');
            $table->foreign('executado_por', 'fk_result_executor')
                ->references('user_id')->on('profissional');
            $table->foreign('liberado_por', 'fk_result_liberador')
                ->references('user_id')->on('profissional');
        });

        // RN-24: resultado so e visivel ao paciente apos liberacao explicita. O banco
        // recusa `visivel_ao_paciente = TRUE` sem quem liberou e quando -- um resultado
        // grave chegando ao paciente antes da leitura medica e dano assistencial.
        DB::statement('
            ALTER TABLE exame_resultado
            ADD CONSTRAINT ck_result_liberacao
            CHECK (
                visivel_ao_paciente = FALSE
                OR (liberado_por IS NOT NULL AND liberado_em IS NOT NULL)
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('exame_resultado');
    }
};
