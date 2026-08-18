<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha a divida da Fase 1 (DECISOES.md D-14).
 *
 * `usuario.login` e `usuario.tipo` sao NOT NULL no schema.sql. Na Fase 1 nasceram
 * nullable porque o cadastro publico do starter kit criava usuario sem nenhum dos dois.
 * Essa rota foi removida na Fase 2 (D-18), entao a folga pode ser fechada: todo usuario
 * passa a ter identificacao de login e de tipo, sempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Usuarios criados antes desta migration (starter kit) nao tem login nem tipo.
        // Backfill deterministico: `login` precisa ser unico, entao deriva do id.
        DB::table('users')->whereNull('login')->update([
            'login' => DB::raw("CONCAT('LEGADO-', id)"),
        ]);

        DB::table('users')->whereNull('tipo')->update(['tipo' => 'PROFISSIONAL']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('login', 60)->nullable(false)->change();
            $table->enum('tipo', ['PACIENTE', 'PROFISSIONAL', 'ADMIN'])->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login', 60)->nullable()->change();
            $table->enum('tipo', ['PACIENTE', 'PROFISSIONAL', 'ADMIN'])->nullable()->change();
        });
    }
};
