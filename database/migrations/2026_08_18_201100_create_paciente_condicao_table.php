<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paciente_condicao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('paciente_id');
            $table->string('descricao', 255);
            $table->char('cid10_codigo', 7)->nullable();
            $table->date('desde')->nullable();
            $table->unsignedBigInteger('registrado_por')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('paciente_id', 'ix_condicao_paciente');

            $table->foreign('paciente_id', 'fk_condicao_paciente')
                ->references('user_id')->on('paciente');
            $table->foreign('cid10_codigo', 'fk_condicao_cid')
                ->references('codigo')->on('cid10');
            $table->foreign('registrado_por', 'fk_condicao_registrador')
                ->references('user_id')->on('profissional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente_condicao');
    }
};
