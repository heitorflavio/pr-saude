<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-04, primeira das tres entidades do ciclo do medicamento: a ORDEM medica
 * ("Dipirona 1 g, IV, de 6 em 6 h, por 2 dias"). O aprazamento e a agenda; a
 * administracao e o fato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescricao_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prescricao_id');
            $table->unsignedBigInteger('medicamento_id');
            $table->decimal('dose', 10, 3);
            $table->string('unidade_dose', 20);
            $table->enum('via', [
                'ORAL', 'IV', 'IM', 'SC', 'TOPICO',
                'INALATORIO', 'RETAL', 'OFTALMICO', 'SL', 'OUTRA',
            ]);
            $table->unsignedSmallInteger('frequencia_horas')->nullable()
                ->comment('NULL quando se_necessario = TRUE');
            $table->unsignedSmallInteger('duracao_horas')->nullable();
            $table->boolean('se_necessario')->default(false)
                ->comment('Medicacao SOS / PRN');
            $table->string('diluicao', 255)->nullable();
            $table->string('velocidade_infusao', 60)->nullable();
            $table->text('observacao')->nullable();
            $table->enum('status', ['VIGENTE', 'SUSPENSO', 'CONCLUIDO'])->default('VIGENTE');

            $table->index(['prescricao_id', 'status'], 'ix_item_prescricao');
            $table->index('medicamento_id', 'ix_item_medicamento');

            $table->foreign('prescricao_id', 'fk_item_prescricao')
                ->references('id')->on('prescricao');
            $table->foreign('medicamento_id', 'fk_item_medicamento')
                ->references('id')->on('medicamento');
        });

        // Dose zero ou negativa nao e prescricao, e erro de digitacao.
        DB::statement('
            ALTER TABLE prescricao_item
            ADD CONSTRAINT ck_item_dose
            CHECK (dose > 0)
        ');

        // Medicacao de horario precisa de frequencia; "se necessario" nao e aprazada.
        DB::statement('
            ALTER TABLE prescricao_item
            ADD CONSTRAINT ck_item_frequencia
            CHECK (se_necessario = TRUE OR frequencia_horas IS NOT NULL)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('prescricao_item');
    }
};
