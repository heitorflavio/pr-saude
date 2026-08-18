<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-02: heranca por tabela de classe. `paciente` e especializacao de `users`, com
 * FK-PK compartilhada -- uma pessoa tem um unico usuario, mesmo quando e paciente e
 * profissional ao mesmo tempo. A coluna era `usuario_id` no schema.sql; virou
 * `user_id` por causa do rename da tabela (DECISOES.md D-01).
 *
 * Nao existe coluna `idade` aqui, e nao deve existir em nenhuma tabela: e atributo
 * derivado de `data_nascimento` (D-01, RN-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paciente', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->uuid('uuid');

            // RN-03: opaco, unico, imutavel. O QR Code nao codifica id nem CPF.
            $table->string('token_pulseira', 64);

            $table->string('nome_completo', 150);
            $table->string('nome_social', 150)->nullable();
            $table->char('cpf', 11)->nullable()
                ->comment('RF-04: nulo permitido para nao identificado');
            $table->char('cns', 15)->nullable();
            $table->date('data_nascimento');
            $table->enum('sexo', ['FEMININO', 'MASCULINO', 'OUTRO', 'NAO_INFORMADO'])
                ->default('NAO_INFORMADO');
            $table->string('nome_mae', 150)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('contato_emergencia_nome', 150)->nullable();
            $table->string('contato_emergencia_telefone', 20)->nullable();
            $table->string('logradouro', 180)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 80)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->char('uf', 2)->nullable();
            $table->char('cep', 8)->nullable();
            $table->boolean('identificacao_provisoria')->default(false);
            $table->string('codigo_provisorio', 20)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('uuid', 'uk_paciente_uuid');
            $table->unique('token_pulseira', 'uk_paciente_token');
            $table->unique('cpf', 'uk_paciente_cpf');
            $table->unique('cns', 'uk_paciente_cns');
            $table->unique('codigo_provisorio', 'uk_paciente_provisorio');
            $table->index('nome_completo', 'ix_paciente_nome');
            $table->index('data_nascimento', 'ix_paciente_nascimento');

            $table->foreign('user_id', 'fk_paciente_usuario')->references('id')->on('users');
        });

        // RF-03: CPF com exatamente 11 digitos. O digito verificador e validado na
        // aplicacao (regra customizada); aqui o banco garante o formato.
        DB::statement("
            ALTER TABLE paciente
            ADD CONSTRAINT ck_paciente_cpf_digitos
            CHECK (cpf IS NULL OR cpf REGEXP '^[0-9]{11}$')
        ");

        // RF-04 / RN-30: ou o paciente tem CPF, ou tem codigo provisorio. Nunca nenhum
        // dos dois -- um paciente sem qualquer identificador e um registro orfao.
        DB::statement('
            ALTER TABLE paciente
            ADD CONSTRAINT ck_paciente_identificacao
            CHECK (
                (identificacao_provisoria = FALSE AND cpf IS NOT NULL)
                OR (identificacao_provisoria = TRUE AND codigo_provisorio IS NOT NULL)
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente');
    }
};
