<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-06: sinais vitais em tabela propria, nao dentro de `triagem`. Sao aferidos na
 * triagem e repetidamente durante o atendimento (observacao, pos-medicacao); guarda-los
 * na triagem impediria a serie temporal.
 *
 * Os tres CHECK de faixa nao sao decorativos: temperatura de 99,9 C, saturacao de 150%
 * e dor 15 numa escala de 0 a 10 sao erros de digitacao que viram decisao clinica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sinal_vital', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('atendimento_id');
            $table->unsignedSmallInteger('pressao_sistolica')->nullable();
            $table->unsignedSmallInteger('pressao_diastolica')->nullable();
            $table->unsignedSmallInteger('frequencia_cardiaca')->nullable();
            $table->unsignedSmallInteger('frequencia_respiratoria')->nullable();
            $table->decimal('saturacao_o2', 4, 1)->nullable();
            $table->decimal('temperatura', 4, 1)->nullable();
            $table->decimal('glicemia', 5, 1)->nullable();
            $table->decimal('peso_kg', 5, 2)->nullable();
            $table->unsignedSmallInteger('altura_cm')->nullable();
            $table->unsignedTinyInteger('escala_dor')->nullable();
            $table->unsignedBigInteger('aferido_por');
            $table->dateTime('aferido_em');

            $table->index(['atendimento_id', 'aferido_em'], 'ix_sinal_atendimento');

            $table->foreign('atendimento_id', 'fk_sinal_atendimento')
                ->references('id')->on('atendimento');
            $table->foreign('aferido_por', 'fk_sinal_aferidor')
                ->references('user_id')->on('profissional');
        });

        DB::statement('
            ALTER TABLE sinal_vital
            ADD CONSTRAINT ck_sinal_dor
            CHECK (escala_dor IS NULL OR escala_dor BETWEEN 0 AND 10)
        ');

        DB::statement('
            ALTER TABLE sinal_vital
            ADD CONSTRAINT ck_sinal_spo2
            CHECK (saturacao_o2 IS NULL OR saturacao_o2 BETWEEN 0 AND 100)
        ');

        DB::statement('
            ALTER TABLE sinal_vital
            ADD CONSTRAINT ck_sinal_temp
            CHECK (temperatura IS NULL OR temperatura BETWEEN 25 AND 45)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('sinal_vital');
    }
};
