<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimento_sintoma', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('atendimento_id');
            $table->unsignedBigInteger('queixa_id')->nullable();
            $table->string('descricao_livre', 255)->nullable();

            $table->index('atendimento_id', 'ix_sintoma_atendimento');

            $table->foreign('atendimento_id', 'fk_sintoma_atendimento')
                ->references('id')->on('atendimento');
            $table->foreign('queixa_id', 'fk_sintoma_queixa')
                ->references('id')->on('queixa');
        });

        // Um sintoma sem queixa catalogada e sem descricao livre nao informa nada.
        DB::statement('
            ALTER TABLE atendimento_sintoma
            ADD CONSTRAINT ck_sintoma_conteudo
            CHECK (queixa_id IS NOT NULL OR descricao_livre IS NOT NULL)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimento_sintoma');
    }
};
