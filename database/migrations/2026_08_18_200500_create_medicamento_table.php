<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicamento', function (Blueprint $table) {
            $table->id();
            $table->string('nome_comercial', 150);
            $table->string('principio_ativo', 150);
            $table->string('concentracao', 60)->nullable()->comment('ex.: 500 mg/mL');
            $table->string('forma_farmaceutica', 60)->nullable()->comment('ex.: comprimido, ampola');
            $table->enum('classe_via', [
                'ORAL', 'IV', 'IM', 'SC', 'TOPICO',
                'INALATORIO', 'RETAL', 'OFTALMICO', 'SL', 'OUTRA',
            ]);
            $table->boolean('injetavel')->default(false);
            $table->boolean('alta_vigilancia')->default(false)
                ->comment('RN-22: exige dupla checagem');
            $table->boolean('controlado')->default(false)
                ->comment('Portaria SVS/MS 344/1998');
            $table->string('unidade_dose_padrao', 20)->nullable();
            $table->decimal('dose_maxima_diaria', 10, 3)->nullable();
            $table->text('observacao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // RN-21: a verificacao de alergia e por principio ativo, nunca por nome
            // comercial -- por isso o indice dedicado.
            $table->index('principio_ativo', 'ix_medicamento_principio');
            $table->index('nome_comercial', 'ix_medicamento_nome');
            $table->index('alta_vigilancia', 'ix_medicamento_vigilancia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicamento');
    }
};
