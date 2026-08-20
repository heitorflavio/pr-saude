<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Doc §11.2: limites críticos são parâmetros do laboratório, nunca constantes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exame_faixa_critica', function (Blueprint $table) {
            $table->id();
            $table->string('analito', 120);
            $table->string('unidade', 30)->nullable();
            $table->decimal('critico_min', 14, 4)->nullable();
            $table->decimal('critico_max', 14, 4)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['analito', 'unidade'], 'uk_faixa_critica_analito_unidade');
            $table->index(['ativo', 'analito'], 'ix_faixa_critica_ativa');
        });

        DB::statement('ALTER TABLE exame_faixa_critica ADD CONSTRAINT ck_faixa_critica_limites CHECK (critico_min IS NOT NULL OR critico_max IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('exame_faixa_critica');
    }
};
