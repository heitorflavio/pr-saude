<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exame', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20);
            $table->string('nome', 150);
            $table->enum('tipo', ['LABORATORIAL', 'IMAGEM', 'GRAFICO', 'OUTRO']);
            $table->text('preparo')->nullable();
            $table->unsignedSmallInteger('prazo_padrao_minutos')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('codigo', 'uk_exame_codigo');
            $table->index(['tipo', 'ativo'], 'ix_exame_tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exame');
    }
};
