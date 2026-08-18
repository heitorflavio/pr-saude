<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estende `users` com as colunas da tabela `usuario` do schema.sql.
 *
 * O documento modela uma tabela `usuario` propria; este projeto reaproveita a `users`
 * do starter kit para nao reimplementar autenticacao ja testada. Ver DECISOES.md D-01.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // RF-04: paciente de urgencia frequentemente nao tem e-mail, e o cadastro nao
            // pode ser bloqueado por isso. O indice unique e mantido -- MySQL aceita
            // multiplos NULL em indice unico. Ver DECISOES.md D-02.
            $table->string('email')->nullable()->change();

            // CPF do paciente, matricula do profissional ou codigo provisorio.
            // Nullable ate a Fase 2 remover o cadastro publico do starter kit, que nao
            // consegue produzir um login valido neste dominio. Ver DECISOES.md D-14.
            $table->string('login', 60)->nullable()->unique()->after('id');

            $table->enum('tipo', ['PACIENTE', 'PROFISSIONAL', 'ADMIN'])
                ->nullable()
                ->after('password');

            // RN-06: forca a troca de senha no primeiro acesso.
            $table->boolean('senha_provisoria')->default(false)->after('tipo');
            $table->timestamp('senha_alterada_em')->nullable()->after('senha_provisoria');

            // RNF-08: bloqueio progressivo apos tentativas falhas.
            $table->boolean('ativo')->default(true)->after('senha_alterada_em');
            $table->timestamp('ultimo_login_em')->nullable()->after('ativo');
            $table->unsignedSmallInteger('tentativas_falhas')->default(0)->after('ultimo_login_em');
            $table->timestamp('bloqueado_ate')->nullable()->after('tentativas_falhas');

            // D-08: nenhuma exclusao fisica.
            $table->softDeletes();

            $table->index(['tipo', 'ativo'], 'ix_users_tipo_ativo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('ix_users_tipo_ativo');
            $table->dropSoftDeletes();
            $table->dropUnique(['login']);
            $table->dropColumn([
                'login', 'tipo', 'senha_provisoria', 'senha_alterada_em',
                'ativo', 'ultimo_login_em', 'tentativas_falhas', 'bloqueado_ate',
            ]);
            $table->string('email')->nullable(false)->change();
        });
    }
};
