<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-02: especializacao de `users`, com FK-PK compartilhada. Ver DECISOES.md D-01
 * para o rename de `usuario_id` para `user_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profissional', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedBigInteger('unidade_id');
            $table->string('nome_completo', 150);
            $table->string('matricula', 30)->nullable();
            $table->enum('categoria', [
                'MEDICO', 'ENFERMEIRO', 'TECNICO_ENFERMAGEM',
                'LABORATORIO', 'RECEPCAO', 'FARMACIA', 'ADMIN',
            ]);
            $table->enum('conselho_tipo', ['CRM', 'COREN', 'CRF', 'CRBM', 'OUTRO'])->nullable();
            $table->string('conselho_numero', 20)->nullable();
            $table->char('conselho_uf', 2)->nullable();
            $table->string('especialidade', 100)->nullable();
            $table->unsignedSmallInteger('capacidade_fila')->default(20)
                ->comment('Teto de referencia para balanceamento (doc 7.4)');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['conselho_tipo', 'conselho_numero', 'conselho_uf'], 'uk_profissional_conselho');
            $table->unique('matricula', 'uk_profissional_matricula');
            $table->index(['unidade_id', 'categoria', 'ativo'], 'ix_profissional_unidade_cat');

            $table->foreign('user_id', 'fk_profissional_usuario')->references('id')->on('users');
            $table->foreign('unidade_id', 'fk_profissional_unidade')->references('id')->on('unidade');
        });

        // RN-18: medico e enfermeiro nao existem sem numero de conselho. As demais
        // categorias (recepcao, laboratorio, admin) nao tem conselho profissional.
        DB::statement("
            ALTER TABLE profissional
            ADD CONSTRAINT ck_profissional_conselho
            CHECK (categoria NOT IN ('MEDICO','ENFERMEIRO') OR conselho_numero IS NOT NULL)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('profissional');
    }
};
