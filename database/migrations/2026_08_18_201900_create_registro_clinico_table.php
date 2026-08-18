<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-05: prontuario append-only. Esta tabela NAO admite UPDATE nem DELETE -- a correcao
 * cria um registro novo do tipo ADENDO apontando para o original, que permanece
 * visivel e marcado (RN-16, RN-17).
 *
 * A garantia tem tres camadas: o model `RegistroClinico` sobrescreve save() e delete()
 * para lancar RegistroImutavelException; o CHECK abaixo impede adendo orfao; e o
 * REVOKE UPDATE, DELETE de docs/privilegios.sql fecha a porta no proprio banco
 * (doc 9.1).
 *
 * `autor_nome` e `autor_conselho` sao snapshots deliberados: se o cadastro do
 * profissional mudar depois, o registro clinico continua dizendo quem assinou naquele
 * momento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_clinico', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('atendimento_id');
            $table->enum('tipo', [
                'ANAMNESE', 'EVOLUCAO_MEDICA', 'EVOLUCAO_ENFERMAGEM',
                'OBSERVACAO', 'ADENDO', 'SUMARIO_ALTA', 'INTERCORRENCIA',
            ]);
            $table->text('subjetivo')->nullable()->comment('SOAP - S');
            $table->text('objetivo')->nullable()->comment('SOAP - O');
            $table->text('avaliacao')->nullable()->comment('SOAP - A');
            $table->text('plano')->nullable()->comment('SOAP - P');
            $table->text('conteudo_livre')->nullable();
            $table->boolean('sigiloso')->default(false)
                ->comment('RF-77: oculto no portal do paciente');
            $table->unsignedBigInteger('registro_retificado_id')->nullable()
                ->comment('RN-16: adendo aponta para o original');
            $table->text('motivo_retificacao')->nullable();
            $table->unsignedBigInteger('autor_id');
            $table->string('autor_nome', 150)
                ->comment('Snapshot: o log nao muda se o cadastro mudar');
            $table->string('autor_conselho', 40)->nullable()
                ->comment('Snapshot ex.: CRM/SP 123456');
            $table->char('hash_conteudo', 64)->comment('SHA-256 do conteudo canonico');
            $table->char('hash_anterior', 64)->nullable()
                ->comment('Encadeamento com o registro anterior do atendimento');
            $table->dateTime('criado_em', 6);

            $table->unique('uuid', 'uk_registro_uuid');
            $table->index(['atendimento_id', 'criado_em'], 'ix_registro_atendimento');
            $table->index(['autor_id', 'criado_em'], 'ix_registro_autor');
            $table->index('registro_retificado_id', 'ix_registro_retificado');

            $table->foreign('atendimento_id', 'fk_registro_atendimento')
                ->references('id')->on('atendimento');
            $table->foreign('autor_id', 'fk_registro_autor')
                ->references('user_id')->on('profissional');
            $table->foreign('registro_retificado_id', 'fk_registro_retificado')
                ->references('id')->on('registro_clinico');
        });

        // RN-16: um adendo sem apontar o registro que retifica, e sem dizer por que,
        // e apenas mais uma nota solta -- nao uma retificacao rastreavel.
        DB::statement("
            ALTER TABLE registro_clinico
            ADD CONSTRAINT ck_registro_adendo
            CHECK (
                tipo <> 'ADENDO'
                OR (registro_retificado_id IS NOT NULL AND motivo_retificacao IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_clinico');
    }
};
