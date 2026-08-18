<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queixa', function (Blueprint $table) {
            $table->id();
            $table->string('descricao', 150);
            $table->string('fluxograma_manchester', 100)->nullable();
            $table->boolean('ativo')->default(true);

            $table->unique('descricao', 'uk_queixa_descricao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queixa');
    }
};
