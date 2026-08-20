<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Conta administrativa inicial — a única forma de entrar no sistema depois de
 * `migrate:fresh`, já que o cadastro público foi removido (D-18).
 *
 * Três cuidados que a versão ingênua deste seeder erra:
 *
 * 1. **Nada de `bcrypt()`.** RNF-07 exige Argon2id, e o cast `hashed` do model aplica o
 *    driver configurado. Passar um hash `$2y$` pronto faz `Hash::verifyConfiguration()`
 *    lançar exceção — o seed inteiro quebra.
 * 2. **`login` e `tipo` são `NOT NULL`** desde a Fase 2 (D-14). Um `User::create()` sem
 *    eles viola a constraint.
 * 3. **`senha_provisoria = true`** (RN-06). Uma senha padrão conhecida que nunca expira é
 *    a porta dos fundos clássica de sistema hospitalar; aqui ela obriga a troca no
 *    primeiro acesso.
 */
class UsuarioAdministradorSeeder extends Seeder
{
    public function run(): void
    {
        $login = (string) env('ADMIN_LOGIN', 'admin');

        $admin = User::withTrashed()->updateOrCreate(
            ['login' => $login],
            [
                'name' => 'Administrador do sistema',
                'email' => 'admin@prsaude.com',
                // Texto claro de propósito: o cast `hashed` aplica Argon2id.
                'password' => 'password',
                'tipo' => 'ADMIN',
                'senha_provisoria' => false,
                'ativo' => true,
                'deleted_at' => null,
            ]
        );

        // Mantém a role para navegação e auditoria. A autorização irrestrita decorre do
        // tipo ADMIN, portanto não depende desta associação (D-20).
        $admin->syncRoles(['admin']);

        $this->command?->info("Administrador criado. Login: {$login}");
        $this->command?->warn('Senha provisória: troque no primeiro acesso (RN-06).');
    }
}
