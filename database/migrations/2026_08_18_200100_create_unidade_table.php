<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidade', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            $table->string('cnes', 7)->nullable()
                ->comment('Cadastro Nacional de Estabelecimentos de Saude');
            $table->string('fuso_horario', 40)->default('America/Sao_Paulo');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('cnes', 'uk_unidade_cnes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidade');
    }
};
