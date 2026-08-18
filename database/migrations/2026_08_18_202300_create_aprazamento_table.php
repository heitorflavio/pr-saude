<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-04, segunda entidade: a AGENDA. O aprazamento e ancorado em horarios redondos
 * (6/12/18/00), nao no minuto do clique -- ver doc 10.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aprazamento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prescricao_item_id');
            $table->unsignedSmallInteger('sequencia');
            $table->dateTime('horario_previsto');
            $table->enum('situacao', [
                'PENDENTE', 'ADMINISTRADA', 'NAO_ADMINISTRADA', 'SUSPENSA',
            ])->default('PENDENTE');

            $table->unique(['prescricao_item_id', 'sequencia'], 'uk_aprazamento_seq');
            $table->index(['situacao', 'horario_previsto'], 'ix_aprazamento_agenda');

            $table->foreign('prescricao_item_id', 'fk_apraz_item')
                ->references('id')->on('prescricao_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aprazamento');
    }
};
