<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-11: as alergias sao exibidas em destaque em toda tela do atendimento.
 * RN-21: a verificacao de alergia e sempre por principio ativo -- por isso o vinculo
 * opcional ao catalogo de medicamentos, alem da substancia em texto livre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paciente_alergia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('paciente_id');
            $table->string('substancia', 150);
            $table->unsignedBigInteger('medicamento_id')->nullable()
                ->comment('Vinculo ao catalogo, quando aplicavel');
            $table->enum('gravidade', ['LEVE', 'MODERADA', 'GRAVE', 'DESCONHECIDA'])
                ->default('DESCONHECIDA');
            $table->string('reacao', 255)->nullable();
            $table->unsignedBigInteger('registrado_por')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('paciente_id', 'ix_alergia_paciente');
            $table->index('medicamento_id', 'ix_alergia_medicamento');

            $table->foreign('paciente_id', 'fk_alergia_paciente')
                ->references('user_id')->on('paciente');
            $table->foreign('medicamento_id', 'fk_alergia_medicamento')
                ->references('id')->on('medicamento');
            $table->foreign('registrado_por', 'fk_alergia_registrador')
                ->references('user_id')->on('profissional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente_alergia');
    }
};
