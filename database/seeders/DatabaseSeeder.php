<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Carga mínima do SGH: o que é preciso para o sistema funcionar e para alguém conseguir
 * entrar nele.
 *
 * Catálogos (as cinco cores de Manchester, CID-10, medicamentos, exames) e uma conta
 * administrativa. Os dados de *demonstração* -- unidade, equipe completa, 30 pacientes,
 * atendimentos em estados variados -- entram na Fase 13, em seeder próprio, para que
 * `migrate:fresh --seed` continue servindo tanto ao teste quanto à navegação.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // A matriz RBAC vem primeiro: tudo o mais depende de haver papéis.
            RbacSeeder::class,
            // Depois dela, porque precisa da role `admin`.
            UsuarioAdministradorSeeder::class,
            ClassificacaoRiscoSeeder::class,
            Cid10Seeder::class,
            QueixaSeeder::class,
            MedicamentoSeeder::class,
            ExameSeeder::class,
        ]);
    }
}
