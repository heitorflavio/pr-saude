<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Carga mínima do SGH: o que é preciso para o sistema funcionar e para alguém conseguir
 * entrar nele.
 *
 * Os dados de *demonstração* — unidade, equipe completa, 30 pacientes, atendimentos em
 * estados variados — entram na Fase 13, em seeder próprio, para que
 * `migrate:fresh --seed` continue servindo tanto ao teste quanto à navegação.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogoSeeder::class,
            // Depois dos catálogos, porque precisa da role `admin`.
            UsuarioAdministradorSeeder::class,
            DemonstracaoSeeder::class,
        ]);
    }
}
