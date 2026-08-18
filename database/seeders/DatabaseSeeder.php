<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Carga de domínio do SGH.
 *
 * Só catálogos aqui: são os dados sem os quais o sistema não funciona (a fila precisa
 * das cinco cores, a prescrição precisa do catálogo de medicamentos). Os dados de
 * demonstração -- unidade, equipe, pacientes, atendimentos -- entram na Fase 13, em um
 * seeder próprio, para que `migrate:fresh --seed` continue servindo tanto ao teste
 * quanto à navegação.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ClassificacaoRiscoSeeder::class,
            Cid10Seeder::class,
            QueixaSeeder::class,
            MedicamentoSeeder::class,
            ExameSeeder::class,
        ]);
    }
}
