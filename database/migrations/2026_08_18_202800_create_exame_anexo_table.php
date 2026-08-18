<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `caminho` aponta para fora do document root -- anexo de exame nao pode ser servido
 * por URL direta. `hash_sha256` garante que o arquivo entregue e o arquivo gravado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exame_anexo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exame_resultado_id');
            $table->string('nome_original', 255);
            $table->string('caminho', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('tamanho_bytes');
            $table->char('hash_sha256', 64)->comment('Integridade do arquivo');
            $table->unsignedBigInteger('enviado_por');
            $table->dateTime('criado_em');

            $table->index('exame_resultado_id', 'ix_anexo_resultado');

            $table->foreign('exame_resultado_id', 'fk_anexo_resultado')
                ->references('id')->on('exame_resultado');
            $table->foreign('enviado_por', 'fk_anexo_remetente')
                ->references('user_id')->on('profissional');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exame_anexo');
    }
};
