<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ExameFaixaCritica;
use Illuminate\Database\Seeder;

final class ExameFaixaCriticaSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['analito' => 'Potássio', 'unidade' => 'mEq/L', 'critico_min' => 2.5, 'critico_max' => 6.5],
            ['analito' => 'Sódio', 'unidade' => 'mEq/L', 'critico_min' => 120, 'critico_max' => 160],
            ['analito' => 'Hemoglobina', 'unidade' => 'g/dL', 'critico_min' => 6, 'critico_max' => 20],
            ['analito' => 'Glicose', 'unidade' => 'mg/dL', 'critico_min' => 45, 'critico_max' => 500],
            ['analito' => 'Plaquetas', 'unidade' => '/mm³', 'critico_min' => 20000, 'critico_max' => 1000000],
        ] as $faixa) {
            ExameFaixaCritica::updateOrCreate(
                ['analito' => $faixa['analito'], 'unidade' => $faixa['unidade']],
                $faixa + ['ativo' => true],
            );
        }
    }
}
