<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Os dados sem os quais o sistema não funciona: matriz RBAC e catálogos de domínio.
 *
 * Separado do `DatabaseSeeder` porque a **suíte de testes** semeia exatamente isto — e
 * só isto — uma vez por execução. Contas de demonstração não entram: um teste que conta
 * usuários não deve depender de quem o seeder de ambiente criou.
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Primeiro: tudo o mais depende de haver papéis.
            RbacSeeder::class,
            ClassificacaoRiscoSeeder::class,
            Cid10Seeder::class,
            QueixaSeeder::class,
            MedicamentoSeeder::class,
            ExameSeeder::class,
        ]);
    }
}
