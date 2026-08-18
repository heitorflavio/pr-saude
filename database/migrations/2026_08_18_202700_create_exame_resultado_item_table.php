<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * doc 11.3: a faixa de referencia e gravada NO RESULTADO, nao apenas no catalogo. O
 * laboratorio pode mudar o metodo e a faixa amanha; o resultado de hoje precisa
 * continuar interpretavel com a faixa que valia hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exame_resultado_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exame_resultado_id');
            $table->string('analito', 120);
            $table->string('valor', 60);
            $table->string('unidade', 30)->nullable();
            $table->decimal('referencia_min', 12, 4)->nullable();
            $table->decimal('referencia_max', 12, 4)->nullable();
            $table->string('referencia_texto', 120)->nullable();
            $table->enum('sinalizacao', ['NORMAL', 'BAIXO', 'ALTO', 'CRITICO', 'INDETERMINADO'])
                ->default('NORMAL');

            $table->index('exame_resultado_id', 'ix_item_resultado');

            $table->foreign('exame_resultado_id', 'fk_ritem_resultado')
                ->references('id')->on('exame_resultado')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exame_resultado_item');
    }
};
