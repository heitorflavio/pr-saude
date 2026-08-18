<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profissional_disponibilidade', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profissional_id');
            $table->enum('situacao', [
                'DISPONIVEL', 'EM_ATENDIMENTO', 'PAUSA', 'AUSENTE', 'FORA_PLANTAO',
            ]);
            $table->dateTime('inicio_em');
            $table->dateTime('fim_em')->nullable()->comment('NULL = situacao vigente');
            $table->string('observacao', 255)->nullable();

            $table->index(['profissional_id', 'fim_em'], 'ix_disp_prof_vigente');

            $table->foreign('profissional_id', 'fk_disp_profissional')
                ->references('user_id')->on('profissional');
        });

        DB::statement('
            ALTER TABLE profissional_disponibilidade
            ADD CONSTRAINT ck_disp_periodo
            CHECK (fim_em IS NULL OR fim_em >= inicio_em)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('profissional_disponibilidade');
    }
};
